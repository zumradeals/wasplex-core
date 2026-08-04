<?php

namespace App\Modules\Campaign\Application\Services;

use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewEvent;
use App\Modules\Campaign\Infrastructure\Models\CampaignReviewTask;
use App\Modules\Campaign\Infrastructure\Models\CampaignStatusEvent;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CampaignReviewService
{
    public function __construct(private WalletReservationContract $reservations) {}

    public function openForSubmission(
        Campaign $campaign,
        ?Account $submitter = null,
        ?string $fromStatus = null,
    ): CampaignReviewCase {
        return DB::transaction(function () use ($campaign, $submitter, $fromStatus): CampaignReviewCase {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $versionId = $locked->active_version_id;

            if (! is_string($versionId)) {
                throw ValidationException::withMessages([
                    'campaign' => 'La version active de la campagne est introuvable.',
                ]);
            }

            $existing = CampaignReviewCase::query()
                ->where('campaign_id', $locked->id)
                ->where('campaign_version_id', $versionId)
                ->where('status', 'pending')
                ->latest('submission_number')
                ->first();

            if ($existing instanceof CampaignReviewCase) {
                return $existing->load(['task', 'events.actor']);
            }

            $submissionNumber = (int) CampaignReviewCase::query()
                ->where('campaign_id', $locked->id)
                ->max('submission_number') + 1;

            $case = CampaignReviewCase::query()->create([
                'campaign_id' => $locked->id,
                'campaign_version_id' => $versionId,
                'submission_number' => $submissionNumber,
                'status' => 'pending',
                'submitted_by_account_id' => $submitter?->id,
                'opened_at' => now(),
            ]);

            CampaignReviewTask::query()->create([
                'review_case_id' => $case->id,
                'campaign_id' => $locked->id,
                'status' => 'pending',
                'priority' => 'normal',
                'due_at' => now()->addHours((int) config('campaign-review.default_due_hours', 24)),
            ]);

            $this->recordReviewEvent(
                case: $case,
                actor: $submitter,
                type: $submissionNumber === 1 ? 'review_opened' : 'campaign_resubmitted',
                fromStatus: null,
                toStatus: 'pending',
                reason: null,
                metadata: ['submission_number' => $submissionNumber, 'campaign_version_id' => $versionId],
            );
            $this->recordStatusEvent(
                campaign: $locked,
                actor: $submitter,
                fromStatus: $fromStatus,
                toStatus: 'submitted',
                reason: $submissionNumber === 1 ? 'Soumission pour revue administrative' : 'Resoumission après corrections',
                metadata: ['review_case_id' => $case->id, 'submission_number' => $submissionNumber],
            );

            DB::afterCommit(static fn () => event(
                $submissionNumber === 1 ? 'campaign.review_opened' : 'campaign.resubmitted',
                [$locked->id, $case->id],
            ));

            return $case->load(['task', 'events.actor']);
        });
    }

    public function requestChanges(Campaign $campaign, Account $actor, string $reason): Campaign
    {
        $reason = $this->requiredReason($reason, 'Le motif de correction est obligatoire.');

        return DB::transaction(function () use ($campaign, $actor, $reason): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->guardCampaignStatus($locked, ['submitted'], 'Cette campagne ne peut pas être renvoyée en correction.');
            $case = $this->pendingCase($locked);
            $this->guardDistinctDecider($case, $actor);
            $this->guardActiveReservation($locked);

            $case->forceFill([
                'status' => 'changes_requested',
                'decided_by_account_id' => $actor->id,
                'decision_reason' => $reason,
                'decided_at' => now(),
            ])->save();
            $this->completeTask($case, $actor);

            $locked->forceFill(['status' => 'changes_requested'])->save();
            $this->recordReviewEvent($case, $actor, 'changes_requested', 'pending', 'changes_requested', $reason);
            $this->recordStatusEvent(
                $locked,
                $actor,
                'submitted',
                'changes_requested',
                $reason,
                ['review_case_id' => $case->id],
            );

            DB::afterCommit(static fn () => event('campaign.changes_requested', [$locked->id, $case->id]));

            return $locked->load($this->campaignRelations());
        });
    }

    public function approve(Campaign $campaign, Account $actor, ?string $reason = null): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor, $reason): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->guardCampaignStatus($locked, ['submitted'], 'Cette campagne ne peut pas être approuvée.');
            $case = $this->pendingCase($locked);
            $this->guardDistinctDecider($case, $actor);
            [$funding] = $this->guardActiveReservation($locked);

            $case->forceFill([
                'status' => 'approved',
                'decided_by_account_id' => $actor->id,
                'decision_reason' => $this->nullableReason($reason),
                'decided_at' => now(),
            ])->save();
            $this->completeTask($case, $actor);

            $funding->forceFill(['status' => 'approved'])->save();
            $locked->forceFill(['status' => 'approved'])->save();
            $this->recordReviewEvent($case, $actor, 'approved', 'pending', 'approved', $reason);
            $this->recordStatusEvent(
                $locked,
                $actor,
                'submitted',
                'approved',
                $reason,
                ['review_case_id' => $case->id],
            );

            DB::afterCommit(static fn () => event('campaign.approved', [$locked->id, $case->id]));

            return $locked->load($this->campaignRelations());
        });
    }

    public function reject(
        Campaign $campaign,
        Account $actor,
        string $reason,
        ?string $traceId = null,
    ): Campaign {
        $reason = $this->requiredReason($reason, 'Le motif de rejet est obligatoire.');

        return DB::transaction(function () use ($campaign, $actor, $reason, $traceId): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'rejected') {
                return $locked->load($this->campaignRelations());
            }

            $this->guardCampaignStatus($locked, ['submitted'], 'Cette campagne ne peut pas être rejetée.');
            $case = $this->pendingCase($locked);
            $this->guardDistinctDecider($case, $actor);
            [$funding, $budgetReservation] = $this->guardActiveReservation($locked);

            $this->reservations->release(
                reservationId: $budgetReservation->wallet_reservation_id,
                idempotencyKey: "campaign-review-reject:{$case->id}",
                reason: $reason,
                actorAccountId: $actor->id,
                traceId: $traceId,
            );

            $budgetReservation->forceFill(['status' => 'released'])->save();
            $funding->forceFill(['status' => 'released'])->save();
            $case->forceFill([
                'status' => 'rejected',
                'decided_by_account_id' => $actor->id,
                'decision_reason' => $reason,
                'decided_at' => now(),
            ])->save();
            $this->completeTask($case, $actor);

            $locked->forceFill(['status' => 'rejected'])->save();
            $this->recordReviewEvent($case, $actor, 'rejected', 'pending', 'rejected', $reason);
            $this->recordStatusEvent(
                $locked,
                $actor,
                'submitted',
                'rejected',
                $reason,
                ['review_case_id' => $case->id, 'reservation_released' => true],
            );

            DB::afterCommit(static fn () => event('campaign.rejected', [$locked->id, $case->id]));

            return $locked->load($this->campaignRelations());
        });
    }

    public function suspend(Campaign $campaign, Account $actor, string $reason): Campaign
    {
        $reason = $this->requiredReason($reason, 'Le motif de suspension est obligatoire.');

        return DB::transaction(function () use ($campaign, $actor, $reason): Campaign {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'suspended') {
                return $locked->load($this->campaignRelations());
            }

            $this->guardCampaignStatus($locked, ['approved'], 'Seule une campagne approuvée peut être suspendue.');
            [$funding] = $this->guardActiveReservation($locked);
            $case = CampaignReviewCase::query()
                ->where('campaign_id', $locked->id)
                ->latest('submission_number')
                ->firstOrFail();

            $funding->forceFill(['status' => 'suspended'])->save();
            $locked->forceFill(['status' => 'suspended'])->save();
            $this->recordReviewEvent($case, $actor, 'suspended', 'approved', 'suspended', $reason);
            $this->recordStatusEvent(
                $locked,
                $actor,
                'approved',
                'suspended',
                $reason,
                ['review_case_id' => $case->id, 'reservation_preserved' => true],
            );

            DB::afterCommit(static fn () => event('campaign.suspended', [$locked->id, $case->id]));

            return $locked->load($this->campaignRelations());
        });
    }

    public function ensureSubmittedCases(?Account $actor = null): int
    {
        $count = 0;

        Campaign::query()
            ->where('status', 'submitted')
            ->whereNotNull('active_version_id')
            ->each(function (Campaign $campaign) use ($actor, &$count): void {
                $exists = CampaignReviewCase::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($exists) {
                    return;
                }

                $this->openForSubmission($campaign, $actor, 'funded');
                $count++;
            });

        return $count;
    }

    public function pendingCount(): int
    {
        return CampaignReviewCase::query()->where('status', 'pending')->count();
    }

    /** @return array{0:CampaignFunding,1:CampaignBudgetReservation} */
    private function guardActiveReservation(Campaign $campaign): array
    {
        $funding = $campaign->funding()->with('budgetReservation.walletReservation')->first();
        $budgetReservation = $funding?->budgetReservation;

        if (
            ! $funding instanceof CampaignFunding
            || ! $budgetReservation instanceof CampaignBudgetReservation
            || $budgetReservation->status !== 'active'
            || $budgetReservation->walletReservation?->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'campaign' => 'La réservation budgétaire active est introuvable.',
            ]);
        }

        return [$funding, $budgetReservation];
    }

    private function pendingCase(Campaign $campaign): CampaignReviewCase
    {
        $case = CampaignReviewCase::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->latest('submission_number')
            ->lockForUpdate()
            ->first();

        if (! $case instanceof CampaignReviewCase) {
            throw ValidationException::withMessages([
                'campaign' => 'Aucun dossier de revue en attente n’est associé à cette campagne.',
            ]);
        }

        return $case;
    }

    private function completeTask(CampaignReviewCase $case, Account $actor): void
    {
        CampaignReviewTask::query()
            ->where('review_case_id', $case->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'completed',
                'assigned_to_account_id' => $actor->id,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function guardDistinctDecider(CampaignReviewCase $case, Account $actor): void
    {
        if (
            (bool) config('campaign-review.require_distinct_decider', false)
            && $case->submitted_by_account_id === $actor->id
        ) {
            throw ValidationException::withMessages([
                'campaign' => 'Le demandeur ne peut pas décider de sa propre campagne avec cette politique.',
            ]);
        }
    }

    /** @param list<string> $allowed */
    private function guardCampaignStatus(Campaign $campaign, array $allowed, string $message): void
    {
        if (! in_array($campaign->status, $allowed, true)) {
            throw ValidationException::withMessages(['campaign' => $message]);
        }
    }

    private function requiredReason(string $reason, string $message): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => $message]);
        }

        return $reason;
    }

    private function nullableReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }

    /** @param array<string, mixed>|null $metadata */
    private function recordReviewEvent(
        CampaignReviewCase $case,
        ?Account $actor,
        string $type,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?array $metadata = null,
    ): void {
        CampaignReviewEvent::query()->create([
            'review_case_id' => $case->id,
            'campaign_id' => $case->campaign_id,
            'actor_account_id' => $actor?->id,
            'type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $this->nullableReason($reason),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function recordStatusEvent(
        Campaign $campaign,
        ?Account $actor,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?array $metadata = null,
    ): void {
        CampaignStatusEvent::query()->create([
            'campaign_id' => $campaign->id,
            'actor_account_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $this->nullableReason($reason),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function campaignRelations(): array
    {
        return [
            'brand.activeVersion.logoAsset',
            'activeVersion.creatives.versions',
            'audiences',
            'quotes.audience',
            'quotes.priceCatalog',
            'funding.budgetReservation.walletReservation',
            'reviewCases.task',
            'reviewCases.events.actor.profile',
            'statusEvents.actor.profile',
        ];
    }
}
