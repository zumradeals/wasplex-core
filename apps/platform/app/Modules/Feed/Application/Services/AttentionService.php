<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Campaigns\Application\Services\CampaignEnvelopeExhaustedException;
use App\Modules\Campaigns\Application\Services\CampaignNotAvailableForDeliveryException;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Feed\Infrastructure\Models\FeedCampaignReward;
use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Subscriptions\Application\Services\FreeSubscriptionProvisioner;
use App\Modules\Subscriptions\Application\Services\NoActiveSubscriptionException;
use App\Modules\Subscriptions\Application\Services\QuotaExhaustedException;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chaîne serveur du Feed : le client ne crédite jamais lui-même. Une
 * livraison reste idempotente par son id et, désormais, la récompense est
 * également atomique par couple membre + campagne.
 */
final class AttentionService
{
    private const MAX_COMPOSITION_ATTEMPTS = 5;

    public function __construct(
        private readonly FeedCompositionService $composition,
        private readonly CampaignEnvelopeContract $envelope,
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly BrandDirectoryContract $brands,
        private readonly CreativeAssetDirectoryContract $creativeAssets,
        private readonly MatchingContract $matching,
        private readonly FreeSubscriptionProvisioner $freeSubscription,
        private readonly SubscriptionQuotaContract $quota,
        private readonly AdvertiserWalletReservationContract $advertiserReservations,
        private readonly UserWalletContract $userWallet,
        private readonly FeedRewardEligibilityService $rewardEligibility,
    ) {}

    /**
     * @return array{delivery: FeedAdDelivery, brand_name: ?string, objective_code: ?string, cta_label: ?string, creative: ?array{url: string, type: string, duration: ?int, duration_ms?: ?int}}|null
     */
    public function next(string $feedSessionId, string $accountId): ?array
    {
        $pending = FeedAdDelivery::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED])
            ->orderByDesc('created_at')
            ->first();

        if ($pending !== null) {
            if ($this->rewardEligibility->isBlockedForOrganization($accountId, $pending->organization_id)) {
                $this->neutralizeBlockedDelivery($pending, $accountId);
            } else {
                $presented = $this->present($pending);

                if (! $this->hasIncompatibleVideoEconomics($presented)) {
                    return $presented;
                }

                // Une livraison créée avant la migration peut encore porter
                // l'ancien quota fixe ou ne pas exiger la fin réelle du média.
                // On la libère puis on compose une livraison neuve afin de ne
                // jamais diffuser silencieusement avec une économie obsolète.
                $this->abandon($pending->id, $accountId);
            }
        }

        $this->freeSubscription->ensureFor($accountId);

        $remainingQuotaUnits = 0;
        try {
            $remainingQuotaUnits = $this->quota->currentCounter($accountId)->remaining();
        } catch (NoActiveSubscriptionException) {
            $remainingQuotaUnits = 0;
        }
        $allowRewardable = $remainingQuotaUnits > 0;

        $excluded = [];

        for ($attempt = 0; $attempt < self::MAX_COMPOSITION_ATTEMPTS; $attempt++) {
            $candidate = $this->composition->nextCandidate($accountId, $excluded, $allowRewardable);

            if ($candidate === null) {
                return null;
            }

            $campaign = $this->campaigns->find($candidate->campaignId);

            if ($campaign === null) {
                $excluded[] = $candidate->campaignId;

                continue;
            }

            if ($candidate->isReplay) {
                $reward = FeedCampaignReward::query()
                    ->where('account_id', $accountId)
                    ->where('campaign_id', $candidate->campaignId)
                    ->first();

                if ($reward === null) {
                    $excluded[] = $candidate->campaignId;

                    continue;
                }

                // Un replay est une exposition non rémunérée : aucun nouveau
                // slot de budget n'est réservé et aucun quota n'est consommé.
                $delivery = FeedAdDelivery::query()->create([
                    'feed_session_id' => $feedSessionId,
                    'account_id' => $accountId,
                    'campaign_id' => $candidate->campaignId,
                    'organization_id' => $reward->organization_id,
                    'campaign_envelope_consumption_id' => "replay:{$reward->id}",
                    'economic_class' => $reward->economic_class,
                    'is_replay' => true,
                    'gain_minor' => 0,
                    'required_duration_ms' => $reward->required_duration_ms,
                    'quota_units' => 0,
                    'requires_media_end' => false,
                    'visible_duration_ms' => 0,
                    'progress_percent' => 0,
                    'status' => FeedAdDelivery::STATUS_REPLAY,
                    'reserved_at' => Carbon::now('UTC'),
                ]);

                return $this->present($delivery);
            }

            try {
                $slot = $this->envelope->reserveSlot(
                    $candidate->campaignId,
                    $candidate->economicClass,
                    "feed-reserve:{$accountId}:{$candidate->campaignId}:".uniqid('', true),
                );
            } catch (CampaignEnvelopeExhaustedException|CampaignNotAvailableForDeliveryException) {
                $excluded[] = $candidate->campaignId;

                continue;
            }

            if ($slot->attentionUnits > $remainingQuotaUnits) {
                $this->envelope->releaseSlot($slot->consumptionId);
                $excluded[] = $candidate->campaignId;

                continue;
            }

            // Final pre-reservation race check. If the account joined the
            // advertiser organization between composition and reservation,
            // release the slot immediately and never expose a rewardable
            // delivery for that campaign.
            if ($this->rewardEligibility->isBlockedForOrganization($accountId, $slot->organizationId)) {
                $this->envelope->releaseSlot($slot->consumptionId);
                $excluded[] = $candidate->campaignId;

                continue;
            }

            $delivery = FeedAdDelivery::query()->create([
                'feed_session_id' => $feedSessionId,
                'account_id' => $accountId,
                'campaign_id' => $candidate->campaignId,
                'organization_id' => $slot->organizationId,
                'campaign_envelope_consumption_id' => $slot->consumptionId,
                'economic_class' => $candidate->economicClass,
                'is_replay' => false,
                'gain_minor' => $slot->gainMinor,
                'required_duration_ms' => $slot->requiredDurationMs,
                'quota_units' => $slot->attentionUnits,
                'requires_media_end' => $slot->requiresMediaEnd,
                'status' => FeedAdDelivery::STATUS_RESERVED,
                'reserved_at' => Carbon::now('UTC'),
            ]);

            return $this->present($delivery);
        }

        return null;
    }

    public function start(string $deliveryId, string $accountId): FeedAdDelivery
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        // Les replays sont déjà diffusables : ils n'ouvrent ni session
        // rémunérée, ni quota, ni réservation financière supplémentaire.
        if ($delivery->status === FeedAdDelivery::STATUS_REPLAY) {
            return $delivery;
        }

        // A stale/direct API call must not consume quota for an advertiser's
        // own campaign, even if the delivery was reserved before the account
        // joined the organization.
        if ($this->rewardEligibility->isBlockedForOrganization($accountId, $delivery->organization_id)) {
            return $this->neutralizeBlockedDelivery($delivery, $accountId);
        }

        if ($delivery->status !== FeedAdDelivery::STATUS_STARTED) {
            if ($delivery->status !== FeedAdDelivery::STATUS_RESERVED || $this->isExpired($delivery)) {
                throw new DeliveryNotStartableException;
            }

            try {
                $this->quota->consume($accountId, max(1, (int) $delivery->quota_units), "feed-quota:{$delivery->id}");
            } catch (QuotaExhaustedException $exception) {
                $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);
                $delivery->update(['status' => FeedAdDelivery::STATUS_EXPIRED]);

                throw $exception;
            }

            $delivery->update(['status' => FeedAdDelivery::STATUS_STARTED, 'started_at' => Carbon::now('UTC')]);
        }

        return $delivery->refresh();
    }

    public function heartbeat(string $deliveryId, string $accountId, int $visibleDurationMs): FeedAdDelivery
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        if ($delivery->status !== FeedAdDelivery::STATUS_STARTED) {
            return $delivery;
        }

        $now = Carbon::now('UTC');
        $realElapsedMs = (int) max(0, $now->diffInMilliseconds($delivery->started_at, true));
        $clamped = max($delivery->visible_duration_ms, min($visibleDurationMs, $realElapsedMs));
        $progress = $delivery->required_duration_ms > 0
            ? (int) min(100, intdiv($clamped * 100, $delivery->required_duration_ms))
            : 100;

        $attributes = [
            'visible_duration_ms' => $clamped,
            'progress_percent' => $progress,
            'last_heartbeat_at' => $now,
        ];

        $signalCode = $this->detectRiskSignal($delivery, $now, $visibleDurationMs, $realElapsedMs);

        if ($signalCode !== null) {
            $attributes['risk_signal_count'] = $delivery->risk_signal_count + 1;
            $attributes['last_risk_signal_code'] = $signalCode;
        }

        $delivery->update($attributes);

        return $delivery->refresh();
    }

    public function markMediaEnded(string $deliveryId, string $accountId, int $visibleDurationMs): FeedAdDelivery
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        if ($delivery->status === FeedAdDelivery::STATUS_REPLAY) {
            return $delivery;
        }
        if ($delivery->status !== FeedAdDelivery::STATUS_STARTED) {
            throw new AttentionNotQualifiedException;
        }

        $now = Carbon::now('UTC');
        $realElapsedMs = (int) max(0, $now->diffInMilliseconds($delivery->started_at, true));
        $claimed = min($visibleDurationMs, $realElapsedMs);
        $toleranceMs = max(0, (int) config('feed.media_end_tolerance_ms'));

        if ($delivery->requires_media_end
            && ($delivery->last_heartbeat_at === null
                || $delivery->visible_duration_ms + $toleranceMs < $delivery->required_duration_ms
                || $claimed + $toleranceMs < $delivery->required_duration_ms)) {
            throw new AttentionNotQualifiedException;
        }

        $delivery->update([
            'visible_duration_ms' => max($delivery->visible_duration_ms, $delivery->required_duration_ms),
            'progress_percent' => 100,
            'last_heartbeat_at' => $now,
            'media_ended_at' => $now,
        ]);

        return $delivery->refresh();
    }

    private function detectRiskSignal(FeedAdDelivery $delivery, Carbon $now, int $visibleDurationMs, int $realElapsedMs): ?string
    {
        if ($delivery->last_heartbeat_at !== null) {
            $sinceLastHeartbeatMs = $now->diffInMilliseconds($delivery->last_heartbeat_at, true);

            if ($sinceLastHeartbeatMs < (int) config('feed.heartbeat_min_interval_ms')) {
                return 'heartbeat_rate_abuse';
            }
        }

        if ($visibleDurationMs - $realElapsedMs > (int) config('feed.overclaim_tolerance_ms')) {
            return 'overclaimed_duration';
        }

        return null;
    }

    /**
     * @return array{delivery: FeedAdDelivery, gain_minor: int, balance_minor: int}
     */
    public function complete(string $deliveryId, string $accountId): array
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        if ($delivery->status === FeedAdDelivery::STATUS_COMPLETED) {
            return [
                'delivery' => $delivery,
                'gain_minor' => $delivery->gain_minor,
                'balance_minor' => $this->userWallet->balanceMinor($accountId),
            ];
        }

        if ($delivery->status === FeedAdDelivery::STATUS_REPLAY) {
            return [
                'delivery' => $delivery,
                'gain_minor' => 0,
                'balance_minor' => $this->userWallet->balanceMinor($accountId),
            ];
        }

        if (in_array($delivery->status, [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED], true)
            && $this->rewardEligibility->isBlockedForOrganization($accountId, $delivery->organization_id)) {
            $delivery = $this->neutralizeBlockedDelivery($delivery, $accountId);

            return [
                'delivery' => $delivery,
                'gain_minor' => 0,
                'balance_minor' => $this->userWallet->balanceMinor($accountId),
            ];
        }

        if ($delivery->status === FeedAdDelivery::STATUS_HELD) {
            return [
                'delivery' => $delivery,
                'gain_minor' => 0,
                'balance_minor' => $this->userWallet->balanceMinor($accountId),
            ];
        }

        if ($delivery->status !== FeedAdDelivery::STATUS_STARTED || $delivery->progress_percent < 100) {
            throw new AttentionNotQualifiedException;
        }

        if ($delivery->requires_media_end && $delivery->media_ended_at === null) {
            throw new AttentionNotQualifiedException;
        }

        if ($delivery->risk_signal_count >= (int) config('feed.risk_hold_threshold')) {
            DB::transaction(function () use ($delivery): void {
                $delivery->update(['status' => FeedAdDelivery::STATUS_HELD]);

                FeedAdDeliveryHold::query()->create([
                    'feed_ad_delivery_id' => $delivery->id,
                    'amount_minor' => $delivery->gain_minor,
                    'reason_code' => $delivery->last_risk_signal_code ?? 'risk_signal_threshold',
                    'status' => FeedAdDeliveryHold::STATUS_CREATED,
                    'opened_at' => Carbon::now('UTC'),
                    'evidence' => [
                        'risk_signal_count' => $delivery->risk_signal_count,
                        'visible_duration_ms' => $delivery->visible_duration_ms,
                        'required_duration_ms' => $delivery->required_duration_ms,
                    ],
                ]);
            });

            return [
                'delivery' => $delivery->refresh(),
                'gain_minor' => 0,
                'balance_minor' => $this->userWallet->balanceMinor($accountId),
            ];
        }

        $rewarded = DB::transaction(function () use ($delivery, $accountId): bool {
            // Re-check immediately inside the payment transaction. The
            // composition/start guards are UX and cost protections; this is
            // the final economic barrier before reward claim and capture.
            if ($this->rewardEligibility->isBlockedForOrganization($accountId, $delivery->organization_id)) {
                $this->neutralizeBlockedDelivery($delivery, $accountId);

                return false;
            }

            $now = Carbon::now('UTC');
            $rewardId = (string) Str::ulid();

            // insertOrIgnore se traduit par ON CONFLICT DO NOTHING sur
            // PostgreSQL : le verrou UNIQUE(account_id, campaign_id) décide
            // atomiquement quel traitement a le droit de payer.
            $claimed = FeedCampaignReward::query()->insertOrIgnore([
                'id' => $rewardId,
                'account_id' => $accountId,
                'campaign_id' => $delivery->campaign_id,
                'first_delivery_id' => $delivery->id,
                'organization_id' => $delivery->organization_id,
                'economic_class' => $delivery->economic_class,
                'reward_minor' => $delivery->gain_minor,
                'required_duration_ms' => $delivery->required_duration_ms,
                'ledger_transaction_id' => null,
                'rewarded_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;

            if (! $claimed) {
                // Une autre livraison de cette même campagne a déjà gagné la
                // course. Celle-ci devient un replay : libération du slot et
                // restitution du quota consommé au start(), sans aucun crédit.
                $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);

                try {
                    $this->quota->restore($accountId, max(1, (int) $delivery->quota_units), "feed-quota-duplicate:{$delivery->id}");
                } catch (NoActiveSubscriptionException) {
                    // La récompense reste protégée même si l'abonnement a
                    // expiré entre start() et complete().
                }

                $delivery->update([
                    'is_replay' => true,
                    'gain_minor' => 0,
                    'status' => FeedAdDelivery::STATUS_COMPLETED,
                    'completed_at' => $now,
                    'ledger_transaction_id' => null,
                ]);

                return false;
            }

            $this->envelope->captureSlot($delivery->campaign_envelope_consumption_id);

            $destination = $this->userWallet->availableAccountReference($accountId);

            $ledgerTransactionId = $this->advertiserReservations->capture(
                $delivery->organization_id,
                $delivery->campaign_id,
                $delivery->gain_minor,
                $destination,
                "feed-capture:{$delivery->id}",
            );

            FeedCampaignReward::query()->whereKey($rewardId)->update([
                'ledger_transaction_id' => $ledgerTransactionId,
                'updated_at' => $now,
            ]);

            $delivery->update([
                'status' => FeedAdDelivery::STATUS_COMPLETED,
                'completed_at' => $now,
                'ledger_transaction_id' => $ledgerTransactionId,
            ]);

            return true;
        });

        if ($rewarded) {
            $this->userWallet->notifyBalanceChanged(
                $accountId,
                $delivery->gain_minor,
                'feed.attention',
                'credit',
                $delivery->ledger_transaction_id,
            );
        }

        return [
            'delivery' => $delivery->refresh(),
            'gain_minor' => $rewarded ? $delivery->gain_minor : 0,
            'balance_minor' => $this->userWallet->balanceMinor($accountId),
        ];
    }

    public function abandon(string $deliveryId, string $accountId): FeedAdDelivery
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        if (in_array($delivery->status, [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED], true)) {
            $quotaWasConsumed = $delivery->status === FeedAdDelivery::STATUS_STARTED;
            $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);

            if ($quotaWasConsumed) {
                try {
                    $this->quota->restore(
                        $accountId,
                        max(1, (int) $delivery->quota_units),
                        "feed-quota-abandon:{$delivery->id}",
                    );
                } catch (NoActiveSubscriptionException) {
                    // L'abandon reste valide si l'abonnement expire pendant la lecture.
                }
            }

            $delivery->update(['status' => FeedAdDelivery::STATUS_ABANDONED]);
        }

        return $delivery->refresh();
    }

    public function explain(string $deliveryId, string $accountId): array
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        return $this->matching->explain($delivery->campaign_id, $accountId);
    }

    private function neutralizeBlockedDelivery(FeedAdDelivery $delivery, string $accountId): FeedAdDelivery
    {
        DB::transaction(function () use ($delivery, $accountId): void {
            /** @var FeedAdDelivery $locked */
            $locked = FeedAdDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED], true)) {
                return;
            }

            $quotaWasConsumed = $locked->status === FeedAdDelivery::STATUS_STARTED;

            $this->envelope->releaseSlot($locked->campaign_envelope_consumption_id);

            if ($quotaWasConsumed) {
                try {
                    $this->quota->restore($accountId, max(1, (int) $locked->quota_units), "feed-quota-own-campaign:{$locked->id}");
                } catch (NoActiveSubscriptionException) {
                    // The economic block remains absolute even if the
                    // subscription expired between start and neutralization.
                }
            }

            $locked->update([
                'is_replay' => true,
                'gain_minor' => 0,
                'status' => FeedAdDelivery::STATUS_REPLAY,
                'ledger_transaction_id' => null,
            ]);
        });

        return $delivery->refresh();
    }

    private function findOwned(string $deliveryId, string $accountId): FeedAdDelivery
    {
        $delivery = FeedAdDelivery::query()->where('account_id', $accountId)->find($deliveryId);

        if ($delivery === null) {
            throw new FeedDeliveryNotFoundException($deliveryId);
        }

        return $delivery;
    }

    private function isExpired(FeedAdDelivery $delivery): bool
    {
        return $delivery->reserved_at
            ->addSeconds((int) config('campaigns.envelope_reservation_ttl_seconds'))
            ->isPast();
    }

    /**
     * @param  array{delivery: FeedAdDelivery, creative: mixed}  $result
     */
    private function hasIncompatibleVideoEconomics(array $result): bool
    {
        $delivery = $result['delivery'];

        if ($delivery->is_replay) {
            return false;
        }

        $creative = $result['creative'] ?? null;
        if (! is_array($creative) || ($creative['type'] ?? null) !== 'video') {
            return true;
        }

        $durationMs = (int) ($creative['duration_ms'] ?? 0);
        if ($durationMs <= 0) {
            $durationMs = ((int) ($creative['duration'] ?? 0)) * 1000;
        }
        if ($durationMs <= 0) {
            return true;
        }

        $unitMs = max(1, (int) config('campaigns.attention_unit_ms'));
        $expectedUnits = (int) ceil($durationMs / $unitMs);

        return (int) $delivery->required_duration_ms !== $durationMs
            || (int) $delivery->quota_units !== $expectedUnits
            || ! $delivery->requires_media_end;
    }

    private function present(FeedAdDelivery $delivery): array
    {
        $campaign = $this->campaigns->find($delivery->campaign_id);
        $brand = $campaign !== null ? $this->brands->find($campaign->organizationId, $campaign->brandId) : null;

        $creative = null;

        if ($campaign?->creativeAssetId !== null) {
            $asset = $this->creativeAssets->find($campaign->organizationId, $campaign->creativeAssetId);

            if ($asset !== null) {
                $creative = [
                    'url' => $asset->url,
                    'type' => $asset->type,
                    'duration' => $asset->duration,
                    'duration_ms' => $asset->durationMs,
                ];
            }
        }

        return [
            'delivery' => $delivery,
            'brand_name' => $brand?->name,
            'objective_code' => $campaign?->objectiveCode,
            'cta_label' => Campaign::ctaFor($campaign?->objectiveCode),
            'creative' => $creative,
        ];
    }
}
