<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionState;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardRiskEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use Illuminate\Support\Facades\DB;

final class LiveRewardAttentionService
{
    public function __construct(
        private readonly AdvertiserLiveBudgetReservationContract $budgets,
        private readonly UserWalletContract $userWallet,
        private readonly LiveRewardRiskService $risk,
        private readonly LiveRewardHoldService $holds,
        private readonly LiveRewardSettlementService $settlement,
    ) {}

    /** @return array<string, mixed> */
    public function heartbeat(
        LiveEvent $live,
        Account $viewer,
        bool $visible,
        bool $mediaConnected,
        ?AccountSession $accountSession = null,
    ): array {
        $capturedRewards = [];

        $state = DB::transaction(function () use ($live, $viewer, $visible, $mediaConnected, $accountSession, &$capturedRewards): ?LiveRewardAttentionState {
            if ($live->type !== LiveEvent::TYPE_SPONSORED) {
                return null;
            }

            $campaign = LiveRewardCampaign::query()->where('live_id', $live->id)->lockForUpdate()->first();
            if ($campaign === null || $campaign->status !== LiveRewardCampaign::STATUS_FUNDS_RESERVED) {
                return null;
            }

            $reservation = $campaign->budgetReservations()
                ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
                ->latest('reserved_at')
                ->lockForUpdate()
                ->first();
            $quote = $reservation?->quote;
            if ($reservation === null || $quote === null || (int) $quote->reward_per_block_minor < 1) {
                return null;
            }

            $seat = LiveRewardSeat::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveRewardSeat::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if ($seat === null) {
                $this->resetState($live, (string) $viewer->id);
                return null;
            }

            $viewerSession = LiveViewerSession::query()
                ->whereKey($seat->viewer_session_id)
                ->where('account_id', $viewer->id)
                ->where('live_id', $live->id)
                ->lockForUpdate()
                ->first();
            if ($viewerSession === null || ! in_array($viewerSession->status, [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED], true)) {
                $this->resetState($live, (string) $viewer->id);
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

            $state->live_reward_seat_id = $seat->id;
            $state->viewer_session_id = $viewerSession->id;

            if ($this->risk->isSelfRewardBlocked($live, (string) $viewer->id)) {
                $this->risk->selfRewardAttempt($live, $campaign, $state, $accountSession);
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = now();
                $state->last_heartbeat_qualified = false;
                $state->risk_hold_required = false;
                $state->save();
                return $state->refresh();
            }

            $this->risk->bindSession($live, $campaign, $state, $accountSession);
            $state->refresh();

            if (! $this->risk->rewardsEnabled()) {
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = now();
                $state->last_heartbeat_qualified = false;
                $state->save();
                return $state->refresh();
            }

            $now = now();
            if ((int) $state->validated_blocks >= (int) $quote->max_blocks_per_viewer) {
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = false;
                $state->save();
                return $state->refresh();
            }

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
                $this->risk->timingSignal($live, $campaign, $state, $accountSession, 'heartbeat_rate_abuse', $elapsedMs);
                $state->refresh();
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = true;
                $state->save();
                return $state->refresh();
            }

            if ($elapsedMs > $maximumGapMs) {
                $this->risk->timingSignal($live, $campaign, $state, $accountSession, 'heartbeat_gap', $elapsedMs);
                $state->refresh();
                $state->current_block_ms = 0;
                $state->last_heartbeat_at = $now;
                $state->last_heartbeat_qualified = true;
                $state->save();
                return $state->refresh();
            }

            $state->current_block_ms = (int) $state->current_block_ms + $elapsedMs;
            $state->last_heartbeat_at = $now;
            $state->last_qualified_at = $now;
            $state->last_heartbeat_qualified = true;
            $requiredMs = max(1, (int) $quote->block_duration_seconds * 1000);

            while ((int) $state->current_block_ms >= $requiredMs && (int) $state->validated_blocks < (int) $quote->max_blocks_per_viewer) {
                $committedGlobally = LiveRewardAttentionBlock::query()
                    ->where('live_reward_campaign_id', $campaign->id)
                    ->whereIn('status', [LiveRewardAttentionBlock::STATUS_CAPTURED, LiveRewardAttentionBlock::STATUS_HELD])
                    ->count();

                if ($committedGlobally >= (int) $quote->funded_blocks) {
                    $state->current_block_ms = 0;
                    break;
                }

                $blockIndex = (int) $state->validated_blocks + 1;
                $holdRequired = $this->risk->mode() === LiveRewardRiskService::MODE_ENFORCE && (bool) $state->risk_hold_required;
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
                    'gross_amount_minor' => (int) $quote->reward_per_block_minor * 2,
                    'risk_mode' => $this->risk->mode(),
                    'status' => $holdRequired ? LiveRewardAttentionBlock::STATUS_HELD : LiveRewardAttentionBlock::STATUS_CAPTURED,
                ]);

                if ($holdRequired) {
                    $recentRiskCodes = LiveRewardRiskEvent::query()
                        ->where('live_id', $live->id)
                        ->where('account_id', $viewer->id)
                        ->whereIn('severity', [LiveRewardRiskEvent::SEVERITY_HIGH, LiveRewardRiskEvent::SEVERITY_CRITICAL])
                        ->where('occurred_at', '>=', now()->subMinutes(15))
                        ->orderBy('occurred_at')
                        ->pluck('signal_code')
                        ->unique()
                        ->values()
                        ->all();

                    $this->holds->open($block, $recentRiskCodes ?: ['risk_signal_threshold'], [
                        'risk_signal_count' => (int) $state->risk_signal_count,
                        'attention_ms' => $requiredMs,
                    ]);
                    $this->audit($live, (string) $viewer->id, 'LiveRewardBlockHeld', [
                        'block_id' => $block->id,
                        'block_index' => $blockIndex,
                        'reward_minor' => (int) $quote->reward_per_block_minor,
                    ]);
                } else {
                    $ledgerTransactionId = $this->budgets->captureReward(
                        $campaign->advertiser_organization_id,
                        $live->id,
                        (int) $quote->reward_per_block_minor,
                        $this->userWallet->availableAccountReference((string) $viewer->id),
                        "live-reward-block:{$live->id}:{$viewer->id}:{$blockIndex}",
                    );
                    $block->update([
                        'ledger_transaction_id' => $ledgerTransactionId,
                        'captured_at' => $now,
                    ]);
                    $capturedRewards[] = [
                        'amount_minor' => (int) $quote->reward_per_block_minor,
                        'ledger_transaction_id' => $ledgerTransactionId,
                    ];
                    $this->audit($live, (string) $viewer->id, 'LiveRewardBlockCaptured', [
                        'block_id' => $block->id,
                        'block_index' => $blockIndex,
                        'reward_minor' => (int) $quote->reward_per_block_minor,
                        'ledger_transaction_id' => $ledgerTransactionId,
                    ]);
                }

                $state->validated_blocks = $blockIndex;
                $state->current_block_ms = (int) $state->current_block_ms - $requiredMs;
                $state->risk_hold_required = false;
            }

            if ((int) $state->validated_blocks >= (int) $quote->max_blocks_per_viewer) {
                $state->current_block_ms = 0;
            }
            $state->save();
            return $state->refresh();
        });

        foreach ($capturedRewards as $captured) {
            $this->userWallet->notifyBalanceChanged(
                (string) $viewer->id,
                $captured['amount_minor'],
                'live.attention',
                'credit',
                $captured['ledger_transaction_id'],
            );
        }

        return $this->present($live, (string) $viewer->id, $state);
    }

    /** @return array<string, mixed> */
    public function stateForAccount(LiveEvent $live, Account $viewer): array
    {
        $state = LiveRewardAttentionState::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->first();
        return $this->present($live, (string) $viewer->id, $state);
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

    /** @return array<string, int|bool|string> */
    public function settleLive(LiveEvent $live, ?string $actorAccountId = null): array
    {
        return $this->settlement->settleLive($live, $actorAccountId);
    }

    /** @return array<string, int|bool|string> */
    public function report(LiveEvent $live): array
    {
        return $this->settlement->report($live);
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

    /** @return array<string, mixed> */
    private function present(LiveEvent $live, string $accountId, ?LiveRewardAttentionState $state): array
    {
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
        $accountBlocks = LiveRewardAttentionBlock::query()->where('live_id', $live->id)->where('account_id', $accountId);
        $capturedForAccount = (clone $accountBlocks)->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED);
        $heldForAccount = (clone $accountBlocks)->where('status', LiveRewardAttentionBlock::STATUS_HELD);
        $capturedBlocks = (clone $capturedForAccount)->count();
        $earnedMinor = (int) (clone $capturedForAccount)->sum('reward_minor');
        $pendingReviewBlocks = (clone $heldForAccount)->count();
        $pendingReviewMinor = (int) (clone $heldForAccount)->sum('reward_minor');
        $globalCommittedBlocks = $campaign === null ? 0 : LiveRewardAttentionBlock::query()
            ->where('live_reward_campaign_id', $campaign->id)
            ->whereIn('status', [LiveRewardAttentionBlock::STATUS_CAPTURED, LiveRewardAttentionBlock::STATUS_HELD])
            ->count();
        $fundedBlocks = (int) ($quote?->funded_blocks ?? 0);

        $status = 'inactive';
        if ($seatActive && $quote !== null) {
            if (! $this->risk->rewardsEnabled()) {
                $status = 'rewards_paused';
            } elseif ($this->risk->isSelfRewardBlocked($live, $accountId)) {
                $status = 'unavailable';
            } elseif ($validatedBlocks >= $maxBlocks && $maxBlocks > 0) {
                $status = 'completed';
            } elseif ($fundedBlocks > 0 && $globalCommittedBlocks >= $fundedBlocks) {
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
            'captured_blocks' => $capturedBlocks,
            'pending_review_blocks' => $pendingReviewBlocks,
            'max_blocks' => $maxBlocks,
            'current_block_ms' => $currentMs,
            'block_duration_ms' => $requiredMs,
            'progress_percent' => $requiredMs > 0 ? (int) min(100, intdiv($currentMs * 100, $requiredMs)) : 0,
            'reward_per_block_minor' => $rewardPerBlock,
            'earned_minor' => $earnedMinor,
            'pending_review_minor' => $pendingReviewMinor,
            'max_reward_minor' => $maxBlocks * $rewardPerBlock,
            'funded_blocks' => $fundedBlocks,
            'committed_blocks' => $globalCommittedBlocks,
            'rewards_paused' => ! $this->risk->rewardsEnabled(),
            'balance_minor' => $this->userWallet->balanceMinor($accountId),
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
