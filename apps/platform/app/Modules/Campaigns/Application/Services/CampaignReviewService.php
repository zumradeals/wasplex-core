<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * docs/13-studio-annonceur-wasplex.md §54-57 : revue humaine exclusivement
 * — approve/request_changes/reject decide un dossier ouvert ; suspend agit
 * séparément sur une campagne déjà approuvée (§93 la liste comme une route
 * distincte). Politique financière par décision : docs/chantiers/
 * P007-CHANTIER.md §6 (seul le rejet libère la réservation).
 */
final class CampaignReviewService
{
    public function __construct(private readonly AdvertiserWalletReservationContract $reservations) {}

    /**
     * @return Collection<int, CampaignReviewCase>
     */
    public function listQueue(): Collection
    {
        return CampaignReviewCase::query()
            ->where('status', CampaignReviewCase::STATUS_OPEN)
            ->with(['campaign', 'campaignVersion'])
            ->orderBy('opened_at')
            ->get();
    }

    public function find(string $caseId): CampaignReviewCase
    {
        $case = CampaignReviewCase::query()
            ->with(['campaign.reservations', 'campaignVersion.quotes', 'events'])
            ->find($caseId);

        if ($case === null) {
            throw new CampaignReviewCaseNotFoundException($caseId);
        }

        return $case;
    }

    public function approve(string $caseId, string $actorAccountId): CampaignReviewCase
    {
        $case = $this->find($caseId);
        $this->assertOpen($case);
        $this->assertDistinctDecider($case, $actorAccountId);

        return DB::transaction(function () use ($case, $actorAccountId): CampaignReviewCase {
            $case->update([
                'status' => CampaignReviewCase::STATUS_DECIDED,
                'decision' => CampaignReviewCase::DECISION_APPROVED,
                'decided_by' => $actorAccountId,
                'decided_at' => now(),
            ]);

            CampaignReviewEvent::query()->create([
                'campaign_review_case_id' => $case->id,
                'event_type' => CampaignReviewEvent::TYPE_APPROVED,
                'actor_account_id' => $actorAccountId,
            ]);

            $durationDays = (int) ($case->campaignVersion->latestQuote()?->priceVersion?->duration_days ?? 7);
            $start = Carbon::today('UTC');
            $case->campaign->update([
                'status' => Campaign::STATUS_APPROVED,
                'scheduled_start' => $start,
                'scheduled_end' => $start->clone()->addDays($durationDays - 1),
            ]);

            return $case->refresh();
        });
    }

    public function requestChanges(string $caseId, string $actorAccountId, string $reason): CampaignReviewCase
    {
        $case = $this->find($caseId);
        $this->assertOpen($case);

        return DB::transaction(function () use ($case, $actorAccountId, $reason): CampaignReviewCase {
            $case->update([
                'status' => CampaignReviewCase::STATUS_DECIDED,
                'decision' => CampaignReviewCase::DECISION_CHANGES_REQUESTED,
                'reason' => $reason,
                'requested_changes_by' => $actorAccountId,
                'decided_by' => $actorAccountId,
                'decided_at' => now(),
            ]);

            CampaignReviewEvent::query()->create([
                'campaign_review_case_id' => $case->id,
                'event_type' => CampaignReviewEvent::TYPE_CHANGES_REQUESTED,
                'actor_account_id' => $actorAccountId,
                'reason' => $reason,
            ]);

            $case->campaign->update(['status' => Campaign::STATUS_CHANGES_REQUESTED]);

            return $case->refresh();
        });
    }

    public function reject(string $caseId, string $actorAccountId, string $reason): CampaignReviewCase
    {
        $case = $this->find($caseId);
        $this->assertOpen($case);
        $this->assertDistinctDecider($case, $actorAccountId);

        return DB::transaction(function () use ($case, $actorAccountId, $reason): CampaignReviewCase {
            $case->update([
                'status' => CampaignReviewCase::STATUS_DECIDED,
                'decision' => CampaignReviewCase::DECISION_REJECTED,
                'reason' => $reason,
                'decided_by' => $actorAccountId,
                'decided_at' => now(),
            ]);

            CampaignReviewEvent::query()->create([
                'campaign_review_case_id' => $case->id,
                'event_type' => CampaignReviewEvent::TYPE_REJECTED,
                'actor_account_id' => $actorAccountId,
                'reason' => $reason,
            ]);

            $campaign = $case->campaign;
            $campaign->update(['status' => Campaign::STATUS_REJECTED]);

            $reservation = CampaignBudgetReservation::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', CampaignBudgetReservation::STATUS_RESERVED)
                ->first();

            if ($reservation !== null) {
                $idempotencyKey = "campaign-budget-release:{$reservation->id}";
                $this->reservations->release($campaign->organization_id, $campaign->id, $reservation->amount_minor, $idempotencyKey);
                $reservation->update(['status' => CampaignBudgetReservation::STATUS_RELEASED]);
            }

            return $case->refresh();
        });
    }

    public function suspend(string $campaignId, string $actorAccountId, string $reason): Campaign
    {
        $campaign = Campaign::query()->find($campaignId);

        if ($campaign === null) {
            throw new CampaignNotFoundException($campaignId);
        }

        if ($campaign->status !== Campaign::STATUS_APPROVED) {
            throw new CampaignNotApprovedException;
        }

        return DB::transaction(function () use ($campaign, $actorAccountId, $reason): Campaign {
            $campaign->update(['status' => Campaign::STATUS_SUSPENDED]);

            $case = $campaign->latestReviewCase();

            if ($case !== null) {
                CampaignReviewEvent::query()->create([
                    'campaign_review_case_id' => $case->id,
                    'event_type' => CampaignReviewEvent::TYPE_SUSPENDED,
                    'actor_account_id' => $actorAccountId,
                    'reason' => $reason,
                ]);
            }

            return $campaign->refresh();
        });
    }

    private function assertOpen(CampaignReviewCase $case): void
    {
        if (! $case->isOpen()) {
            throw new CampaignReviewCaseAlreadyDecidedException($case->id);
        }
    }

    /**
     * If configured, the admin who requested changes on a prior case for
     * this campaign cannot be the one deciding the resubmission.
     */
    private function assertDistinctDecider(CampaignReviewCase $case, string $actorAccountId): void
    {
        if (! config('campaigns.review_require_distinct_decider')) {
            return;
        }

        // `decided_at` is second-precision — order by the ULID primary key
        // too so a tie always resolves to the truly most recent case.
        $previous = CampaignReviewCase::query()
            ->where('campaign_id', $case->campaign_id)
            ->where('id', '!=', $case->id)
            ->where('decision', CampaignReviewCase::DECISION_CHANGES_REQUESTED)
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->first();

        if ($previous !== null && $previous->requested_changes_by === $actorAccountId) {
            throw new SameAdminCannotDecideException;
        }
    }
}
