<?php

declare(strict_types=1);

namespace App\Modules\Funds\Application\Services;

use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundCollectionParticipant;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundReciprocityProfile;
use App\Modules\Funds\Infrastructure\Models\FundRehabilitationCase;
use App\Modules\Funds\Infrastructure\Models\FundReserveAllocation;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishQueueEntry;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundPilotageService
{
    public function __construct(
        private readonly FundCollectionService $collections,
        private readonly FundRealizationService $realizations,
    ) {}

    public function refreshReciprocity(string $accountId): FundReciprocityProfile
    {
        $membership = FundMembership::query()
            ->with(['version', 'program'])
            ->where('account_id', $accountId)
            ->latest('started_at')
            ->first();

        $seniorityDays = (int) ($membership?->started_at?->diffInDays(now()) ?? 0);
        $seniorityPoints = min(20, intdiv(max(0, $seniorityDays), 30) * 2);

        $participation = FundCollectionParticipant::query()
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(solidarity_due_minor), 0) AS due_minor, COALESCE(SUM(solidarity_paid_minor), 0) AS paid_minor')
            ->first();
        $dueMinor = (int) ($participation?->due_minor ?? 0);
        $paidMinor = (int) ($participation?->paid_minor ?? 0);
        $participationRatio = $dueMinor > 0 ? min(1, $paidMinor / $dueMinor) : null;
        $participationPoints = $participationRatio === null ? 15 : (int) round($participationRatio * 45);

        $openArrears = FundArrear::query()->where('account_id', $accountId)->where('status', FundArrear::STATUS_OPEN)->count();
        $overdueArrears = FundArrear::query()
            ->where('account_id', $accountId)
            ->where('status', FundArrear::STATUS_OPEN)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->count();
        $settledArrears = FundArrear::query()->where('account_id', $accountId)->where('status', FundArrear::STATUS_SETTLED)->count();
        $regularizationPoints = min(15, $settledArrears * 3);
        $incidentPenalty = min(55, ($openArrears * 8) + ($overdueArrears * 12));

        $receivedRealizations = FundOrder::query()
            ->whereHas('wish', fn ($query) => $query->where('account_id', $accountId))
            ->where('status', FundOrder::STATUS_COMPLETED)
            ->count();

        $score = max(0, min(100, 20 + $seniorityPoints + $participationPoints + $regularizationPoints - $incidentPenalty));
        $level = match (true) {
            $score >= 85 => 'exemplary',
            $score >= 65 => 'solid',
            $score >= 40 => 'regular',
            default => 'starting',
        };

        return FundReciprocityProfile::query()->updateOrCreate(
            ['account_id' => $accountId],
            [
                'score' => $score,
                'level' => $level,
                'factors' => [
                    'seniority_days' => $seniorityDays,
                    'seniority_points' => $seniorityPoints,
                    'solidarity_due_minor' => $dueMinor,
                    'solidarity_paid_minor' => $paidMinor,
                    'participation_points' => $participationPoints,
                    'open_arrears' => $openArrears,
                    'overdue_arrears' => $overdueArrears,
                    'settled_arrears' => $settledArrears,
                    'regularization_points' => $regularizationPoints,
                    'incident_penalty' => $incidentPenalty,
                    'received_realizations' => $receivedRealizations,
                    'principles' => [
                        'not_money' => true,
                        'not_purchasable' => true,
                        'not_a_guarantee' => true,
                        'verified_vital_emergency_overrides_score' => true,
                    ],
                ],
                'calculated_at' => now(),
            ],
        );
    }

    public function queueWish(FundWish $wish, string $lane, string $actorAccountId, bool $emergencyVerified = false): FundWishQueueEntry
    {
        if (! in_array($lane, [FundWishQueueEntry::LANE_ORDINARY, FundWishQueueEntry::LANE_EMERGENCY], true)) {
            throw new RuntimeException('File de financement inconnue.');
        }
        if ($lane === FundWishQueueEntry::LANE_EMERGENCY && ! $emergencyVerified) {
            throw new RuntimeException('Une urgence doit être explicitement vérifiée avant son placement dans la file prioritaire.');
        }

        return DB::transaction(function () use ($wish, $lane, $actorAccountId): FundWishQueueEntry {
            $wish = FundWish::query()->with(['membership.version'])->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if ($wish->status !== FundWish::STATUS_APPROVED) {
                throw new RuntimeException('Seul un vœu validé peut entrer dans une file de financement.');
            }
            if ($wish->validated_amount_minor === null || $wish->provider_organization_id === null || $wish->selected_quote_id === null) {
                throw new RuntimeException('Le coût et le prestataire doivent être validés avant la mise en file.');
            }
            if ($wish->collectionSnapshot()->exists()) {
                throw new RuntimeException('Une collecte existe déjà pour ce vœu.');
            }

            $reciprocity = $this->refreshReciprocity((string) $wish->account_id);
            $minimumScore = (int) ($wish->membership->version->reciprocity_min_score ?? 0);
            if ($lane === FundWishQueueEntry::LANE_ORDINARY && (int) $reciprocity->score < $minimumScore) {
                throw new RuntimeException('L’indice de réciprocité minimum du programme n’est pas encore atteint.');
            }
            $priorityScore = $lane === FundWishQueueEntry::LANE_EMERGENCY
                ? 1_000_000
                : (int) $reciprocity->score;

            $entry = FundWishQueueEntry::query()->updateOrCreate(
                ['fund_wish_id' => $wish->id],
                [
                    'fund_program_id' => $wish->membership->fund_program_id,
                    'program_version_id' => $wish->membership->program_version_id,
                    'lane' => $lane,
                    'priority_score' => $priorityScore,
                    'status' => FundWishQueueEntry::STATUS_QUEUED,
                    'blocked_reason' => null,
                    'queued_at' => now(),
                    'released_at' => null,
                    'queued_by_account_id' => $actorAccountId,
                    'released_by_account_id' => null,
                ],
            );
            FundAuditEvent::record($actorAccountId, 'FundWishQueued', 'fund_wish_queue_entry', $entry->id, [
                'fund_wish_id' => $wish->id,
                'lane' => $lane,
                'priority_score' => $priorityScore,
            ]);

            return $entry;
        });
    }

    public function releaseNext(string $programId, string $actorAccountId): FundCollectionSnapshot
    {
        $entryId = null;

        try {
            return DB::transaction(function () use ($programId, $actorAccountId, &$entryId): FundCollectionSnapshot {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:queue:'.$programId]);

                $entry = FundWishQueueEntry::query()
                    ->with('wish')
                    ->where('fund_program_id', $programId)
                    ->where('status', FundWishQueueEntry::STATUS_QUEUED)
                    ->orderByRaw("CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END")
                    ->orderByDesc('priority_score')
                    ->orderBy('queued_at')
                    ->lockForUpdate()
                    ->first();

                if ($entry === null) {
                    throw new RuntimeException('Aucun vœu n’attend dans cette file.');
                }
                $entryId = (string) $entry->id;
                $snapshot = $this->collections->createSnapshot($entry->wish, $actorAccountId);

                $entry->update([
                    'status' => FundWishQueueEntry::STATUS_RELEASED,
                    'released_at' => now(),
                    'released_by_account_id' => $actorAccountId,
                    'blocked_reason' => null,
                ]);
                FundAuditEvent::record($actorAccountId, 'FundWishQueueReleased', 'fund_wish_queue_entry', $entry->id, [
                    'snapshot_id' => $snapshot->id,
                ]);

                return $snapshot;
            });
        } catch (RuntimeException $exception) {
            if ($entryId !== null) {
                FundWishQueueEntry::query()
                    ->whereKey($entryId)
                    ->where('status', FundWishQueueEntry::STATUS_QUEUED)
                    ->update([
                        'status' => FundWishQueueEntry::STATUS_BLOCKED,
                        'blocked_reason' => $exception->getMessage(),
                    ]);
                FundAuditEvent::record($actorAccountId, 'FundWishQueueBlocked', 'fund_wish_queue_entry', $entryId, [
                    'reason' => $exception->getMessage(),
                ]);
            }
            throw $exception;
        }
    }

    public function authorizeReserve(FundWish $wish, int $amountMinor, string $reason, ?string $justification, string $actorAccountId): FundReserveAllocation
    {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Le montant de réserve doit être positif.');
        }

        return DB::transaction(function () use ($wish, $amountMinor, $reason, $justification, $actorAccountId): FundReserveAllocation {
            $wish = FundWish::query()->with('membership.version')->whereKey($wish->id)->lockForUpdate()->firstOrFail();
            if ($wish->collectionSnapshot()->exists()) {
                throw new RuntimeException('La réserve doit être autorisée avant la création du snapshot immuable.');
            }
            if ($wish->status !== FundWish::STATUS_APPROVED) {
                throw new RuntimeException('La réserve ne peut être affectée qu’à un vœu validé.');
            }

            $available = $this->availableReserveMinor();
            $minimumBalance = max(0, (int) ($wish->membership->version->reserve_min_balance_minor ?? 0));
            $allocatable = max(0, $available - $minimumBalance);
            if ($amountMinor > $allocatable) {
                throw new RuntimeException('Cette allocation ferait passer la réserve sous le plancher du programme.');
            }

            $allocation = FundReserveAllocation::query()->create([
                'fund_program_id' => $wish->membership->fund_program_id,
                'fund_wish_id' => $wish->id,
                'amount_minor' => $amountMinor,
                'consumed_minor' => 0,
                'status' => FundReserveAllocation::STATUS_AUTHORIZED,
                'reason' => $reason,
                'justification' => $justification,
                'authorized_by_account_id' => $actorAccountId,
                'authorized_at' => now(),
            ]);
            FundAuditEvent::record($actorAccountId, 'FundReserveAllocated', 'fund_reserve_allocation', $allocation->id, [
                'fund_wish_id' => $wish->id,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
            ]);

            return $allocation;
        });
    }

    public function releaseReserveAllocation(FundReserveAllocation $allocation, string $actorAccountId): FundReserveAllocation
    {
        if ((int) $allocation->consumed_minor > 0) {
            throw new RuntimeException('Une allocation déjà consommée ne peut pas être libérée intégralement.');
        }
        $allocation->update([
            'status' => FundReserveAllocation::STATUS_RELEASED,
            'released_at' => now(),
        ]);
        FundAuditEvent::record($actorAccountId, 'FundReserveAllocationReleased', 'fund_reserve_allocation', $allocation->id);

        return $allocation->refresh();
    }

    public function availableReserveMinor(): int
    {
        $reserved = (int) FundReserveAllocation::query()
            ->whereIn('status', [FundReserveAllocation::STATUS_AUTHORIZED, FundReserveAllocation::STATUS_PARTIALLY_CONSUMED])
            ->selectRaw('COALESCE(SUM(amount_minor - consumed_minor), 0) AS reserved_minor')
            ->value('reserved_minor');

        return max(0, $this->realizations->reserveBalanceMinor() - $reserved);
    }

    public function deficitPlan(FundCollectionSnapshot $snapshot): array
    {
        $snapshot->load('participants');
        $collected = (int) $snapshot->participants->sum('solidarity_paid_minor');
        $missing = max(0, (int) $snapshot->collective_amount_minor - $collected);
        $arrears = (int) $snapshot->participants->sum('arrears_minor');

        $actions = [];
        if ($missing > 0 && $arrears > 0) {
            $actions[] = 'wait_for_arrears';
        }
        if ($missing > 0 && $this->availableReserveMinor() > 0) {
            $actions[] = 'consider_reserve';
        }
        if ($missing > 0) {
            $actions = [...$actions, 'request_extra_personal_contribution', 'renegotiate_quote', 'resize_wish', 'extend_or_second_wave', 'postpone_or_cancel'];
        }

        return [
            'snapshot_id' => $snapshot->id,
            'collective_amount_minor' => (int) $snapshot->collective_amount_minor,
            'collected_minor' => $collected,
            'missing_minor' => $missing,
            'arrears_minor' => $arrears,
            'available_reserve_minor' => $this->availableReserveMinor(),
            'recommended_actions' => array_values(array_unique($actions)),
        ];
    }

    public function detectRehabilitationCases(?string $actorAccountId = null): int
    {
        $created = 0;
        $memberships = FundMembership::query()->with(['version', 'mandate', 'subscription', 'program'])->get();
        foreach ($memberships as $membership) {
            $threshold = max(1, (int) ($membership->version?->rehabilitation_incident_threshold ?? 3));
            $overdue = FundArrear::query()
                ->where('account_id', $membership->account_id)
                ->where('status', FundArrear::STATUS_OPEN)
                ->whereNotNull('grace_ends_at')
                ->where('grace_ends_at', '<', now())
                ->count();
            if ($overdue < $threshold) {
                continue;
            }

            $exists = FundRehabilitationCase::query()
                ->where('fund_membership_id', $membership->id)
                ->whereIn('status', [FundRehabilitationCase::STATUS_REQUIRED, FundRehabilitationCase::STATUS_IN_PROGRESS])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::transaction(function () use ($membership, $overdue, $actorAccountId): void {
                $membership->update([
                    'status' => FundMembership::STATUS_SUSPENDED,
                    'suspended_at' => now(),
                ]);
                $case = FundRehabilitationCase::query()->create([
                    'fund_membership_id' => $membership->id,
                    'account_id' => $membership->account_id,
                    'status' => FundRehabilitationCase::STATUS_REQUIRED,
                    'trigger_code' => 'persistent_overdue_arrears',
                    'incident_count' => $overdue,
                    'required_actions' => [
                        'settle_open_arrears',
                        'keep_eligible_paid_subscription_active',
                        'keep_valid_fund_mandate',
                        'request_reactivation_review',
                    ],
                    'opened_at' => now(),
                ]);
                FundAuditEvent::record($actorAccountId, 'FundRehabilitationRequired', 'fund_rehabilitation_case', $case->id, [
                    'incident_count' => $overdue,
                ]);
            });
            $created++;
        }

        return $created;
    }

    public function startRehabilitation(FundRehabilitationCase $case, string $accountId): FundRehabilitationCase
    {
        if ((string) $case->account_id !== $accountId) {
            throw new RuntimeException('Dossier de réhabilitation introuvable.');
        }
        if ($case->status !== FundRehabilitationCase::STATUS_REQUIRED) {
            return $case;
        }
        $case->update(['status' => FundRehabilitationCase::STATUS_IN_PROGRESS]);
        FundAuditEvent::record($accountId, 'FundRehabilitationStarted', 'fund_rehabilitation_case', $case->id);

        return $case->refresh();
    }

    public function completeRehabilitation(FundRehabilitationCase $case, string $actorAccountId, string $note): FundRehabilitationCase
    {
        return DB::transaction(function () use ($case, $actorAccountId, $note): FundRehabilitationCase {
            $case = FundRehabilitationCase::query()->with(['membership.subscription.entitlements', 'membership.mandate', 'membership.program'])->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $membership = $case->membership;

            if (FundArrear::query()->where('account_id', $case->account_id)->where('status', FundArrear::STATUS_OPEN)->exists()) {
                throw new RuntimeException('Toutes les contributions à régulariser doivent être soldées avant réactivation.');
            }
            $eligibleSubscription = $membership->subscription !== null
                && $membership->subscription->isActive()
                && $membership->subscription->entitlements->contains(
                    fn ($entitlement): bool => $entitlement->key === SubscriptionEntitlement::KEY_FONDS_ELIGIBLE && (bool) $entitlement->enabled,
                );
            if (! $eligibleSubscription) {
                throw new RuntimeException('Un abonnement payant éligible Fonds actif est requis.');
            }
            if ($membership->mandate === null || ! $membership->mandate->is_active) {
                throw new RuntimeException('Un mandat Fonds actif est requis.');
            }
            if ($membership->program === null || $membership->program->status !== FundProgram::STATUS_ACTIVE) {
                throw new RuntimeException('Le programme Fonds doit être actif pour réhabiliter cette adhésion.');
            }

            $membership->update([
                'status' => FundMembership::STATUS_ACTIVE,
                'suspended_at' => null,
            ]);
            $case->update([
                'status' => FundRehabilitationCase::STATUS_COMPLETED,
                'decision_note' => $note,
                'completed_at' => now(),
                'decided_by_account_id' => $actorAccountId,
            ]);
            $this->refreshReciprocity((string) $case->account_id);
            FundAuditEvent::record($actorAccountId, 'FundRehabilitationCompleted', 'fund_rehabilitation_case', $case->id, [
                'note' => $note,
            ]);

            return $case->refresh();
        });
    }

    public function transparency(): array
    {
        $completedOrders = FundOrder::query()->where('status', FundOrder::STATUS_COMPLETED);
        $realizedCount = (clone $completedOrders)->count();
        $realizedAmount = (int) (clone $completedOrders)->sum('total_minor');
        $participants = FundCollectionParticipant::query()->where('solidarity_paid_minor', '>', 0)->distinct('account_id')->count('account_id');
        $solidarity = (int) FundCollectionParticipant::query()->sum('solidarity_paid_minor');

        $categories = DB::table('fund_orders as orders')
            ->join('fund_wishes as wishes', 'wishes.id', '=', 'orders.fund_wish_id')
            ->join('fund_wish_categories as categories', 'categories.id', '=', 'wishes.category_id')
            ->where('orders.status', FundOrder::STATUS_COMPLETED)
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get(['categories.name', DB::raw('COUNT(*) AS realized_count')])
            ->map(fn ($row): array => ['name' => (string) $row->name, 'realized_count' => (int) $row->realized_count])
            ->values()
            ->all();

        return [
            'realized_wishes' => $realizedCount,
            'realized_amount_minor' => $realizedAmount,
            'solidarity_collected_minor' => $solidarity,
            'participant_count' => $participants,
            'reserve_balance_minor' => $this->realizations->reserveBalanceMinor(),
            'reserve_available_minor' => $this->availableReserveMinor(),
            'categories' => $categories,
            'privacy' => [
                'beneficiary_identity_exposed' => false,
                'medical_data_exposed' => false,
                'participant_financial_details_exposed' => false,
                'wasplex_global_revenue_exposed' => false,
            ],
        ];
    }
}
