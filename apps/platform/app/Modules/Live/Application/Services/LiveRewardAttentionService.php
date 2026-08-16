<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionState;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use App\Shared\Http\AppException;
use Illuminate\Support\Facades\DB;

final class LiveRewardAttentionService
{
    public function __construct(
        private readonly AdvertiserLiveBudgetReservationContract $budgets,
        private readonly UserWalletContract $userWallet,
    ) {}

    /**
     * A heartbeat never trusts a client duration. The server only receives
     * boolean presence signals and derives qualified time from its own clock.
     *
     * @return array<string, mixed>
     */
    public function heartbeat(
        LiveEvent $live,
        Account $viewer,
        bool $visible,
        bool $mediaConnected,
    ): array {
        $capturedRewards = [];

        $state = DB::transaction(function () use (
            $live,
            $viewer,
            $visible,
            $mediaConnected,
            &$capturedRewards,
        ): ?LiveRewardAttentionState {
            if ($live->type !== LiveEvent::TYPE_SPONSORED) {
                return null;
            }

            /** @var LiveRewardCampaign|null $campaign */
            $campaign = LiveRewardCampaign::query()
                ->where('live_id', $live->id)
                ->lockForUpdate()
                ->first();
            if ($campaign === null || $campaign->status !== LiveRewardCampaign::STATUS_FUNDS_RESERVED) {
                return null;
            }

            $reservation = $campaign->budgetReservations()
                ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
                ->latest('reserved_at')
                ->lockForUpdate()
                ->first();
            $quote = $reservation?->quote;
            if ($reservation === null || $quote === null || $quote->reward_per_block_minor < 1) {
                return null;
            }

            $seat = LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveRewardSeat::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if ($seat === null) {
                $this->resetState($live, $viewer->id);

                return null;
            }

            $viewerSession = LiveViewerSession::query()
                ->whereKey($seat->viewer_session_id)
                ->where('account_id', $viewer->id)
                ->where('live_id', $live->id)
                ->lockForUpdate()
                ->first();
            if ($viewerSession === null
                || ! in_array($viewerSession->status, [
                    LiveViewerSession::STATUS_WATCHING,
                    LiveViewerSession::STATUS_PAUSED,
                ], true)) {
                $this->resetState($live, $viewer->id);

                return null;
            }

            $state = LiveRewardAttentionState::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                $state = LiveRewardAttentionState::query()->create([
                    'live_reward_campaign_id' => $campaign->id,
                    'live_id' => $live->id,
                    'account_id' => $viewer->id,
                    'live_reward_seat_id' => $seat->id,
                    'viewer_session_id' => $viewerSession->id,
                    'validated_blocks' => 0,
                    'current_block_ms' => 0,
                    'last_heartbeat_qualified' => false,
                ]);
            }

            $now = now();
            $state->live_reward_seat_id = $seat->id;
            $state->viewer_session_id = $viewerSession->id;

            if ($state->validated_blocks >= $quote->max_blocks_per_viewer) {
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = false;
                $state->save();

                return $state->refresh();
            }

            // Host pause is neutral: preserve partial progress but do not count
            // paused time. The browser keeps heartbeating, so resume starts cleanly.
            if ($live->status === LiveEvent::STATUS_PAUSED) {
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = false;
                $state->save();

                return $state->refresh();
         }

            if ($live->status !== LiveEvent::STATUS_LIVE) {
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = false;
                $state->save();

                return $state->refresh();
            }

            // A hidden tab or disconnected media breaks a continuous attention
            // block. Incomplete time is never transformed into value.
            if (! $visible || ! $mediaConnected) {
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = false;
                $state->save();

                return $state->refresh();
            }

            if ($state->last_heartbeat_at === null || ! $state->last_heartbeat_qualified) {
                $state->last_heartbeat_at = $now;
                $state->last_qualified_at = $now;
                $state->last_heartbeat_qualified = true;
                $state->save();

                return $state->refresh();
            }

            $elapsedMs = (int) $state->last_heartbeat_at->diffInMilliseconds($now, true);
            $minimumMs = max(100, (int) config('live.reward_heartbeat_min_interval_ms'));
            $maximumGapMs = max($minimumMs, (int) config('live.reward_heartbeat_max_gap_ms'));

            if ($elapsedMs < $minimumMs) {
                $state->risk_signal_count++;
                $state->last_risk_signal_code = 'heartbeat_rate_abuse';
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = true;
                $state->save();

                return $state->refresh();
            }

            if ($elapsedMs > $maximumGapMs) {
                $state->risk_signal_count++;
                $state->last_risk_signal_code = 'heartbeat_gap';
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = true;
                $state->save();

                return $state->refresh();
            }

            $state->current_block_ms += $elapsedMs;
            $state->last_heartbeat_at = $now;
            $state->last_qualified_at = $now;
            $state->last_heartbeat_qualified = true;

            $requiredMs = max(1, (int) $quote->block_duration_seconds * 1000);

            while ($state->current_block_ms >= $requiredMs
                && $state->validated_blocks < $quote->max_blocks_per_viewer) {
                $capturedGlobally = LiveRewardAttentionBlock::query()
                    ->where('live_reward_campaign_id', $campaign->id)
                    ->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED)
                    ->count();

                if ($capturedGlobally >= $quote->funded_blocks) {
                    $state->current_block_ms = 0;
                    break;
                }

                $blockIndex = $state->validated_blocks + 1;
                $block = LiveRewardAttentionBlock::query()->create([
                    'live_reward_campaign_id' => $campaign->id,
                    'live_reward_quote_id' => $quote->id,
                    'live_id' => $live->id,
                    'account_id' => $viewer->id,
                    'live_reward_seat_id' => $seat->id,
                    'viewer_session_id' => $viewerSession->id,
                    'block_index' => $blockIndex,
                    'attention_ms' => $requiredMs,
                    'reward_minor' => $quote->reward_per_block_minor,
                    'gross_amount_minor' => $quote->reward_per_block_minor * 2,
                    'status' => LiveRewardAttentionBlock::STATUS_CAPTURED,
                ]);

                $ledgerTransactionId = $this->budgets->captureReward(
                    $campaign->advertiser_organization_id,
                    $live->id,
                    $quote->reward_per_block_minor,
                    $this->userWallet->availableAccountReference($viewer->id),
                    "live-reward-block:{$live->id}:{$viewer->id}:{$blockIndex}",
                );

                $block->update([
                    'ledger_transaction_id' => $ledgerTransactionId,
                    'captured_at' => $now,
                ]);

                $state->validated_blocks = $blockIndex;
                $state->current_block_ms -= $requiredMs;
                $capturedRewards[] = [
                    'amount_minor' => (int) $quote->reward_per_block_minor,
                    'ledger_transaction_id' => $ledgerTransactionId,
                ];

                $this->audit($live, $viewer->id, 'LiveRewardBlockCaptured', [
                    'block_id' => $block->id,
                    'block_index' => $blockIndex,
                    'reward_minor' => (int) $quote->reward_per_block_minor,
                    'ledger_transaction_id' => $ledgerTransactionId,
                ]);
            }

            if ($state->validated_blocks >= $quote->max_blocks_per_viewer) {
                $state->current_block_ms = 0;
            }

            $state->save();

            return $state->refresh();
        });

        foreach ($capturedRewards as $captured) {
            $this->userWallet->notifyBalanceChanged(
                $viewer->id,
                $captured['amount_minor'],
                'live.attention',
                'credit',
                $captured['ledger_transaction_id'],
            );
        }

        return $this->present($live, $viewer->id, $state);
    }

    /**
     * @return array<string, mixed>
     */
    public function stateForAccount(LiveEvent $live, Account $viewer): array
    {
        $state = LiveRewardAttentionState::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->first();

        return $this->present($live, $viewer->id, $state);
    }

    public function interrupt(LiveEvent $live, Account $viewer, string $reason = 'viewer_interrupted'): void
    {
        DB::transaction(function () use ($live, $viewer, $reason): void {
            $state = LiveRewardAttentionState::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                return;
            }

            $state->update([
                'current_block_ms' => 0,
                'last_heartbeat_at' => now(),
                'last_heartbeat_qualified' => false,
                'last_risk_signal_code' => $reason,
            ]);
        });
    }

    /**
     * Finalizes the reserved Live budget. Qualified blocks are already captured
     * append-only; every remaining WP returns to the advertiser exactly once.
     *
     * @return array<string, int|bool>
     */
    public function settleLive(LiveEvent $live, ?string $actorAccountId = null): array
    {
        return DB::transaction(function () use ($live, $actorAccountId): array {
            $campaign = LiveRewardCampaign::query()
                ->where('live_id', $live->id)
                ->lockForUpdate()
                ->first();

            if ($campaign === null) {
                return $this->report($live);
            }

            $reservation = $campaign->budgetReservations()
                ->latest('reserved_at')
                ->lockForUpdate()
                ->first();

            if ($reservation === null || $reservation->status !== LiveRewardBudgetReservation::STATUS_RESERVED) {
                return $this->report($live);
            }

            $grossCaptured = (int) LiveRewardAttentionBlock::query()
                ->where('live_reward_campaign_id', $campaign->id)
                ->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED)
                ->sum('gross_amount_minor');

            if ($grossCaptured > $reservation->amount_minor) {
                throw new AppException(
                    'LIVE_REWARD_BUDGET_INCONSISTENT',
                    'La consommation récompensée dépasse le budget Live réservé.',
                    [],
                    409,
                );
            }

            $remaining = $reservation->amount_minor - $grossCaptured;
            $releaseTransactionId = null;

            if ($remaining > 0) {
                $releaseTransactionId = $this->budgets->release(
                    $campaign->advertiser_organization_id,
                    $live->id,
                    $remaining,
                    "live-budget-settlement:{$reservation->id}",
                );
            }

            $reservation->update([
                'status' => LiveRewardBudgetReservation::STATUS_RELEASED,
                'released_at' => now(),
            ]);

            $this->audit($live, $actorAccountId, 'LiveRewardBudgetSettled', [
                'reservation_id' => $reservation->id,
                'captured_gross_minor' => $grossCaptured,
                'released_minor' => $remaining,
                'release_ledger_transaction_id' => $releaseTransactionId,
            ]);

            return $this->report($live);
        });
    }

    /**
     * Aggregate only: never exposes rewarded member identities to the Studio.
     *
     * @return array<string, int|bool>
     */
    public function report(LiveEvent $live): array
    {
        $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->first();

        if ($campaign === null) {
            return [
                'settled' => false,
                'funded_blocks' => 0,
                'captured_blocks' => 0,
                'rewarded_viewers' => 0,
                'member_rewards_minor' => 0,
                'wasplex_revenue_minor' => 0,
                'gross_consumed_minor' => 0,
                'remaining_reserved_minor' => 0,
                'released_minor' => 0,
            ];
        }

        $reservation = $campaign->budgetReservations()->latest('reserved_at')->first();
        $quote = $reservation?->quote ?? $campaign->quotes()->latest('quoted_at')->first();

        $blocks = LiveRewardAttentionBlock::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED);

        $capturedBlocks = (clone $blocks)->count();
        $memberRewards = (int) (clone $blocks)->sum('reward_minor');
        $grossConsumed = (int) (clone $blocks)->sum('gross_amount_minor');
        $rewardedViewers = (clone $blocks)->distinct()->count('account_id');

        $reservedAmount = (int) ($reservation?->amount_minor ?? 0);
        $settled = $reservation?->status === LiveRewardBudgetReservation::STATUS_RELEASED;
        $remainingReserved = $settled ? 0 : max(0, $reservedAmount - $grossConsumed);
        $released = $settled ? max(0, $reservedAmount - $grossConsumed) : 0;

        return [
            'settled' => $settled,
            'funded_blocks' => (int) ($quote?->funded_blocks ?? 0),
            'captured_blocks' => $capturedBlocks,
            'rewarded_viewers' => $rewardedViewers,
            'member_rewards_minor' => $memberRewards,
            'wasplex_revenue_minor' => $memberRewards,
            'gross_consumed_minor' => $grossConsumed,
            'remaining_reserved_minor' => $remainingReserved,
            'released_minor' => $released,
        ];
    }

    private function resetState(LiveEvent $live, string $accountId): void
    {
        LiveRewardAttentionState::query()
            ->where('live_id', $live->id)
            ->where('account_id', $accountId)
            ->update([
                'current_block_ms' => 0,
                'last_heartbeat_at' => now(),
                'last_heartbeat_qualified' => false,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(
        LiveEvent $live,
        string $accountId,
        ?LiveRewardAttentionState $state,
    ): array {
        $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->first();
        $reservation = $campaign?->budgetReservations()->latest('reserved_at')->first();
        $quote = $reservation?->quote;
        $seatActive = LiveRewardSeat::query()
            ->where('live_id', $live->id)
            ->where('account_id', $accountId)
            ->where('status', LiveRewardSeat::STATUS_ACTIVE)
            ->exists();

        $requiredMs = max(1, (int) ($quote?->block_duration_seconds ?? 0) * 1000);
        $currentMs = (int) ($state?->current_block_ms ?? 0);
        $validatedBlocks = (int) ($state?->validated_blocks ?? 0);
        $maxBlocks = (int) ($quote?->max_blocks_per_viewer ?? 0);
        $rewardPerBlock = (int) ($quote?->reward_per_block_minor ?? 0);
        $globalCapturedBlocks = $campaign === null ? 0 : LiveRewardAttentionBlock::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED)
            ->count();
        $fundedBlocks = (int) ($quote?->funded_blocks ?? 0);

        $status = 'inactive';
        if ($seatActive && $quote !== null) {
            if ($validatedBlocks >= $maxBlocks && $maxBlocks > 0) {
                $status = 'completed';
            } elseif ($fundedBlocks > 0 && $globalCapturedBlocks >= $fundedBlocks) {
                $status = 'funding_exhausted';
            } elseif ($live->status === LiveEvent::STATUS_PAUSED) {
                $status = 'paused';
            } elseif ($live->status === LiveEvent::STATUS_LIVE) {
                $status = 'tracking';
            }
        }

        return [
            'status' => $status,
            'validated_blocks' => $validatedBlocks,
            'max_blocks' => $maxBlocks,
            'current_block_ms' => $currentMs,
            'block_duration_ms' => $requiredMs,
            'progress_percent' => $requiredMs > 0
                ? (int) min(100, intdiv($currentMs * 100, $requiredMs))
                : 0,
            'reward_per_block_minor' => $rewardPerBlock,
            'earned_minor' => $validatedBlocks * $rewardPerBlock,
            'max_reward_minor' => $maxBlocks * $rewardPerBlock,
            'funded_blocks' => $fundedBlocks,
            'captured_blocks' => $globalCapturedBlocks,
            'balance_minor' => $this->userWallet->balanceMinor($accountId),
        ];
    }

    private function audit(
        LiveEvent $live,
        ?string $actorAccountId,
        string $eventType,
        array $metadata = [],
    ): void {
        LiveAuditEvent::query()->create([
            'live_id' => $live->id,
            'actor_account_id' => $actorAccountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
