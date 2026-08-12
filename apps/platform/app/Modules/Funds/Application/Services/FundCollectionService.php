<?php

declare(strict_types=1);

namespace App\Modules\Funds\Application\Services;

use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundCollectionDebit;
use App\Modules\Funds\Infrastructure\Models\FundCollectionParticipant;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundCollectionService
{
    private const RULE_VERSION = 'P014-D-v1';

    private const ACTIVE_COLLECTION_STATUSES = [
        FundCollectionSnapshot::STATUS_SCHEDULED,
        FundCollectionSnapshot::STATUS_COLLECTING,
        FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED,
    ];

    public function __construct(
        private readonly FundContributionService $contributions,
        private readonly LedgerPostingContract $posting,
    ) {}

    public function createSnapshot(FundWish $wish, string $actorAccountId): FundCollectionSnapshot
    {
        $existing = FundCollectionSnapshot::query()->where('fund_wish_id', $wish->id)->first();
        if ($existing !== null) {
            return $existing->load('participants');
        }

        return DB::transaction(function () use ($wish, $actorAccountId): FundCollectionSnapshot {
            $wish = FundWish::query()
                ->with(['membership.version', 'membership.program'])
                ->whereKey($wish->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = FundCollectionSnapshot::query()->where('fund_wish_id', $wish->id)->first();
            if ($existing !== null) {
                return $existing->load('participants');
            }

            if ($wish->status !== FundWish::STATUS_APPROVED) {
                throw new RuntimeException('Le vœu doit être validé avant de préparer une collecte.');
            }
            if ($wish->validated_amount_minor === null || $wish->provider_organization_id === null) {
                throw new RuntimeException('Un coût réel et un prestataire sélectionné sont requis avant la collecte.');
            }
            if (strtoupper((string) $wish->currency) !== 'XOF') {
                throw new RuntimeException('P014-D v1 collecte uniquement les vœux XOF, selon la parité 1 WP = 1 FCFA.');
            }

            $personalContribution = $this->contributions->wishContributionMinor($wish);
            $requiredContribution = (int) ($wish->required_personal_contribution_minor ?? 0);
            if ($requiredContribution > 0 && $personalContribution < $requiredContribution) {
                throw new RuntimeException('L’apport personnel requis doit être constitué avant la collecte collective.');
            }

            $validatedCost = (int) $wish->validated_amount_minor;
            $personalApplied = min($personalContribution, $validatedCost);
            $collectiveAmount = max(0, $validatedCost - $personalApplied);
            if ($collectiveAmount <= 0) {
                throw new RuntimeException('Aucun montant collectif ne reste à financer.');
            }

            $programId = (string) $wish->membership->fund_program_id;
            $country = strtoupper((string) $wish->country_code);
            $currency = strtoupper((string) $wish->currency);
            $version = $wish->membership->version;

            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'funds:collection:'.$programId.':'.$country.':'.$currency,
            ]);

            $activeCount = FundCollectionSnapshot::query()
                ->where('fund_program_id', $programId)
                ->where('country_code', $country)
                ->where('currency', $currency)
                ->whereIn('status', self::ACTIVE_COLLECTION_STATUSES)
                ->count();
            $maxSimultaneous = max(1, (int) ($version->max_simultaneous_collections ?? 1));
            if ($activeCount >= $maxSimultaneous) {
                throw new RuntimeException('La capacité de collectes simultanées de ce programme est atteinte.');
            }

            $candidates = $this->eligibleCandidates($wish, $collectiveAmount);
            if ($candidates->isEmpty()) {
                throw new RuntimeException('Aucun membre éligible ne peut participer à cette collecte.');
            }

            [$participants, $base, $remainder] = $this->stabilizeDistribution($candidates, $collectiveAmount);
            if ($participants->isEmpty()) {
                throw new RuntimeException('Les plafonds des mandats ne permettent pas de constituer cette collecte.');
            }

            $maxNoticeHours = max(
                (int) ($version->notice_hours ?? 24),
                (int) $participants->max(fn (array $participant): int => (int) $participant['notice_hours']),
            );
            $noticeAt = now();
            $scheduledAt = $noticeAt->copy()->addHours($maxNoticeHours);

            $hashPayload = [
                'fund_wish_id' => (string) $wish->id,
                'fund_program_id' => $programId,
                'program_version_id' => (string) $wish->membership->program_version_id,
                'country_code' => $country,
                'currency' => $currency,
                'validated_cost_minor' => $validatedCost,
                'personal_contribution_minor' => $personalApplied,
                'collective_amount_minor' => $collectiveAmount,
                'participants' => $participants->map(fn (array $item): array => [
                    'account_id' => $item['account_id'],
                    'membership_id' => $item['membership_id'],
                    'mandate_id' => $item['mandate_id'],
                    'solidarity_due_minor' => $item['solidarity_due_minor'],
                    'fee_due_minor' => $item['fee_due_minor'],
                    'mandate_cap_minor' => $item['mandate_cap_minor'],
                ])->values()->all(),
                'rule_version' => self::RULE_VERSION,
            ];
            $snapshotHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $snapshot = FundCollectionSnapshot::query()->create([
                'fund_wish_id' => $wish->id,
                'fund_program_id' => $programId,
                'program_version_id' => $wish->membership->program_version_id,
                'country_code' => $country,
                'currency' => $currency,
                'validated_cost_minor' => $validatedCost,
                'personal_contribution_minor' => $personalApplied,
                'partner_contribution_minor' => 0,
                'reserve_contribution_minor' => 0,
                'collective_amount_minor' => $collectiveAmount,
                'default_wasplex_fee_minor' => (int) ($version->wasplex_fee_minor ?? 0),
                'participant_count' => $participants->count(),
                'solidarity_base_minor' => $base,
                'solidarity_remainder_minor' => $remainder,
                'rule_version' => self::RULE_VERSION,
                'snapshot_hash' => $snapshotHash,
                'rules_snapshot' => [
                    'wp_to_xof' => 1,
                    'notice_hours' => $maxNoticeHours,
                    'max_simultaneous_collections' => $maxSimultaneous,
                    'rounding' => 'floor_then_distribute_one_wp_by_account_id',
                    'beneficiary_excluded' => true,
                    'fee_separate_from_solidarity' => true,
                ],
                'status' => FundCollectionSnapshot::STATUS_SCHEDULED,
                'notice_at' => $noticeAt,
                'scheduled_at' => $scheduledAt,
                'created_by_account_id' => $actorAccountId,
            ]);

            foreach ($participants->values() as $index => $participant) {
                FundCollectionParticipant::query()->create([
                    'snapshot_id' => $snapshot->id,
                    'account_id' => $participant['account_id'],
                    'fund_membership_id' => $participant['membership_id'],
                    'fund_mandate_id' => $participant['mandate_id'],
                    'ordinal' => $index + 1,
                    'solidarity_due_minor' => $participant['solidarity_due_minor'],
                    'fee_due_minor' => $participant['fee_due_minor'],
                    'total_due_minor' => $participant['solidarity_due_minor'] + $participant['fee_due_minor'],
                    'mandate_cap_minor' => $participant['mandate_cap_minor'],
                    'daily_remaining_minor' => $participant['daily_remaining_minor'],
                    'monthly_remaining_minor' => $participant['monthly_remaining_minor'],
                    'annual_remaining_minor' => $participant['annual_remaining_minor'],
                    'rule_snapshot' => [
                        'program_version_id' => $participant['program_version_id'],
                        'notice_hours' => $participant['notice_hours'],
                        'arrears_grace_days' => $participant['arrears_grace_days'],
                        'wasplex_fee_minor' => $participant['fee_due_minor'],
                    ],
                    'status' => FundCollectionParticipant::STATUS_PENDING,
                ]);
            }

            return $snapshot->load('participants');
        });
    }

    public function execute(FundCollectionSnapshot $snapshot, string $actorAccountId): FundCollectionSnapshot
    {
        $snapshot = FundCollectionSnapshot::query()->whereKey($snapshot->id)->firstOrFail();
        if ($snapshot->status === FundCollectionSnapshot::STATUS_FUNDED) {
            return $snapshot->load(['participants.arrear', 'debits']);
        }
        if ($snapshot->status === FundCollectionSnapshot::STATUS_CANCELLED) {
            throw new RuntimeException('Cette collecte est annulée.');
        }
        if ($snapshot->scheduled_at->isFuture()) {
            throw new RuntimeException('Le préavis de collecte n’est pas encore terminé.');
        }

        $snapshot->update([
            'status' => FundCollectionSnapshot::STATUS_COLLECTING,
            'started_at' => $snapshot->started_at ?? now(),
        ]);

        foreach ($snapshot->participants()->orderBy('ordinal')->get() as $participant) {
            $this->debitParticipant($participant, $actorAccountId);
        }

        return $this->refreshSnapshotStatus($snapshot);
    }

    private function eligibleCandidates(FundWish $wish, int $collectiveAmount): Collection
    {
        $country = strtoupper((string) $wish->country_code);
        $memberships = FundMembership::query()
            ->with(['account', 'version', 'mandate', 'subscription.entitlements'])
            ->where('fund_program_id', $wish->membership->fund_program_id)
            ->where('status', FundMembership::STATUS_ACTIVE)
            ->where('account_id', '!=', $wish->account_id)
            ->get();

        $candidates = $memberships->filter(function (FundMembership $membership) use ($country): bool {
            if ($membership->account === null || ! $membership->account->isActive()) {
                return false;
            }
            if (strtoupper((string) $membership->account->country_code) !== $country) {
                return false;
            }
            if ($membership->version === null || strtoupper((string) $membership->version->currency) !== 'XOF') {
                return false;
            }
            if ($membership->mandate === null || ! $membership->mandate->is_active) {
                return false;
            }
            if ($membership->subscription === null || ! $membership->subscription->isActive()) {
                return false;
            }
            if ($membership->subscription->current_period_end !== null && $membership->subscription->current_period_end->isPast()) {
                return false;
            }
            $fondsEntitled = $membership->subscription->entitlements
                ->contains(fn ($entitlement): bool => $entitlement->key === SubscriptionEntitlement::KEY_FONDS_ELIGIBLE && (bool) $entitlement->enabled);
            if (! $fondsEntitled) {
                return false;
            }

            return ! FundArrear::query()
                ->where('account_id', $membership->account_id)
                ->where('status', FundArrear::STATUS_OPEN)
                ->whereNotNull('grace_ends_at')
                ->where('grace_ends_at', '<', now())
                ->exists();
        })->sortBy('account_id')->values();

        if ($collectiveAmount < $candidates->count()) {
            $candidates = $candidates->take($collectiveAmount)->values();
        }

        return $candidates->map(function (FundMembership $membership): array {
            $version = $membership->version;
            $mandate = $membership->mandate;
            $dailyRemaining = $this->remainingPeriodCapacity($membership->account_id, $version->daily_cap_minor, now()->startOfDay());
            $monthlyRemaining = $this->remainingPeriodCapacity($membership->account_id, $version->monthly_cap_minor, now()->startOfMonth());
            $annualRemaining = $this->remainingPeriodCapacity($membership->account_id, $version->annual_cap_minor, now()->startOfYear());

            $limits = [(int) $mandate->personal_cap_minor];
            if ($version->max_debit_minor !== null) {
                $limits[] = (int) $version->max_debit_minor;
            }
            foreach ([$dailyRemaining, $monthlyRemaining, $annualRemaining] as $remaining) {
                if ($remaining !== null) {
                    $limits[] = $remaining;
                }
            }

            return [
                'account_id' => (string) $membership->account_id,
                'membership_id' => (string) $membership->id,
                'mandate_id' => (string) $mandate->id,
                'program_version_id' => (string) $version->id,
                'mandate_cap_minor' => max(0, min($limits)),
                'daily_remaining_minor' => $dailyRemaining,
                'monthly_remaining_minor' => $monthlyRemaining,
                'annual_remaining_minor' => $annualRemaining,
                'fee_due_minor' => (int) $version->wasplex_fee_minor,
                'notice_hours' => max(1, (int) $version->notice_hours),
                'arrears_grace_days' => max(1, (int) ($version->arrears_grace_days ?? 7)),
            ];
        })->values();
    }

    private function stabilizeDistribution(Collection $candidates, int $collectiveAmount): array
    {
        $working = $candidates->sortBy('account_id')->values();

        while ($working->isNotEmpty()) {
            $count = $working->count();
            $base = intdiv($collectiveAmount, $count);
            $remainder = $collectiveAmount % $count;

            $distributed = $working->values()->map(function (array $candidate, int $index) use ($base, $remainder): array {
                $solidarity = $base + ($index < $remainder ? 1 : 0);

                return [
                    ...$candidate,
                    'solidarity_due_minor' => $solidarity,
                ];
            });

            $invalidIds = $distributed
                ->filter(fn (array $candidate): bool => $candidate['solidarity_due_minor'] <= 0
                    || ($candidate['solidarity_due_minor'] + $candidate['fee_due_minor']) > $candidate['mandate_cap_minor'])
                ->pluck('account_id')
                ->all();

            if ($invalidIds === []) {
                return [$distributed, $base, $remainder];
            }

            $working = $working
                ->reject(fn (array $candidate): bool => in_array($candidate['account_id'], $invalidIds, true))
                ->values();
        }

        return [collect(), 0, 0];
    }

    private function remainingPeriodCapacity(string $accountId, mixed $capMinor, mixed $periodStart): ?int
    {
        if ($capMinor === null) {
            return null;
        }

        $used = (int) FundCollectionDebit::query()
            ->where('account_id', $accountId)
            ->where('attempted_at', '>=', $periodStart)
            ->sum(DB::raw('debited_solidarity_minor + debited_fee_minor'));

        return max(0, (int) $capMinor - $used);
    }

    private function debitParticipant(FundCollectionParticipant $participant, string $actorAccountId): FundCollectionDebit
    {
        return DB::transaction(function () use ($participant, $actorAccountId): FundCollectionDebit {
            $participant = FundCollectionParticipant::query()
                ->with(['mandate', 'snapshot'])
                ->whereKey($participant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingSolidarity = max(0, (int) $participant->solidarity_due_minor - (int) $participant->solidarity_paid_minor);
            $remainingFee = max(0, (int) $participant->fee_due_minor - (int) $participant->fee_paid_minor);
            if ($remainingSolidarity === 0) {
                return $this->recordNoopDebit($participant, 'already_paid');
            }

            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:'.$participant->account_id]);
            $fundBalance = $this->contributions->fundBalanceMinor((string) $participant->account_id);
            $mandateActive = $participant->mandate !== null && (bool) $participant->mandate->is_active;
            $idempotencyKey = implode(':', [
                'fund-collection',
                $participant->snapshot_id,
                $participant->id,
                $participant->solidarity_paid_minor,
                $participant->fee_paid_minor,
                $fundBalance,
                $mandateActive ? 'active' : 'inactive',
            ]);

            $existing = FundCollectionDebit::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            if (! $mandateActive) {
                return $this->recordFailedDebit($participant, $idempotencyKey, $remainingSolidarity, $remainingFee, 'mandate_inactive');
            }

            $solidarityDebit = 0;
            $feeDebit = 0;
            if ($remainingFee > 0) {
                if ($fundBalance > $remainingFee) {
                    $solidarityDebit = min($remainingSolidarity, $fundBalance - $remainingFee);
                    if ($solidarityDebit > 0) {
                        $feeDebit = $remainingFee;
                    }
                }
            } else {
                $solidarityDebit = min($remainingSolidarity, $fundBalance);
            }

            if ($solidarityDebit <= 0) {
                return $this->recordFailedDebit($participant, $idempotencyKey, $remainingSolidarity, $remainingFee, 'insufficient_fund_balance');
            }

            $totalDebit = $solidarityDebit + $feeDebit;
            $entries = [
                LedgerEntryInput::debit(
                    LedgerAccountReference::forIdentityAccount(
                        FundContributionService::FUND_BALANCE_ACCOUNT_CODE,
                        (string) $participant->account_id,
                        UserWalletQueryService::ACCOUNT_TYPE_CODE,
                        'WP',
                    ),
                    Money::of($totalDebit, 'WP'),
                    'Contribution automatique Fonds',
                ),
                LedgerEntryInput::credit(
                    LedgerAccountReference::system('fund.collection.pool.'.$participant->snapshot_id.'.wp', 'FONDS', 'WP'),
                    Money::of($solidarityDebit, 'WP'),
                    'Solidarité collectée pour le vœu Fonds',
                ),
            ];
            if ($feeDebit > 0) {
                $entries[] = LedgerEntryInput::credit(
                    LedgerAccountReference::system('fund.wasplex.collection-fee.wp', 'REVENUE', 'WP'),
                    Money::of($feeDebit, 'WP'),
                    'Frais fixe de fonctionnement Fonds',
                );
            }

            $transaction = $this->posting->post(new PostLedgerTransaction(
                type: 'FUND_AUTOMATIC_COLLECTION_DEBIT',
                sourceModule: 'funds',
                idempotencyKey: $idempotencyKey,
                entries: $entries,
                businessReference: 'fund-collection:'.$participant->snapshot_id,
                createdBy: $actorAccountId,
                metadata: [
                    'snapshot_id' => $participant->snapshot_id,
                    'participant_id' => $participant->id,
                    'account_id' => $participant->account_id,
                    'solidarity_minor' => $solidarityDebit,
                    'wasplex_fee_minor' => $feeDebit,
                ],
                ruleVersion: self::RULE_VERSION,
            ));

            $newSolidarityPaid = (int) $participant->solidarity_paid_minor + $solidarityDebit;
            $newFeePaid = (int) $participant->fee_paid_minor + $feeDebit;
            $arrears = max(0, (int) $participant->solidarity_due_minor - $newSolidarityPaid);
            $status = $arrears === 0
                ? FundCollectionParticipant::STATUS_PAID
                : FundCollectionParticipant::STATUS_PARTIAL;

            $participant->update([
                'solidarity_paid_minor' => $newSolidarityPaid,
                'fee_paid_minor' => $newFeePaid,
                'arrears_minor' => $arrears,
                'status' => $status,
                'last_attempted_at' => now(),
                'paid_at' => $arrears === 0 ? now() : null,
            ]);
            $this->synchronizeArrear($participant->fresh(), $arrears);

            return FundCollectionDebit::query()->create([
                'snapshot_id' => $participant->snapshot_id,
                'participant_id' => $participant->id,
                'account_id' => $participant->account_id,
                'idempotency_key' => $idempotencyKey,
                'requested_solidarity_minor' => $remainingSolidarity,
                'requested_fee_minor' => $remainingFee,
                'debited_solidarity_minor' => $solidarityDebit,
                'debited_fee_minor' => $feeDebit,
                'arrears_minor' => $arrears,
                'ledger_transaction_id' => $transaction->id,
                'status' => $arrears === 0 ? FundCollectionDebit::STATUS_SUCCESS : FundCollectionDebit::STATUS_PARTIAL,
                'attempted_at' => now(),
            ]);
        });
    }

    private function recordFailedDebit(
        FundCollectionParticipant $participant,
        string $idempotencyKey,
        int $remainingSolidarity,
        int $remainingFee,
        string $failureCode,
    ): FundCollectionDebit {
        $participant->update([
            'arrears_minor' => $remainingSolidarity,
            'status' => FundCollectionParticipant::STATUS_ARREARS,
            'last_attempted_at' => now(),
        ]);
        $this->synchronizeArrear($participant->fresh(), $remainingSolidarity);

        return FundCollectionDebit::query()->create([
            'snapshot_id' => $participant->snapshot_id,
            'participant_id' => $participant->id,
            'account_id' => $participant->account_id,
            'idempotency_key' => $idempotencyKey,
            'requested_solidarity_minor' => $remainingSolidarity,
            'requested_fee_minor' => $remainingFee,
            'debited_solidarity_minor' => 0,
            'debited_fee_minor' => 0,
            'arrears_minor' => $remainingSolidarity,
            'status' => FundCollectionDebit::STATUS_FAILED,
            'failure_code' => $failureCode,
            'attempted_at' => now(),
        ]);
    }

    private function recordNoopDebit(FundCollectionParticipant $participant, string $reason): FundCollectionDebit
    {
        $key = 'fund-collection:'.$participant->snapshot_id.':'.$participant->id.':noop:'.$reason;

        return FundCollectionDebit::query()->firstOrCreate([
            'idempotency_key' => $key,
        ], [
            'snapshot_id' => $participant->snapshot_id,
            'participant_id' => $participant->id,
            'account_id' => $participant->account_id,
            'requested_solidarity_minor' => 0,
            'requested_fee_minor' => 0,
            'debited_solidarity_minor' => 0,
            'debited_fee_minor' => 0,
            'arrears_minor' => 0,
            'status' => FundCollectionDebit::STATUS_SUCCESS,
            'failure_code' => $reason,
            'attempted_at' => now(),
        ]);
    }

    private function synchronizeArrear(FundCollectionParticipant $participant, int $remainingSolidarity): void
    {
        $graceDays = max(1, (int) ($participant->rule_snapshot['arrears_grace_days'] ?? 7));
        $arrear = FundArrear::query()->firstOrNew(['participant_id' => $participant->id]);
        $arrear->fill([
            'snapshot_id' => $participant->snapshot_id,
            'account_id' => $participant->account_id,
            'due_solidarity_minor' => (int) $participant->solidarity_due_minor,
            'settled_solidarity_minor' => (int) $participant->solidarity_paid_minor,
            'status' => $remainingSolidarity === 0 ? FundArrear::STATUS_SETTLED : FundArrear::STATUS_OPEN,
            'grace_ends_at' => $arrear->grace_ends_at ?? now()->addDays($graceDays),
            'settled_at' => $remainingSolidarity === 0 ? now() : null,
        ]);
        $arrear->save();
    }

    private function refreshSnapshotStatus(FundCollectionSnapshot $snapshot): FundCollectionSnapshot
    {
        $snapshot->refresh();
        $paid = (int) $snapshot->participants()->sum('solidarity_paid_minor');
        $target = (int) $snapshot->collective_amount_minor;

        if ($paid >= $target) {
            $snapshot->update([
                'status' => FundCollectionSnapshot::STATUS_FUNDED,
                'completed_at' => now(),
            ]);
        } elseif ($paid > 0) {
            $snapshot->update([
                'status' => FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED,
            ]);
        } else {
            $snapshot->update([
                'status' => FundCollectionSnapshot::STATUS_FAILED,
            ]);
        }

        return $snapshot->refresh()->load(['participants.arrear', 'debits']);
    }
}
