<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;
use App\Modules\Live\Infrastructure\Models\LiveRewardSeat;
use App\Modules\Live\Infrastructure\Models\LiveRewardWaitlistEntry;

final class LiveSponsorshipPresenter
{
    public static function forLive(LiveEvent $live): ?array
    {
        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            return null;
        }

        $campaign = $live->relationLoaded('rewardCampaign')
            ? $live->rewardCampaign
            : $live->rewardCampaign()->with(['quotes', 'budgetReservations'])->first();
        if ($campaign === null) {
            return [
                'status' => LiveRewardCampaign::STATUS_DRAFT,
                'segment' => null,
                'latest_quote' => null,
                'reservation' => null,
                'can_schedule' => false,
                'seat_runtime' => null,
            ];
        }

        $quote = $campaign->relationLoaded('quotes')
            ? $campaign->quotes->sortByDesc('quoted_at')->first()
            : $campaign->quotes()->latest('quoted_at')->first();
        $reservation = $campaign->relationLoaded('budgetReservations')
            ? $campaign->budgetReservations->sortByDesc('created_at')->first()
            : $campaign->budgetReservations()->latest('created_at')->first();

        $activeSeats = LiveRewardSeat::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardSeat::STATUS_ACTIVE)
            ->count();
        $waiting = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardWaitlistEntry::STATUS_WAITING)
            ->count();
        $offered = LiveRewardWaitlistEntry::query()
            ->where('live_id', $live->id)
            ->where('status', LiveRewardWaitlistEntry::STATUS_OFFERED)
            ->where('offer_expires_at', '>', now())
            ->count();

        return [
            'status' => $campaign->status,
            'segment' => [
                'country_code' => $campaign->segment_configuration['territory']['country_code'] ?? null,
                'economic_classes' => $campaign->segment_configuration['economic_classes'] ?? [],
            ],
            'latest_quote' => $quote === null ? null : [
                'id' => $quote->id,
                'status' => $quote->status,
                'currency' => $quote->currency,
                'budget_amount_minor' => $quote->budget_amount_minor,
                'wasplex_share_minor' => $quote->wasplex_share_minor,
                'spectator_envelope_minor' => $quote->spectator_envelope_minor,
                'estimated_reach_min' => $quote->estimated_reach_min,
                'estimated_reach_max' => $quote->estimated_reach_max,
                'planned_duration_minutes' => $quote->planned_duration_minutes,
                'block_duration_seconds' => $quote->block_duration_seconds,
                'rewarded_seat_capacity' => $quote->rewarded_seat_capacity,
                'max_blocks_per_viewer' => $quote->max_blocks_per_viewer,
                'funded_blocks' => $quote->funded_blocks,
                'reward_per_block_minor' => $quote->reward_per_block_minor,
                'max_reward_per_viewer_minor' => $quote->max_reward_per_viewer_minor,
                'spectator_envelope_remainder_minor' => $quote->spectator_envelope_remainder_minor,
                'quoted_at' => $quote->quoted_at?->toIso8601String(),
            ],
            'reservation' => $reservation === null ? null : [
                'status' => $reservation->status,
                'amount_minor' => $reservation->amount_minor,
                'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                'released_at' => $reservation->released_at?->toIso8601String(),
            ],
            'can_schedule' => $campaign->status === LiveRewardCampaign::STATUS_FUNDS_RESERVED
                && $reservation?->status === LiveRewardBudgetReservation::STATUS_RESERVED,
            'seat_runtime' => $quote === null ? null : [
                'active' => $activeSeats,
                'offered' => $offered,
                'waiting' => $waiting,
                'available' => max(0, $quote->rewarded_seat_capacity - $activeSeats - $offered),
            ],
        ];
    }
}
