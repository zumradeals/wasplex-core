<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Campaigns\Application\Services\CampaignEnvelopeExhaustedException;
use App\Modules\Campaigns\Application\Services\CampaignNotAvailableForDeliveryException;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Subscriptions\Application\Services\NoActiveSubscriptionException;
use App\Modules\Subscriptions\Application\Services\QuotaExhaustedException;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * docs/07 §15/§20-22, docs/08 §21-28 : chaîne « réservation → session
 * d'attention → preuve → décision » réduite au périmètre réel de ce dépôt
 * (docs/chantiers/P009-CHANTIER.md §2). Le client ne crédite jamais
 * lui-même — chaque transition est décidée et enregistrée ici.
 */
final class AttentionService
{
    private const MAX_COMPOSITION_ATTEMPTS = 5;

    public function __construct(
        private readonly FeedCompositionService $composition,
        private readonly CampaignEnvelopeContract $envelope,
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly BrandDirectoryContract $brands,
        private readonly MatchingContract $matching,
        private readonly SubscriptionQuotaContract $quota,
        private readonly AdvertiserWalletReservationContract $advertiserReservations,
        private readonly UserWalletContract $userWallet,
    ) {}

    /**
     * @return array{delivery: FeedAdDelivery, brand_name: ?string, objective_code: ?string, cta_label: ?string}|null
     */
    public function next(string $feedSessionId, string $accountId): ?array
    {
        $pending = FeedAdDelivery::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED])
            ->orderByDesc('created_at')
            ->first();

        if ($pending !== null) {
            return $this->present($pending);
        }

        try {
            if ($this->quota->currentCounter($accountId)->remaining() <= 0) {
                return null;
            }
        } catch (NoActiveSubscriptionException) {
            return null;
        }

        $excluded = [];

        for ($attempt = 0; $attempt < self::MAX_COMPOSITION_ATTEMPTS; $attempt++) {
            $candidate = $this->composition->nextCandidate($accountId, $excluded);

            if ($candidate === null) {
                return null;
            }

            $campaign = $this->campaigns->find($candidate->campaignId);

            if ($campaign === null) {
                $excluded[] = $candidate->campaignId;

                continue;
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

            $delivery = FeedAdDelivery::query()->create([
                'feed_session_id' => $feedSessionId,
                'account_id' => $accountId,
                'campaign_id' => $candidate->campaignId,
                'organization_id' => $slot->organizationId,
                'campaign_envelope_consumption_id' => $slot->consumptionId,
                'economic_class' => $candidate->economicClass,
                'gain_minor' => $slot->gainMinor,
                'required_duration_ms' => $slot->requiredDurationMs,
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

        if ($delivery->status !== FeedAdDelivery::STATUS_STARTED) {
            if ($delivery->status !== FeedAdDelivery::STATUS_RESERVED || $this->isExpired($delivery)) {
                throw new DeliveryNotStartableException;
            }

            try {
                $this->quota->consume($accountId, 1, "feed-quota:{$delivery->id}");
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

    /**
     * docs/chantiers/P010-CHANTIER.md §2.3/§5 : deux signaux calculables
     * avec les données déjà collectées, faute de seuils chiffrés dans les
     * spécifications qualitatives (docs/16 §18). Un signal n'entraîne pas
     * lui-même de mise en attente — seul l'atteinte du seuil cumulé dans
     * complete() décide (docs/16 §20 : preuve douteuse -> hold -> revue).
     */
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

        DB::transaction(function () use ($delivery, $accountId): void {
            $this->envelope->captureSlot($delivery->campaign_envelope_consumption_id);

            $destination = $this->userWallet->availableAccountReference($accountId);

            $ledgerTransactionId = $this->advertiserReservations->capture(
                $delivery->organization_id,
                $delivery->campaign_id,
                $delivery->gain_minor,
                $destination,
                "feed-capture:{$delivery->id}",
            );

            $delivery->update([
                'status' => FeedAdDelivery::STATUS_COMPLETED,
                'completed_at' => Carbon::now('UTC'),
                'ledger_transaction_id' => $ledgerTransactionId,
            ]);
        });

        return [
            'delivery' => $delivery->refresh(),
            'gain_minor' => $delivery->gain_minor,
            'balance_minor' => $this->userWallet->balanceMinor($accountId),
        ];
    }

    public function abandon(string $deliveryId, string $accountId): FeedAdDelivery
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        if (in_array($delivery->status, [FeedAdDelivery::STATUS_RESERVED, FeedAdDelivery::STATUS_STARTED], true)) {
            $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);
            $delivery->update(['status' => FeedAdDelivery::STATUS_ABANDONED]);
        }

        return $delivery->refresh();
    }

    public function explain(string $deliveryId, string $accountId): array
    {
        $delivery = $this->findOwned($deliveryId, $accountId);

        return $this->matching->explain($delivery->campaign_id, $accountId);
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

    private function present(FeedAdDelivery $delivery): array
    {
        $campaign = $this->campaigns->find($delivery->campaign_id);
        $brand = $campaign !== null ? $this->brands->find($campaign->organizationId, $campaign->brandId) : null;

        return [
            'delivery' => $delivery,
            'brand_name' => $brand?->name,
            'objective_code' => $campaign?->objectiveCode,
            'cta_label' => Campaign::ctaFor($campaign?->objectiveCode),
        ];
    }
}
