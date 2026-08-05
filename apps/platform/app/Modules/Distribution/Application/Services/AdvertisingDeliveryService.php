<?php

namespace App\Modules\Distribution\Application\Services;

use App\Modules\Advertising\Application\Services\AdvertisingConfigurationService;
use App\Modules\Advertising\Application\Services\CampaignEligibilityService;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingFrequencyCounter;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\AdvertiserStudio\Domain\Enums\AssetStatus;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Distribution\Domain\Enums\AdvertisingDeliveryStatus;
use App\Modules\Distribution\Domain\Enums\DeliveryLeaseStatus;
use App\Modules\Distribution\Domain\Enums\FeedSessionStatus;
use App\Modules\Distribution\Domain\Exceptions\DistributionException;
use App\Modules\Distribution\Infrastructure\Models\AdvertisingDelivery;
use App\Modules\Distribution\Infrastructure\Models\AdvertisingDeliveryLease;
use App\Modules\Distribution\Infrastructure\Models\FeedSession;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\ValueEngine\Application\Services\AdvertisingValueCalculator;
use App\Modules\ValueEngine\Application\Services\ValueEngineCatalog;
use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\Wallet\Domain\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final readonly class AdvertisingDeliveryService
{
    public function __construct(
        private ActiveEconomicEntitlementProjection $entitlements,
        private CampaignEligibilityService $eligibility,
        private AdvertisingConfigurationService $advertisingConfiguration,
        private ValueEngineCatalog $valueCatalog,
        private AdvertisingValueCalculator $valueCalculator,
        private AdvertisingDeliveryOutbox $outbox,
    ) {}

    public function startSession(
        Account $account,
        string $idempotencyKey,
        ?string $territory = null,
        ?string $market = null,
    ): FeedSession {
        $idempotencyKey = $this->key($idempotencyKey);

        return DB::transaction(function () use ($account, $idempotencyKey, $territory, $market): FeedSession {
            $existing = FeedSession::query()
                ->where('account_id', $account->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof FeedSession) {
                return $existing;
            }

            $entitlement = $this->entitlements->resolve($account, true);
            $now = now();

            return FeedSession::query()->create([
                'account_id' => $account->id,
                'user_subscription_id' => $entitlement['subscriptionId'],
                'subscription_quota_counter_id' => $entitlement['quotaCounterId'],
                'economic_class_id' => $entitlement['economicClassId'],
                'economic_class_version_id' => $entitlement['economicClassVersionId'],
                'idempotency_key' => $idempotencyKey,
                'status' => FeedSessionStatus::Active,
                'market' => strtoupper($market ?? (string) config('distribution.default_market', 'CI')),
                'currency' => strtoupper((string) config('distribution.default_currency', 'XOF')),
                'territory' => $territory === null ? null : trim($territory),
                'started_at' => $now,
                'expires_at' => $now->copy()->addSeconds(
                    max(60, (int) config('distribution.feed_session_ttl_seconds', 900)),
                ),
            ]);
        }, 5);
    }

    public function reserveNext(
        Account $account,
        FeedSession $session,
        string $idempotencyKey,
    ): AdvertisingDelivery {
        $idempotencyKey = $this->key($idempotencyKey);

        return DB::transaction(function () use ($account, $session, $idempotencyKey): AdvertisingDelivery {
            $existing = AdvertisingDelivery::query()
                ->where('account_id', $account->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof AdvertisingDelivery) {
                return $existing;
            }

            $lockedSession = FeedSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->guardSession($lockedSession, $account);
            $entitlement = $this->entitlements->resolve($account, true);

            if ($entitlement['quotaCounterId'] !== $lockedSession->subscription_quota_counter_id) {
                throw new DistributionException('La session Feed ne correspond plus au cycle de quota actif.');
            }

            if ($entitlement['remaining'] <= 0) {
                throw new DistributionException('Le quota publicitaire actif est épuisé.');
            }

            [$match, $campaign, $version, $creative, $funding] = $this->selectCandidate($account);
            $now = now();
            $expiresAt = $now->copy()->addSeconds(
                max(5, (int) config('distribution.delivery_lease_ttl_seconds', 30)),
            );

            $lease = AdvertisingDeliveryLease::query()->create([
                'feed_session_id' => $lockedSession->id,
                'account_id' => $account->id,
                'advertising_match_id' => $match->id,
                'idempotency_key' => $idempotencyKey,
                'status' => DeliveryLeaseStatus::Reserved,
                'reserved_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            $preview = $this->valuePreview(
                $entitlement['economicClassCode'],
                $lockedSession->market,
                $funding,
            );

            $delivery = AdvertisingDelivery::query()->create([
                'advertising_delivery_lease_id' => $lease->id,
                'feed_session_id' => $lockedSession->id,
                'account_id' => $account->id,
                'advertising_match_id' => $match->id,
                'campaign_id' => $campaign->id,
                'campaign_version_id' => $version->id,
                'creative_asset_id' => $creative->id,
                'subscription_quota_counter_id' => $entitlement['quotaCounterId'],
                'economic_class_version_id' => $entitlement['economicClassVersionId'],
                'idempotency_key' => $idempotencyKey,
                'status' => AdvertisingDeliveryStatus::Prepared,
                'creative_type' => $creative->kind->value,
                'media_reference' => Storage::disk($creative->disk)->url($creative->path),
                'cta_reference' => $version->destination_url,
                'explanation_reference' => route(
                    'advertising.profile.explanation',
                    ['match' => $match->id],
                    false,
                ),
                'value_preview' => $preview,
                'context_hash' => $this->contextHash(
                    $account,
                    $match,
                    $campaign,
                    $version,
                    $creative,
                    $entitlement,
                ),
                'prepared_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            $this->event($delivery, 'AdvertisingDeliveryReserved', 'delivery-reserved:'.$delivery->id, [
                'feed_session_id' => $lockedSession->id,
                'match_id' => $match->id,
                'campaign_id' => $campaign->id,
                'quota_consumed' => false,
            ]);
            $this->outbox->record(
                'advertising_delivery',
                $delivery->id,
                'ADVERTISING_DELIVERY_RESERVED',
                'delivery-reserved:'.$delivery->id,
                [
                    'delivery_id' => $delivery->id,
                    'feed_session_id' => $lockedSession->id,
                    'campaign_id' => $campaign->id,
                    'campaign_version_id' => $version->id,
                    'creative_id' => $creative->id,
                    'quota_consumed' => false,
                    'financial_operation_created' => false,
                ],
            );

            DB::afterCommit(static fn () => event('distribution.delivery-reserved', [$delivery->id]));

            return $delivery;
        }, 5);
    }

    public function deliver(
        Account $account,
        AdvertisingDelivery $delivery,
        string $idempotencyKey,
    ): AdvertisingDelivery {
        $idempotencyKey = $this->key($idempotencyKey);

        return DB::transaction(function () use ($account, $delivery, $idempotencyKey): AdvertisingDelivery {
            $locked = AdvertisingDelivery::query()
                ->whereKey($delivery->id)
                ->with(['lease', 'session', 'campaign.activeVersion', 'creative'])
                ->lockForUpdate()
                ->firstOrFail();
            $this->guardOwnership($locked, $account);

            if ($locked->status === AdvertisingDeliveryStatus::Delivered) {
                return $locked;
            }

            if ($locked->status !== AdvertisingDeliveryStatus::Prepared) {
                throw new DistributionException('Cette livraison n’est plus finalisable.');
            }

            $lease = AdvertisingDeliveryLease::query()->whereKey($locked->advertising_delivery_lease_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lease->status !== DeliveryLeaseStatus::Reserved
                || $lease->expires_at->isPast()
                || $locked->expires_at->isPast()
            ) {
                throw new DistributionException('Le bail de livraison a expiré sans consommation de quota.');
            }

            $session = FeedSession::query()->whereKey($locked->feed_session_id)->lockForUpdate()->firstOrFail();
            $this->guardSession($session, $account);

            $campaign = Campaign::query()
                ->whereKey($locked->campaign_id)
                ->with([
                    'activeVersion.creatives',
                    'funding.quote',
                    'funding.budgetReservation.walletReservation',
                ])
                ->lockForUpdate()
                ->firstOrFail();
            $currentMatch = $this->eligibility->evaluate($account, $campaign);
            $this->guardCandidate($account, $currentMatch, $campaign);

            if ($currentMatch->campaign_version_id !== $locked->campaign_version_id) {
                throw new DistributionException('La version de campagne a changé avant la livraison.');
            }

            $activeVersion = $campaign->activeVersion;

            if (! $activeVersion instanceof CampaignVersion) {
                throw new DistributionException('La campagne ne possède plus de version active.');
            }

            $creative = $activeVersion->creatives
                ->firstWhere('id', $locked->creative_asset_id);

            if (! $creative instanceof CreativeAsset || $creative->status !== AssetStatus::Ready) {
                throw new DistributionException('Le créatif n’est plus livrable.');
            }

            $this->guardFunding($campaign->funding);

            $entitlement = $this->entitlements->resolve($account, true);

            if (
                $entitlement['quotaCounterId'] !== $locked->subscription_quota_counter_id
                || $entitlement['economicClassVersionId'] !== $locked->economic_class_version_id
            ) {
                throw new DistributionException('Le droit économique a changé avant la livraison.');
            }

            if ($entitlement['remaining'] <= 0) {
                throw new DistributionException('Le quota publicitaire actif est épuisé.');
            }

            $existingConsumption = DB::table('advertising_quota_consumptions')
                ->where('advertising_delivery_id', $locked->id)
                ->first();

            if ($existingConsumption !== null) {
                return $locked->refresh();
            }

            $this->advanceFrequency($account, $campaign);
            $before = $entitlement['remaining'];
            $after = $before - 1;

            DB::table('subscription_quota_counters')
                ->where('id', $entitlement['quotaCounterId'])
                ->update([
                    'consumed' => $entitlement['consumed'] + 1,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            DB::table('advertising_quota_consumptions')->insert([
                'id' => (string) Str::ulid(),
                'advertising_delivery_id' => $locked->id,
                'subscription_quota_counter_id' => $entitlement['quotaCounterId'],
                'economic_class_version_id' => $entitlement['economicClassVersionId'],
                'event_code' => 'AD_DELIVERED',
                'quota_before' => $before,
                'quota_after' => $after,
                'idempotency_key' => 'quota:'.$idempotencyKey,
                'consumed_at' => now(),
                'created_at' => now(),
            ]);

            $lease->forceFill([
                'status' => DeliveryLeaseStatus::Delivered,
                'delivered_at' => now(),
            ])->save();

            $locked->forceFill([
                'advertising_match_id' => $currentMatch->id,
                'status' => AdvertisingDeliveryStatus::Delivered,
                'delivered_at' => now(),
            ])->save();

            $this->event($locked, 'AdDelivered', 'ad-delivered:'.$locked->id, [
                'quota_before' => $before,
                'quota_after' => $after,
                'frequency_advanced' => true,
                'financial_operation_created' => false,
            ]);
            $this->outbox->record(
                'advertising_delivery',
                $locked->id,
                'AD_DELIVERED',
                'ad-delivered:'.$locked->id,
                [
                    'delivery_id' => $locked->id,
                    'feed_session_id' => $locked->feed_session_id,
                    'match_id' => $currentMatch->id,
                    'campaign_id' => $locked->campaign_id,
                    'campaign_version_id' => $locked->campaign_version_id,
                    'creative_id' => $locked->creative_asset_id,
                    'quota_remaining' => $after,
                    'financial_operation_created' => false,
                    'wallet_operation_created' => false,
                ],
            );

            DB::afterCommit(static fn () => event('distribution.ad-delivered', [$locked->id]));

            return $locked->refresh();
        }, 5);
    }

    public function release(
        Account $account,
        AdvertisingDelivery $delivery,
        string $idempotencyKey,
    ): AdvertisingDelivery {
        $this->key($idempotencyKey);

        return DB::transaction(function () use ($account, $delivery): AdvertisingDelivery {
            $locked = AdvertisingDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $this->guardOwnership($locked, $account);

            if ($locked->status === AdvertisingDeliveryStatus::Delivered) {
                throw new DistributionException('Une livraison confirmée est immuable.');
            }

            if ($locked->status !== AdvertisingDeliveryStatus::Prepared) {
                return $locked;
            }

            $lease = AdvertisingDeliveryLease::query()->whereKey($locked->advertising_delivery_lease_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lease->forceFill([
                'status' => DeliveryLeaseStatus::Released,
                'released_at' => now(),
            ])->save();
            $locked->forceFill([
                'status' => AdvertisingDeliveryStatus::Invalidated,
                'invalidated_at' => now(),
            ])->save();

            $this->event($locked, 'AdvertisingDeliveryReleased', 'delivery-released:'.$locked->id, [
                'quota_consumed' => false,
            ]);
            $this->outbox->record(
                'advertising_delivery',
                $locked->id,
                'ADVERTISING_DELIVERY_RELEASED',
                'delivery-released:'.$locked->id,
                [
                    'delivery_id' => $locked->id,
                    'quota_consumed' => false,
                    'financial_operation_created' => false,
                ],
            );

            return $locked->refresh();
        }, 5);
    }

    public function expireDue(): int
    {
        $ids = AdvertisingDeliveryLease::query()
            ->where('status', DeliveryLeaseStatus::Reserved->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $expired = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $lease = AdvertisingDeliveryLease::query()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($lease->status !== DeliveryLeaseStatus::Reserved || $lease->expires_at->isFuture()) {
                    return;
                }

                $lease->forceFill(['status' => DeliveryLeaseStatus::Expired])->save();
                $delivery = AdvertisingDelivery::query()
                    ->where('advertising_delivery_lease_id', $lease->id)
                    ->lockForUpdate()
                    ->first();

                if ($delivery instanceof AdvertisingDelivery && $delivery->status === AdvertisingDeliveryStatus::Prepared) {
                    $delivery->forceFill([
                        'status' => AdvertisingDeliveryStatus::Invalidated,
                        'invalidated_at' => now(),
                    ])->save();
                    $this->event($delivery, 'AdvertisingDeliveryExpired', 'delivery-expired:'.$delivery->id, [
                        'quota_consumed' => false,
                    ]);
                    $this->outbox->record(
                        'advertising_delivery',
                        $delivery->id,
                        'ADVERTISING_DELIVERY_EXPIRED',
                        'delivery-expired:'.$delivery->id,
                        [
                            'delivery_id' => $delivery->id,
                            'quota_consumed' => false,
                            'financial_operation_created' => false,
                        ],
                    );
                }
            }, 5);

            $expired++;
        }

        return $expired;
    }

    /** @return array<string, mixed> */
    public function present(Account $account, AdvertisingDelivery $delivery): array
    {
        $this->guardOwnership($delivery, $account);
        $counter = DB::table('subscription_quota_counters')
            ->where('id', $delivery->subscription_quota_counter_id)
            ->first();
        $remaining = $counter === null
            ? 0
            : max(0, (int) $counter->quota_limit - (int) $counter->consumed);

        return [
            'delivery_id' => $delivery->id,
            'feed_session_id' => $delivery->feed_session_id,
            'match_id' => $delivery->advertising_match_id,
            'campaign_id' => $delivery->campaign_id,
            'campaign_version_id' => $delivery->campaign_version_id,
            'creative_id' => $delivery->creative_asset_id,
            'creative_type' => $delivery->creative_type,
            'media_reference' => $delivery->media_reference,
            'cta_reference' => $delivery->cta_reference,
            'explanation_reference' => $delivery->explanation_reference,
            'quota_remaining' => $remaining,
            'value_preview' => $delivery->value_preview,
            'status' => $delivery->status->value,
            'expires_at' => $delivery->expires_at->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
        ];
    }

    /** @return array{0:AdvertisingMatch,1:Campaign,2:CampaignVersion,3:CreativeAsset,4:CampaignFunding} */
    private function selectCandidate(Account $account): array
    {
        $candidates = AdvertisingMatch::query()
            ->where('account_id', $account->id)
            ->where('decision', 'eligible')
            ->where('evaluated_at', '>=', now()->subMinutes(
                max(1, (int) config('distribution.match_ttl_minutes', 60)),
            ))
            ->orderByDesc('score_basis_points')
            ->orderByDesc('evaluated_at')
            ->limit(max(1, min(200, (int) config('distribution.candidate_limit', 50))))
            ->get();

        foreach ($candidates as $storedMatch) {
            $campaign = Campaign::query()
                ->whereKey($storedMatch->campaign_id)
                ->with([
                    'activeVersion.creatives',
                    'funding.quote',
                    'funding.budgetReservation.walletReservation',
                ])
                ->first();

            if (! $campaign instanceof Campaign) {
                continue;
            }

            try {
                $currentMatch = $this->eligibility->evaluate($account, $campaign);
                $this->guardCandidate($account, $currentMatch, $campaign);
                $version = $campaign->activeVersion;
                $funding = $campaign->funding;

                if (! $version instanceof CampaignVersion || ! $funding instanceof CampaignFunding) {
                    continue;
                }

                $this->guardFunding($funding);
                $creative = $version->creatives
                    ->first(static fn (CreativeAsset $asset): bool => $asset->status === AssetStatus::Ready);

                if (! $creative instanceof CreativeAsset) {
                    continue;
                }

                $alreadyDelivered = AdvertisingDelivery::query()
                    ->where('advertising_match_id', $currentMatch->id)
                    ->where('status', AdvertisingDeliveryStatus::Delivered->value)
                    ->exists();

                if ($alreadyDelivered) {
                    continue;
                }

                return [$currentMatch, $campaign, $version, $creative, $funding];
            } catch (Throwable) {
                continue;
            }
        }

        throw new DistributionException('Aucune publicité livrable n’est disponible pour cette session.');
    }

    private function guardCandidate(Account $account, AdvertisingMatch $match, Campaign $campaign): void
    {
        if (
            $match->account_id !== $account->id
            || $match->campaign_id !== $campaign->id
            || $match->decision !== 'eligible'
        ) {
            throw new DistributionException('Le Matching ne permet plus cette livraison.');
        }

        if ($campaign->status !== 'approved' || $campaign->active_version_id !== $match->campaign_version_id) {
            throw new DistributionException('La campagne ou sa version ne sont plus livrables.');
        }

        $version = $campaign->activeVersion;

        if (! $version instanceof CampaignVersion) {
            throw new DistributionException('La campagne ne possède plus de version active.');
        }

        $today = now()->startOfDay();

        if (
            ($version->starts_on !== null && $version->starts_on->startOfDay()->isAfter($today))
            || ($version->ends_on !== null && $version->ends_on->endOfDay()->isBefore($today))
        ) {
            throw new DistributionException('La campagne est hors de sa période de diffusion.');
        }
    }

    private function guardFunding(?CampaignFunding $funding): void
    {
        $budget = $funding?->budgetReservation;

        if (
            ! $funding instanceof CampaignFunding
            || $funding->status !== 'approved'
            || ! $budget instanceof CampaignBudgetReservation
            || $budget->status !== 'active'
            || $budget->walletReservation === null
            || $budget->walletReservation->status !== ReservationStatus::Active
        ) {
            throw new DistributionException('Le financement de campagne n’est plus actif.');
        }
    }

    private function advanceFrequency(Account $account, Campaign $campaign): void
    {
        $controls = $this->advertisingConfiguration->runtime();
        $now = CarbonImmutable::now();
        $hours = max(1, $controls['frequencyWindowHours']);
        $windowSeconds = $hours * 3600;
        $windowStartTimestamp = intdiv($now->timestamp, $windowSeconds) * $windowSeconds;
        $windowStart = $now->setTimestamp($windowStartTimestamp)->utc();
        $windowEnd = $windowStart->addSeconds($windowSeconds);

        AdvertisingFrequencyCounter::query()->firstOrCreate(
            [
                'account_id' => $account->id,
                'campaign_id' => $campaign->id,
                'window_start' => $windowStart,
            ],
            [
                'window_end' => $windowEnd,
                'impressions' => 0,
                'fatigue_score' => 0,
                'version' => 0,
            ],
        );

        $counter = AdvertisingFrequencyCounter::query()
            ->where('account_id', $account->id)
            ->where('campaign_id', $campaign->id)
            ->where('window_start', $windowStart)
            ->lockForUpdate()
            ->firstOrFail();

        if ($counter->impressions >= $controls['frequencyLimit']) {
            throw new DistributionException('La limite de fréquence est atteinte.');
        }

        if ($counter->fatigue_score >= $controls['fatigueLimit']) {
            throw new DistributionException('La campagne est retenue par le contrôle de fatigue.');
        }

        $counter->forceFill([
            'impressions' => $counter->impressions + 1,
            'fatigue_score' => min($controls['fatigueLimit'], $counter->fatigue_score + 1),
            'last_impression_at' => $now,
            'version' => $counter->version + 1,
        ])->save();
    }

    /** @return array<string, mixed> */
    private function valuePreview(
        string $economicClass,
        string $market,
        CampaignFunding $funding,
    ): array {
        try {
            $rule = $this->valueCatalog->resolveAdvertisingRule(
                economicClass: $economicClass,
                countryCode: $market,
                currency: $funding->quote->currency,
            );
            $quote = $this->valueCalculator->quote($funding->quote, $rule);
        } catch (ValueEngineException $exception) {
            throw new DistributionException($exception->getMessage(), 0, $exception);
        }

        return [
            'gross_amount_minor' => $quote->grossAmountMinor,
            'user_amount_minor' => $quote->userAmountMinor,
            'wasplex_amount_minor' => $quote->wasplexAmountMinor,
            'unit' => $quote->unit,
            'currency' => $quote->currency,
            'rule_version_id' => $quote->ruleVersionId,
            'calculation_hash' => $quote->calculationHash,
            'attempt_started' => false,
        ];
    }

    /**
     * @param array{
     *     subscriptionId:string,
     *     quotaCounterId:string,
     *     economicClassId:string,
     *     economicClassVersionId:string,
     *     economicClassCode:string,
     *     quotaLimit:int,
     *     consumed:int,
     *     remaining:int,
     *     cycleStart:string,
     *     cycleEnd:string
     * } $entitlement
     */
    private function contextHash(
        Account $account,
        AdvertisingMatch $match,
        Campaign $campaign,
        CampaignVersion $version,
        CreativeAsset $creative,
        array $entitlement,
    ): string {
        try {
            return hash('sha256', json_encode([
                'account' => $account->id,
                'match' => $match->id,
                'campaign' => $campaign->id,
                'campaign_version' => $version->id,
                'creative' => $creative->id,
                'quota_counter' => $entitlement['quotaCounterId'],
                'economic_version' => $entitlement['economicClassVersionId'],
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new DistributionException('Le contexte de livraison ne peut pas être canonisé.', 0, $exception);
        }
    }

    private function guardSession(FeedSession $session, Account $account): void
    {
        if ($session->account_id !== $account->id) {
            throw new DistributionException('Cette session Feed ne correspond pas au compte authentifié.');
        }

        if ($session->status !== FeedSessionStatus::Active || $session->expires_at->isPast()) {
            throw new DistributionException('La session Feed n’est plus active.');
        }
    }

    private function guardOwnership(AdvertisingDelivery $delivery, Account $account): void
    {
        if ($delivery->account_id !== $account->id) {
            throw new DistributionException('Cette livraison ne correspond pas au compte authentifié.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function event(
        AdvertisingDelivery $delivery,
        string $eventType,
        string $idempotencyKey,
        array $metadata,
    ): void {
        DB::table('advertising_delivery_events')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'advertising_delivery_id' => $delivery->id,
            'event_type' => $eventType,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    private function key(string $key): string
    {
        $key = trim($key);

        if ($key === '' || strlen($key) > 64) {
            throw new DistributionException('Une clé d’idempotence valide est obligatoire.');
        }

        return $key;
    }
}
