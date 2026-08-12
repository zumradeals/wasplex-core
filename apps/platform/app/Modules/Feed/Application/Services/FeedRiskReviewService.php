<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Feed\Infrastructure\Models\FeedCampaignReward;
use App\Modules\Subscriptions\Application\Services\NoActiveSubscriptionException;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Revue administrative des retenues antifraude. La libération d'un hold
 * respecte la même unicité membre + campagne que le parcours direct : une
 * décision admin ne peut donc jamais créer un second paiement.
 */
final class FeedRiskReviewService
{
    public function __construct(
        private readonly CampaignEnvelopeContract $envelope,
        private readonly AdvertiserWalletReservationContract $advertiserReservations,
        private readonly UserWalletContract $userWallet,
        private readonly SubscriptionQuotaContract $quota,
        private readonly FeedRewardEligibilityService $rewardEligibility,
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

        $rewarded = DB::transaction(function () use ($hold, $delivery, $adminAccountId, $note): bool {
            $now = Carbon::now('UTC');

            // An admin review can never bypass the same economic-integrity
            // rule enforced by Feed composition and direct completion. This
            // also safely neutralizes an old hold if the account is an
            // active member of the advertiser organization when reviewed.
            if ($this->rewardEligibility->isBlockedForOrganization($delivery->account_id, $delivery->organization_id)) {
                $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);

                try {
                    $this->quota->restore($delivery->account_id, max(1, (int) $delivery->quota_units), "feed-quota-own-campaign:{$delivery->id}");
                } catch (NoActiveSubscriptionException) {
                    // No payment may be created even if the subscription
                    // expired while the hold was awaiting manual review.
                }

                $delivery->update([
                    'is_replay' => true,
                    'gain_minor' => 0,
                    'status' => FeedAdDelivery::STATUS_REJECTED,
                    'ledger_transaction_id' => null,
                ]);

                $hold->update([
                    'status' => FeedAdDeliveryHold::STATUS_REJECTED,
                    'resolved_at' => $now,
                    'resolved_by' => $adminAccountId,
                    'resolution_note' => trim(($note !== null ? $note.' — ' : '').'Aucune récompense : le membre appartient à l’organisation annonceur de cette campagne.'),
                ]);

                return false;
            }

            $rewardId = (string) Str::ulid();

            $claimed = FeedCampaignReward::query()->insertOrIgnore([
                'id' => $rewardId,
                'account_id' => $delivery->account_id,
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
                $this->envelope->releaseSlot($delivery->campaign_envelope_consumption_id);

                try {
                    $this->quota->restore($delivery->account_id, max(1, (int) $delivery->quota_units), "feed-quota-duplicate:{$delivery->id}");
                } catch (NoActiveSubscriptionException) {
                    // Le verrou économique reste prioritaire si l'abonnement
                    // a expiré pendant la revue manuelle.
                }

                $delivery->update([
                    'is_replay' => true,
                    'gain_minor' => 0,
                    'status' => FeedAdDelivery::STATUS_COMPLETED,
                    'completed_at' => $now,
                    'ledger_transaction_id' => null,
                ]);

                $hold->update([
                    'status' => FeedAdDeliveryHold::STATUS_RELEASED,
                    'resolved_at' => $now,
                    'resolved_by' => $adminAccountId,
                    'resolution_note' => trim(($note !== null ? $note.' — ' : '').'Récompense déjà versée pour cette campagne.'),
                ]);

                return false;
            }

            $this->envelope->captureSlot($delivery->campaign_envelope_consumption_id);

            $destination = $this->userWallet->availableAccountReference($delivery->account_id);

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

            $hold->update([
                'status' => FeedAdDeliveryHold::STATUS_RELEASED,
                'resolved_at' => $now,
                'resolved_by' => $adminAccountId,
                'resolution_note' => $note,
            ]);

            return true;
        });

        if ($rewarded) {
            $this->userWallet->notifyBalanceChanged(
                $delivery->account_id,
                $delivery->gain_minor,
                'feed.risk_review',
                'credit',
                $delivery->ledger_transaction_id,
            );
        }

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
