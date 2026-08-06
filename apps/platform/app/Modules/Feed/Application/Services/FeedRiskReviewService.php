<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revue administrative des retenues antifraude (docs/16 §20/§24,
 * docs/chantiers/P010-CHANTIER.md §5) — séparé d'AttentionService comme
 * CampaignReviewService l'est de CampaignService (P007) : le parcours
 * utilisateur en direct et la décision administrative sont deux
 * responsabilités distinctes.
 */
final class FeedRiskReviewService
{
    public function __construct(
        private readonly CampaignEnvelopeContract $envelope,
        private readonly AdvertiserWalletReservationContract $advertiserReservations,
        private readonly UserWalletContract $userWallet,
    ) {}

    /**
     * @return Collection<int, FeedAdDeliveryHold>
     */
    public function listQueue(): Collection
    {
        return FeedAdDeliveryHold::query()
            ->whereIn('status', [FeedAdDeliveryHold::STATUS_CREATED, FeedAdDeliveryHold::STATUS_UNDER_REVIEW])
            ->with('delivery')
            ->orderBy('opened_at')
            ->get();
    }

    public function release(string $holdId, string $adminAccountId, ?string $note = null): FeedAdDeliveryHold
    {
        $hold = $this->findResolvable($holdId);
        $delivery = $hold->delivery;

        DB::transaction(function () use ($hold, $delivery, $adminAccountId, $note): void {
            $this->envelope->captureSlot($delivery->campaign_envelope_consumption_id);

            $destination = $this->userWallet->availableAccountReference($delivery->account_id);

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

            $hold->update([
                'status' => FeedAdDeliveryHold::STATUS_RELEASED,
                'resolved_at' => Carbon::now('UTC'),
                'resolved_by' => $adminAccountId,
                'resolution_note' => $note,
            ]);
        });

        $this->userWallet->notifyBalanceChanged(
            $delivery->account_id,
            $delivery->gain_minor,
            'feed.risk_review',
            'credit',
            $delivery->ledger_transaction_id,
        );

        return $hold->refresh();
    }

    public function reject(string $holdId, string $adminAccountId, ?string $note = null): FeedAdDeliveryHold
    {
        $hold = $this->findResolvable($holdId);
        $delivery = $hold->delivery;

        DB::transaction(function () use ($hold, $delivery, $adminAccountId, $note): void {
            $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);

            $delivery->update(['status' => FeedAdDelivery::STATUS_REJECTED]);

            $hold->update([
                'status' => FeedAdDeliveryHold::STATUS_REJECTED,
                'resolved_at' => Carbon::now('UTC'),
                'resolved_by' => $adminAccountId,
                'resolution_note' => $note,
            ]);
        });

        return $hold->refresh();
    }

    private function findResolvable(string $holdId): FeedAdDeliveryHold
    {
        $hold = FeedAdDeliveryHold::query()->find($holdId);

        if ($hold === null) {
            throw new FeedHoldNotFoundException($holdId);
        }

        if (! in_array($hold->status, [FeedAdDeliveryHold::STATUS_CREATED, FeedAdDeliveryHold::STATUS_UNDER_REVIEW], true)) {
            throw new HoldNotResolvableException;
        }

        return $hold;
    }
}
