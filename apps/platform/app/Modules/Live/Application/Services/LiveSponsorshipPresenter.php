<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveRewardBudgetReservation;
use App\Modules\Live\Infrastructure\Models\LiveRewardCampaign;

final class LiveSponsorshipPresenter
{
    public static function forLive(LiveEvent $live): ?array
    {
        if ($live->type !== LiveEvent::TYPE_SPONSORED) {
            return null;
        }

        $campaign = $live->rewardCampaign()->first();
        if ($campaign === null) {
            return [
                'status' => LiveRewardCampaign::STATUS_DRAFT,
                'segment' => null,
                'latest_quote' => null,
                'reservation' => null,
                'can_schedule' => false,
            ];
        }

        $quote = $campaign->quotes()->latest('quoted_at')->first();
        $reservation = $campaign->budgetReservations()
            ->latest('created_at')
            ->first();

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
        ];
    }
}
