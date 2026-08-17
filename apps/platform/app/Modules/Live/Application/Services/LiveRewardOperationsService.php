<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardAttentionBlock;
use App\Modules\Live\Infrastructure\Models\LiveRewardHold;
use App\Modules\Live\Infrastructure\Models\LiveRewardRiskEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;

final class LiveRewardOperationsService
{
    public function __construct(private readonly LiveRewardRiskService $risk) {}

    /** @return array<string, int|bool|string> */
    public function dashboard(): array
    {
        $captured = LiveRewardAttentionBlock::query()->where('status', LiveRewardAttentionBlock::STATUS_CAPTURED);
        $pending = LiveRewardHold::query()->where('status', LiveRewardHold::STATUS_PENDING);
        $since = now()->subDay();
        $memberRewards = (int) (clone $captured)->sum('reward_minor');

        return [
            'rewards_enabled' => $this->risk->rewardsEnabled(),
            'antifraud_mode' => $this->risk->mode(),
            'active_sponsored_lives' => LiveEvent::query()
                ->where('type', LiveEvent::TYPE_SPONSORED)
                ->whereIn('status', [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED])
                ->count(),
            'active_rewarded_seats' => LiveRewardSeat::query()->where('status', LiveRewardSeat::STATUS_ACTIVE)->count(),
            'captured_blocks' => (clone $captured)->count(),
            'held_blocks' => LiveRewardAttentionBlock::query()->where('status', LiveRewardAttentionBlock::STATUS_HELD)->count(),
            'rejected_blocks' => LiveRewardAttentionBlock::query()->where('status', LiveRewardAttentionBlock::STATUS_REJECTED)->count(),
            'member_rewards_minor' => $memberRewards,
            'wasplex_revenue_minor' => $memberRewards,
            'gross_consumed_minor' => (int) (clone $captured)->sum('gross_amount_minor'),
            'pending_review_minor' => (int) (clone $pending)->sum('reward_minor'),
            'pending_review_gross_minor' => (int) (clone $pending)->sum('gross_amount_minor'),
            'pending_holds' => (clone $pending)->count(),
            'risk_events_24h' => LiveRewardRiskEvent::query()->where('occurred_at', '>=', $since)->count(),
            'critical_risk_events_24h' => LiveRewardRiskEvent::query()
                ->where('occurred_at', '>=', $since)
                ->where('severity', LiveRewardRiskEvent::SEVERITY_CRITICAL)
                ->count(),
        ];
    }
}
