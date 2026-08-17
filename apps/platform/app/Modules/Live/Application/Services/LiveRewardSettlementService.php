<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardHold;
use App\Shared\Http\AppException;
use Illuminate\Support\Facades\DB;

final class LiveRewardSettlementService
{
    public function __construct(private readonly AdvertiserLiveBudgetReservationContract $budgets) {}

    /** @return array<string, int|bool|string> */
    public function settleLive(LiveEvent $live, ?string $actorAccountId = null): array
    {
        return DB::transaction(function () use ($live, $actorAccountId): array {
            $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->lockForUpdate()->first();
            if ($campaign === null) {
                return $this->report($live);
            }

            $reservation = $campaign->budgetReservations()->latest('reserved_at')->lockForUpdate()->first();
            if ($reservation === null) {
                return $this->report($live);
            }

            $capturedGross = (int) LiveRewardAttentionBlock::query()
                ->where('live_reward_campaign_id', $campaign->id)
                ->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED)
                ->sum('gross_amount_minor');
            $heldGross = (int) LiveRewardHold::query()
                ->where('live_reward_campaign_id', $campaign->id)
                ->where('status', LiveRewardHold::STATUS_PENDING)
                ->sum('gross_amount_minor');

            $alreadyReleased = (int) $reservation->released_amount_minor;
            if ($reservation->status === LiveRewardBudgetReservation::STATUS_RELEASED && $alreadyReleased === 0) {
                $alreadyReleased = max(0, (int) $reservation->amount_minor - $capturedGross);
            }

            $committed = $capturedGross + $heldGross + $alreadyReleased;
            if ($committed > (int) $reservation->amount_minor) {
                throw new AppException(
                    'LIVE_REWARD_BUDGET_INCONSISTENT',
                    'La consommation récompensée dépasse le budget Live réservé.',
                    [],
                    409,
                );
            }

            $releasable = max(0, (int) $reservation->amount_minor - $committed);
            if ($releasable > 0 && $reservation->status === LiveRewardBudgetReservation::STATUS_RESERVED) {
                $newReleasedTotal = $alreadyReleased + $releasable;
                $ledgerTransactionId = $this->budgets->release(
                    $campaign->advertiser_organization_id,
                    $live->id,
                    $releasable,
                    "live-budget-settlement:{$reservation->id}:{$newReleasedTotal}",
                );
                $reservation->released_amount_minor = $newReleasedTotal;
                $this->audit($live, $actorAccountId, 'LiveRewardBudgetPartiallyReleased', [
                    'reservation_id' => $reservation->id,
                    'released_minor' => $releasable,
                    'released_total_minor' => $newReleasedTotal,
                    'pending_hold_gross_minor' => $heldGross,
                    'ledger_transaction_id' => $ledgerTransactionId,
                ]);
            }

            $reservation->refresh();
            $fullyAllocated = $capturedGross + (int) $reservation->released_amount_minor === (int) $reservation->amount_minor;

            if ($heldGross === 0 && $fullyAllocated && $reservation->status !== LiveRewardBudgetReservation::STATUS_RELEASED) {
                $reservation->status = LiveRewardBudgetReservation::STATUS_RELEASED;
                $reservation->released_at = now();
                $this->audit($live, $actorAccountId, 'LiveRewardBudgetSettled', [
                    'reservation_id' => $reservation->id,
                    'captured_gross_minor' => $capturedGross,
                    'released_minor' => (int) $reservation->released_amount_minor,
                ]);
            }

            $reservation->save();

            return $this->report($live);
        });
    }

    /** @return array<string, int|bool|string> */
    public function report(LiveEvent $live): array
    {
        $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->first();
        if ($campaign === null) {
            return [
                'settled' => false,
                'settlement_state' => 'unfunded',
                'funded_blocks' => 0,
                'captured_blocks' => 0,
                'held_blocks' => 0,
                'rejected_blocks' => 0,
                'rewarded_viewers' => 0,
                'member_rewards_minor' => 0,
                'wasplex_revenue_minor' => 0,
                'gross_consumed_minor' => 0,
                'pending_review_minor' => 0,
                'pending_review_gross_minor' => 0,
                'remaining_reserved_minor' => 0,
                'released_minor' => 0,
            ];
        }

        $reservation = $campaign->budgetReservations()->latest('reserved_at')->first();
        $quote = $reservation?->quote ?? $campaign->quotes()->latest('quoted_at')->first();
        $captured = LiveRewardAttentionBlock::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED);

        $capturedBlocks = (clone $captured)->count();
        $memberRewards = (int) (clone $captured)->sum('reward_minor');
        $grossConsumed = (int) (clone $captured)->sum('gross_amount_minor');
        $rewardedViewers = (clone $captured)->distinct()->count('account_id');
        $heldBlocks = LiveRewardAttentionBlock::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardAttentionBlock::STATUS_HELD)
            ->count();
        $rejectedBlocks = LiveRewardAttentionBlock::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardAttentionBlock::STATUS_REJECTED)
            ->count();
        $pendingReward = (int) LiveRewardHold::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardHold::STATUS_PENDING)
            ->sum('reward_minor');
        $pendingGross = (int) LiveRewardHold::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardHold::STATUS_PENDING)
            ->sum('gross_amount_minor');

        $reservedAmount = (int) ($reservation?->amount_minor ?? 0);
        $released = (int) ($reservation?->released_amount_minor ?? 0);
        if ($reservation?->status === LiveRewardBudgetReservation::STATUS_RELEASED && $released === 0) {
            $released = max(0, $reservedAmount - $grossConsumed);
        }

        $settled = $reservation?->status === LiveRewardBudgetReservation::STATUS_RELEASED;
        $remainingReserved = max(0, $reservedAmount - $grossConsumed - $released);

        return [
            'settled' => $settled,
            'settlement_state' => $settled ? 'settled' : ($pendingGross > 0 ? 'pending_review' : 'reserved'),
            'funded_blocks' => (int) ($quote?->funded_blocks ?? 0),
            'captured_blocks' => $capturedBlocks,
            'held_blocks' => $heldBlocks,
            'rejected_blocks' => $rejectedBlocks,
            'rewarded_viewers' => $rewardedViewers,
            'member_rewards_minor' => $memberRewards,
            'wasplex_revenue_minor' => $memberRewards,
            'gross_consumed_minor' => $grossConsumed,
            'pending_review_minor' => $pendingReward,
            'pending_review_gross_minor' => $pendingGross,
            'remaining_reserved_minor' => $remainingReserved,
            'released_minor' => $released,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function audit(LiveEvent $live, ?string $actorAccountId, string $eventType, array $metadata = []): void
    {
        LiveAuditEvent::query()->create([
            'live_id' => $live->id,
            'actor_account_id' => $actorAccountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
