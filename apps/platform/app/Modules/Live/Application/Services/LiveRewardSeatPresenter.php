<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;
use App\Modules\Live\Infrastructure\Models\LiveRewardWaitlistEntry;

final class LiveRewardSeatPresenter
{
    /**
     * Safe public economics only. Never returns advertiser budget, target segment,
     * ledger references or nominative audience data.
     */
    public static function publicPlan(LiveEvent $live): ?array
    {
        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            return null;
        }

        $campaign = LiveRewardCampaign::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardCampaign::STATUS_FUNDS_RESERVED)
            ->first();
        if ($campaign === null) {
            return null;
        }

        $reservation = $campaign->budgetReservations()
            ->where('status', LiveRewardBudgetReservation::STATUS_RESERVED)
            ->latest('reserved_at')
            ->first();
        $quote = $reservation?->quote;
        if ($quote === null || $quote->rewarded_seat_capacity < 1 || $quote->reward_per_block_minor < 1) {
            return null;
        }

        $activeSeats = LiveRewardSeat::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardSeat::STATUS_ACTIVE)
            ->count();
        $activeOffers = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardWaitlistEntry::STATUS_OFFERED)
            ->where('offer_expires_at', '>', now())
            ->count();
        $waitlistSize = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->whereIn('status', [
                LiveRewardWaitlistEntry::STATUS_WAITING,
                LiveRewardWaitlistEntry::STATUS_OFFERED,
            ])
            ->count();

        return [
            'block_duration_seconds' => $quote->block_duration_seconds,
            'reward_per_block_minor' => $quote->reward_per_block_minor,
            'max_blocks_per_viewer' => $quote->max_blocks_per_viewer,
            'max_reward_per_viewer_minor' => $quote->max_reward_per_viewer_minor,
            'rewarded_seat_capacity' => $quote->rewarded_seat_capacity,
            'active_rewarded_seats' => $activeSeats,
            'available_rewarded_seats' => max(0, $quote->rewarded_seat_capacity - $activeSeats - $activeOffers),
            'waitlist_size' => $waitlistSize,
        ];
    }
}
