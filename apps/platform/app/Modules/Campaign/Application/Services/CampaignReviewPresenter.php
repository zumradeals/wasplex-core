<?php

namespace App\Modules\Campaign\Application\Services;

use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignQuote;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewEvent;
use App\Modules\Campaign\Infrastructure\Models\CampaignStatusEvent;

final readonly class CampaignReviewPresenter
{
    public function __construct(private CampaignPresenter $campaigns) {}

    /** @return array<string, mixed> */
    public function summary(Campaign $campaign): array
    {
        $case = $campaign->reviewCases->first();
        $quote = $campaign->quotes->first();
        $funding = $campaign->funding;
        $amountMinor = 0;
        $currency = 'XOF';

        if ($quote instanceof CampaignQuote) {
            $amountMinor = $quote->gross_amount_minor;
            $currency = $quote->currency;
        } elseif ($funding instanceof CampaignFunding) {
            $amountMinor = $funding->amount_minor;
            $currency = $funding->currency;
        }

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'advertiser' => [
                'organizationId' => $campaign->organization_id,
                'name' => $campaign->organization->name,
                'spaceLabel' => $campaign->space->label,
            ],
            'brand' => [
                'id' => $campaign->brand->id,
                'name' => $campaign->brand->name,
                'primaryColor' => $campaign->brand->activeVersion?->primary_color,
                'secondaryColor' => $campaign->brand->activeVersion?->secondary_color,
            ],
            'review' => $case instanceof CampaignReviewCase ? $this->reviewCase($case) : null,
            'budget' => [
                'amountMinor' => $amountMinor,
                'currency' => $currency,
                'reservationStatus' => $funding?->budgetReservation?->status,
            ],
            'submittedAt' => $campaign->submitted_at?->toIso8601String(),
            'updatedAt' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Campaign $campaign): array
    {
        return [
            ...$this->campaigns->campaign($campaign),
            'advertiser' => [
                'organizationId' => $campaign->organization_id,
                'name' => $campaign->organization->name,
                'spaceId' => $campaign->advertiser_space_id,
                'spaceLabel' => $campaign->space->label,
                'creatorName' => $campaign->creator->profile?->display_name,
            ],
            'reviews' => $campaign->reviewCases
                ->map(fn (CampaignReviewCase $case): array => $this->reviewCase($case))
                ->values(),
            'statusHistory' => $campaign->statusEvents
                ->map(fn (CampaignStatusEvent $event): array => $this->statusEvent($event))
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function reviewCase(CampaignReviewCase $case): array
    {
        return [
            'id' => $case->id,
            'submissionNumber' => $case->submission_number,
            'status' => $case->status,
            'reason' => $case->decision_reason,
            'openedAt' => $case->opened_at->toIso8601String(),
            'decidedAt' => $case->decided_at?->toIso8601String(),
            'submitterName' => $case->submitter?->profile?->display_name,
            'deciderName' => $case->decider?->profile?->display_name,
            'task' => $case->task ? [
                'status' => $case->task->status,
                'priority' => $case->task->priority,
                'dueAt' => $case->task->due_at?->toIso8601String(),
                'completedAt' => $case->task->completed_at?->toIso8601String(),
            ] : null,
            'events' => $case->events
                ->map(fn (CampaignReviewEvent $event): array => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'fromStatus' => $event->from_status,
                    'toStatus' => $event->to_status,
                    'reason' => $event->reason,
                    'actorName' => $event->actor?->profile?->display_name,
                    'occurredAt' => $event->occurred_at->toIso8601String(),
                ])
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function statusEvent(CampaignStatusEvent $event): array
    {
        return [
            'id' => $event->id,
            'fromStatus' => $event->from_status,
            'toStatus' => $event->to_status,
            'reason' => $event->reason,
            'actorName' => $event->actor?->profile?->display_name,
            'occurredAt' => $event->occurred_at->toIso8601String(),
        ];
    }
}
