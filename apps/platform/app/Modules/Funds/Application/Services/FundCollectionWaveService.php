<?php

declare(strict_types=1);

namespace App\Modules\Funds\Application\Services;

use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundCollectionDebit;
use App\Modules\Funds\Infrastructure\Models\FundCollectionParticipant;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundCollectionWaveService
{
    private const RULE_VERSION = 'P014-F-wave-v1';

    private const WAVE_SCHEDULED = 'scheduled';

    private const WAVE_COLLECTING = 'collecting';

    private const WAVE_PARTIAL = 'partially_funded';

    private const WAVE_FUNDED = 'funded';

    private const WAVE_FAILED = 'failed';

    private const WAVE_CANCELLED = 'cancelled';

    public function __construct(
        private readonly FundContributionService $contributions,
        private readonly LedgerPostingContract $posting,
    ) {}

    public function createSecondWave(FundCollectionSnapshot $snapshot, string $actorAccountId): array
    {
        return DB::transaction(function () use ($snapshot, $actorAccountId): array {
            $snapshot = FundCollectionSnapshot::query()
                ->with(['wish.membership.version', 'wish.membership.program'])
                ->whereKey($snapshot->id)
                ->lockForUpdate()
                ->firstOrFail();

            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'funds:collection-wave:'.$snapshot->id,
            ]);

            if ($snapshot->status === FundCollectionSnapshot::STATUS_FUNDED) {
                throw new RuntimeException('Cette collecte est déjà entièrement financée.');
            }
            if ($snapshot->started_at === null) {
                throw new RuntimeException('La première vague doit avoir été exécutée avant de préparer une vague de remplacement.');
            }
            if ((int) $snapshot->collective_amount_minor <= 0 || ! $snapshot->wish instanceof FundWish) {
                throw new RuntimeException('Cette collecte ne nécessite pas de vague supplémentaire.');
            }

            $latestWave = max(1, (int) FundCollectionParticipant::query()
                ->where('snapshot_id', $snapshot->id)
                ->max('wave_number'));
            if ($latestWave > 1) {
                $activeLatest = FundCollectionParticipant::query()
                    ->where('snapshot_id', $snapshot->id)
                    ->where('wave_number', $latestWave)
                    ->whereIn('wave_status', [self::WAVE_SCHEDULED, self::WAVE_COLLECTING])
                    ->exists();
                if ($activeLatest) {
                    return $this->wavePayload($snapshot, $latestWave);
                }
            }

            $collected = (int) FundCollectionParticipant::query()
                ->where('snapshot_id', $snapshot->id)
                ->sum('solidarity_paid_minor');
            $missing = max(0, (int) $snapshot->collective_amount_minor - $collected);
            if ($missing <= 0) {
                $snapshot->update([
                    'status' => FundCollectionSnapshot::STATUS_FUNDED,
                    'completed_at' => $snapshot->completed_at ?? now(),
                ]);
                throw new RuntimeException('Le besoin collectif est déjà couvert.');
            }

            $nextWave = $latestWave + 1;
            $this->replaceOutstandingArrears($snapshot, $nextWave, $actorAccountId);

            $candidates = $this->eligibleCandidates($snapshot->wish, $missing, (string) $snapshot->id);
            if ($candidates->isEmpty()) {
                throw new RuntimeException('Aucun membre éligible ne peut participer à cette nouvelle vague.');
            }

            [$participants, $base, $remainder] = $this->stabilizeDistribution($candidates, $missing);
            if ($participants->isEmpty()) {
                throw new RuntimeException('Les plafonds des mandats ne permettent pas de constituer cette nouvelle vague.');
            }

            $version = $snapshot->wish->membership->version;
            $noticeHours = max(
                (int) ($version->notice_hours ?? 24),
                (int) $participants->max(fn (array $participant): int => (int) $participant['notice_hours']),
            );
            $noticeAt = now();
            $scheduledAt = $noticeAt->copy()->addHours($noticeHours);
            $waveHash = hash('sha256', json_encode([
                'snapshot_id' => (string) $snapshot->id,
                'fund_wish_id' => (string) $snapshot->fund_wish_id,
                'wave_number' => $nextWave,
                'wave_target_minor' => $missing,
                'notice_at' => $noticeAt->toISOString(),
                'scheduled_at' => $scheduledAt->toISOString(),
                'participants' => $participants->map(fn (array $participant): array => [
                    'account_id' => $participant['account_id'],
                    'membership_id' => $participant['membership_id'],
                    'mandate_id' => $participant['mandate_id'],
                    'solidarity_due_minor' => $participant['solidarity_due_minor'],
                    'fee_due_minor' => $participant['fee_due_minor'],
                    'mandate_cap_minor' => $participant['mandate_cap_minor'],
                ])->values()->all(),
                'rule_version' => self::RULE_VERSION,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $ordinal = (int) FundCollectionParticipant::query()
                ->where('snapshot_id', $snapshot->id)
                ->max('ordinal');

            foreach ($participants->values() as $index => $participant) {
                FundCollectionParticipant::query()->create([
                    'snapshot_id' => $snapshot->id,
                    'wave_number' => $nextWave,
                    'wave_target_minor' => $missing,
                    'wave_hash' => $waveHash,
                    'wave_notice_at' => $noticeAt,
                    'wave_scheduled_at' => $scheduledAt,
                    'wave_status' => self::WAVE_SCHEDULED,
                    'account_id' => $participant['account_id'],
                    'fund_membership_id' => $participant['membership_id'],
                    'fund_mandate_id' => $participant['mandate_id'],
                    'ordinal' => $ordinal + $index + 1,
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
                        'wave_number' => $nextWave,
                        'wave_hash' => $waveHash,
                        'replacement_wave' => true,
                    ],
                    'status' => FundCollectionParticipant::STATUS_PENDING,
                ]);
            }

            $snapshot->update([
                'status' => FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED,
                'completed_at' => null,
            ]);

            FundAuditEvent::record($actorAccountId, 'FundCollectionWaveCreated', 'fund_collection_snapshot', $snapshot->id, [
                'wave_number' => $nextWave,
                'wave_target_minor' => $missing,
                'participant_count' => $participants->count(),
                'wave_hash' => $waveHash,
                'solidarity_base_minor' => $base,
                'solidarity_remainder_minor' => $remainder,
            ]);

            return $this->wavePayload($snapshot, $nextWave);
        });
    }

    public function executeWave(FundCollectionSnapshot $snapshot, int $waveNumber, string $actorAccountId): array
    {
        if ($waveNumber < 2) {
            throw new RuntimeException('La première vague reste gérée par le moteur de collecte initial.');
        }

        $snapshot = FundCollectionSnapshot::query()->whereKey($snapshot->id)->firstOrFail();
        if ($snapshot->status === FundCollectionSnapshot::STATUS_FUNDED) {
            return $this->wavePayload($snapshot, $waveNumber);
        }

        $participants = FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('wave_number', $waveNumber)
            ->orderBy('ordinal')
            ->get();
        if ($participants->isEmpty()) {
            throw new RuntimeException('Cette vague de collecte est introuvable.');
        }
        if ($participants->contains(fn (FundCollectionParticipant $participant): bool => $participant->wave_status === self::WAVE_CANCELLED)) {
            throw new RuntimeException('Cette vague a été remplacée ou annulée.');
        }

        $scheduledAt = $participants->first()->wave_scheduled_at;
        if ($scheduledAt !== null && CarbonImmutable::parse((string) $scheduledAt)->isFuture()) {
            throw new RuntimeException('Le préavis de cette vague n’est pas encore terminé.');
        }

        FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('wave_number', $waveNumber)
            ->update(['wave_status' => self::WAVE_COLLECTING]);

        foreach ($participants as $participant) {
            $this->debitParticipant($participant, $actorAccountId);
        }

        return DB::transaction(function () use ($snapshot, $waveNumber, $actorAccountId): array {
            $snapshot = FundCollectionSnapshot::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'funds:collection-target:'.$snapshot->id,
            ]);

            $collected = (int) FundCollectionParticipant::query()
                ->where('snapshot_id', $snapshot->id)
                ->sum('solidarity_paid_minor');
            $target = (int) $snapshot->collective_amount_minor;
            $waveParticipants = FundCollectionParticipant::query()
                ->where('snapshot_id', $snapshot->id)
                ->where('wave_number', $waveNumber)
                ->get();
            $wavePaid = (int) $waveParticipants->sum('solidarity_paid_minor');
            $waveTarget = (int) ($waveParticipants->first()?->wave_target_minor ?? 0);

            if ($collected >= $target) {
                $snapshot->update([
                    'status' => FundCollectionSnapshot::STATUS_FUNDED,
                    'completed_at' => now(),
                ]);
                FundCollectionParticipant::query()
                    ->where('snapshot_id', $snapshot->id)
                    ->where('wave_number', $waveNumber)
                    ->update(['wave_status' => self::WAVE_FUNDED]);
                $this->closeUnneededObligations($snapshot, $waveNumber);
            } elseif ($wavePaid > 0) {
                FundCollectionParticipant::query()
                    ->where('snapshot_id', $snapshot->id)
                    ->where('wave_number', $waveNumber)
                    ->update(['wave_status' => self::WAVE_PARTIAL]);
                $snapshot->update(['status' => FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED]);
            } else {
                FundCollectionParticipant::query()
                    ->where('snapshot_id', $snapshot->id)
                    ->where('wave_number', $waveNumber)
                    ->update(['wave_status' => self::WAVE_FAILED]);
                $snapshot->update([
                    'status' => $collected > 0
                        ? FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED
                        : FundCollectionSnapshot::STATUS_FAILED,
                ]);
            }

            FundAuditEvent::record($actorAccountId, 'FundCollectionWaveExecuted', 'fund_collection_snapshot', $snapshot->id, [
                'wave_number' => $waveNumber,
                'wave_target_minor' => $waveTarget,
                'wave_paid_minor' => $wavePaid,
                'global_collected_minor' => $collected,
                'global_target_minor' => $target,
                'snapshot_status' => $snapshot->fresh()->status,
            ]);

            return $this->wavePayload($snapshot->fresh(), $waveNumber);
        });
    }

    private function eligibleCandidates(FundWish $wish, int $collectiveAmount, string $snapshotId): Collection
    {
        $country = strtoupper((string) $wish->country_code);
        $memberships = FundMembership::query()
            ->with(['account', 'version', 'mandate', 'subscription.entitlements'])
            ->where('fund_program_id', $wish->membership->fund_program_id)
            ->where('status', FundMembership::STATUS_ACTIVE)
            ->where('account_id', '!=', $wish->account_id)
            ->get();

        $previousFeeAccounts = FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshotId)
            ->where('fee_paid_minor', '>', 0)
            ->pluck('account_id')
            ->mapWithKeys(fn ($accountId): array => [(string) $accountId => true]);

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

        return $candidates->map(function (FundMembership $membership) use ($previousFeeAccounts): array {
            $version = $membership->version;
            $mandate = $membership->mandate;
            $dailyRemaining = $this->remainingPeriodCapacity((string) $membership->account_id, $version->daily_cap_minor, now()->startOfDay());
            $monthlyRemaining = $this->remainingPeriodCapacity((string) $membership->account_id, $version->monthly_cap_minor, now()->startOfMonth());
            $annualRemaining = $this->remainingPeriodCapacity((string) $membership->account_id, $version->annual_cap_minor, now()->startOfYear());

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
                'fee_due_minor' => $previousFeeAccounts->has((string) $membership->account_id)
                    ? 0
                    : (int) $version->wasplex_fee_minor,
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
                return [
                    ...$candidate,
                    'solidarity_due_minor' => $base + ($index < $remainder ? 1 : 0),
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

            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                'funds:collection-target:'.$participant->snapshot_id,
            ]);

            $participantRemaining = max(0, (int) $participant->solidarity_due_minor - (int) $participant->solidarity_paid_minor);
            if ($participantRemaining === 0) {
                return $this->recordNoopDebit($participant, 'already_paid');
            }

            $globalCollected = (int) FundCollectionParticipant::query()
                ->where('snapshot_id', $participant->snapshot_id)
                ->sum('solidarity_paid_minor');
            $globalRemaining = max(0, (int) $participant->snapshot->collective_amount_minor - $globalCollected);
            if ($globalRemaining <= 0) {
                return $this->skipParticipantRemainder($participant, 'target_already_funded');
            }

            $remainingSolidarity = min($participantRemaining, $globalRemaining);
            $remainingFee = max(0, (int) $participant->fee_due_minor - (int) $participant->fee_paid_minor);
            if ($remainingSolidarity === 0) {
                return $this->skipParticipantRemainder($participant, 'nothing_remaining');
            }

            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:'.$participant->account_id]);
            $fundBalance = $this->contributions->fundBalanceMinor((string) $participant->account_id);
            $mandateActive = $participant->mandate !== null && (bool) $participant->mandate->is_active;
            $idempotencyKey = implode(':', [
                'fund-collection-wave',
                $participant->snapshot_id,
                $participant->wave_number,
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
                    'Contribution automatique Fonds — vague complémentaire',
                ),
                LedgerEntryInput::credit(
                    LedgerAccountReference::system('fund.collection.pool.'.$participant->snapshot_id.'.wp', 'FONDS', 'WP'),
                    Money::of($solidarityDebit, 'WP'),
                    'Solidarité collectée — vague complémentaire',
                ),
            ];
            if ($feeDebit > 0) {
                $entries[] = LedgerEntryInput::credit(
                    LedgerAccountReference::system('fund.wasplex.collection-fee.wp', 'REVENUE', 'WP'),
                    Money::of($feeDebit, 'WP'),
                    'Frais fixe Fonds — première participation au vœu',
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
                    'wave_number' => (int) $participant->wave_number,
                    'solidarity_minor' => $solidarityDebit,
                    'wasplex_fee_minor' => $feeDebit,
                    'global_remaining_before_minor' => $globalRemaining,
                ],
                ruleVersion: self::RULE_VERSION,
            ));

            $newSolidarityPaid = (int) $participant->solidarity_paid_minor + $solidarityDebit;
            $newFeePaid = (int) $participant->fee_paid_minor + $feeDebit;
            $effectiveRemaining = max(0, $remainingSolidarity - $solidarityDebit);
            $targetSatisfied = $solidarityDebit >= $globalRemaining;
            $arrears = $targetSatisfied ? 0 : $effectiveRemaining;
            $status = $arrears === 0
                ? ($newSolidarityPaid >= (int) $participant->solidarity_due_minor
                    ? FundCollectionParticipant::STATUS_PAID
                    : FundCollectionParticipant::STATUS_SKIPPED)
                : FundCollectionParticipant::STATUS_PARTIAL;

            $participant->update([
                'solidarity_paid_minor' => $newSolidarityPaid,
                'fee_paid_minor' => $newFeePaid,
                'arrears_minor' => $arrears,
                'status' => $status,
                'last_attempted_at' => now(),
                'paid_at' => $status === FundCollectionParticipant::STATUS_PAID ? now() : null,
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

    private function recordNoopDebit(FundCollectionParticipant $participant, string $reason): FundCollectionDebit
    {
        $key = implode(':', [
            'fund-collection-wave',
            $participant->snapshot_id,
            $participant->wave_number,
            $participant->id,
            'noop',
            $reason,
        ]);

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

    private function skipParticipantRemainder(FundCollectionParticipant $participant, string $reason): FundCollectionDebit
    {
        $participant->update([
            'arrears_minor' => 0,
            'status' => FundCollectionParticipant::STATUS_SKIPPED,
            'last_attempted_at' => now(),
        ]);
        $this->synchronizeArrear($participant->fresh(), 0);

        $key = implode(':', [
            'fund-collection-wave',
            $participant->snapshot_id,
            $participant->wave_number,
            $participant->id,
            'noop',
            $reason,
        ]);

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
        $graceDays = max(1, (int) data_get($participant->rule_snapshot, 'arrears_grace_days', 7));
        $arrear = FundArrear::query()->firstOrNew(['participant_id' => $participant->id]);
        $arrear->snapshot_id = $participant->snapshot_id;
        $arrear->account_id = $participant->account_id;
        $arrear->due_solidarity_minor = (int) $participant->solidarity_due_minor;
        $arrear->settled_solidarity_minor = min(
            (int) $participant->solidarity_due_minor,
            (int) $participant->solidarity_paid_minor,
        );
        $arrear->status = $remainingSolidarity > 0 ? FundArrear::STATUS_OPEN : FundArrear::STATUS_SETTLED;
        $arrear->grace_ends_at = $remainingSolidarity > 0
            ? ($arrear->grace_ends_at ?? now()->addDays($graceDays))
            : $arrear->grace_ends_at;
        $arrear->settled_at = $remainingSolidarity === 0 ? now() : null;
        $arrear->save();
    }

    private function replaceOutstandingArrears(FundCollectionSnapshot $snapshot, int $nextWave, string $actorAccountId): void
    {
        $arrears = FundArrear::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('status', FundArrear::STATUS_OPEN)
            ->lockForUpdate()
            ->get();

        foreach ($arrears as $arrear) {
            $arrear->update([
                'status' => FundArrear::STATUS_WAIVED,
                'settled_at' => now(),
            ]);
            FundCollectionParticipant::query()
                ->whereKey($arrear->participant_id)
                ->update([
                    'arrears_minor' => 0,
                    'status' => FundCollectionParticipant::STATUS_SKIPPED,
                    'wave_status' => self::WAVE_CANCELLED,
                ]);
        }

        if ($arrears->isNotEmpty()) {
            FundAuditEvent::record($actorAccountId, 'FundCollectionArrearsReplacedByWave', 'fund_collection_snapshot', $snapshot->id, [
                'next_wave_number' => $nextWave,
                'waived_arrear_count' => $arrears->count(),
                'waived_minor' => (int) $arrears->sum(fn (FundArrear $arrear): int => max(
                    0,
                    (int) $arrear->due_solidarity_minor - (int) $arrear->settled_solidarity_minor,
                )),
            ]);
        }
    }

    private function closeUnneededObligations(FundCollectionSnapshot $snapshot, int $fundingWave): void
    {
        $openParticipants = FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshot->id)
            ->whereColumn('solidarity_paid_minor', '<', 'solidarity_due_minor')
            ->whereIn('status', [
                FundCollectionParticipant::STATUS_PENDING,
                FundCollectionParticipant::STATUS_PARTIAL,
                FundCollectionParticipant::STATUS_ARREARS,
            ])
            ->get();

        foreach ($openParticipants as $participant) {
            $participant->update([
                'arrears_minor' => 0,
                'status' => FundCollectionParticipant::STATUS_SKIPPED,
                'wave_status' => (int) $participant->wave_number > $fundingWave
                    ? self::WAVE_CANCELLED
                    : $participant->wave_status,
            ]);
            FundArrear::query()
                ->where('participant_id', $participant->id)
                ->where('status', FundArrear::STATUS_OPEN)
                ->update([
                    'status' => FundArrear::STATUS_WAIVED,
                    'settled_at' => now(),
                ]);
        }

        FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('wave_number', '>', $fundingWave)
            ->update(['wave_status' => self::WAVE_CANCELLED]);
    }

    private function wavePayload(FundCollectionSnapshot $snapshot, int $waveNumber): array
    {
        $participants = FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshot->id)
            ->where('wave_number', $waveNumber)
            ->orderBy('ordinal')
            ->get();
        if ($participants->isEmpty()) {
            throw new RuntimeException('Cette vague de collecte est introuvable.');
        }

        $globalCollected = (int) FundCollectionParticipant::query()
            ->where('snapshot_id', $snapshot->id)
            ->sum('solidarity_paid_minor');

        return [
            'snapshot_id' => $snapshot->id,
            'fund_wish_id' => $snapshot->fund_wish_id,
            'snapshot_status' => $snapshot->status,
            'wave_number' => $waveNumber,
            'wave_status' => (string) $participants->first()->wave_status,
            'wave_target_minor' => (int) ($participants->first()->wave_target_minor ?? 0),
            'wave_hash' => (string) ($participants->first()->wave_hash ?? ''),
            'notice_at' => $participants->first()->wave_notice_at,
            'scheduled_at' => $participants->first()->wave_scheduled_at,
            'participant_count' => $participants->count(),
            'solidarity_due_minor' => (int) $participants->sum('solidarity_due_minor'),
            'solidarity_paid_minor' => (int) $participants->sum('solidarity_paid_minor'),
            'fees_due_minor' => (int) $participants->sum('fee_due_minor'),
            'fees_paid_minor' => (int) $participants->sum('fee_paid_minor'),
            'arrears_minor' => (int) $participants->sum('arrears_minor'),
            'global_target_minor' => (int) $snapshot->collective_amount_minor,
            'global_collected_minor' => $globalCollected,
            'global_remaining_minor' => max(0, (int) $snapshot->collective_amount_minor - $globalCollected),
            'participants' => $participants->map(fn (FundCollectionParticipant $participant): array => [
                'id' => $participant->id,
                'account_id' => $participant->account_id,
                'status' => $participant->status,
                'solidarity_due_minor' => (int) $participant->solidarity_due_minor,
                'solidarity_paid_minor' => (int) $participant->solidarity_paid_minor,
                'fee_due_minor' => (int) $participant->fee_due_minor,
                'fee_paid_minor' => (int) $participant->fee_paid_minor,
                'arrears_minor' => (int) $participant->arrears_minor,
            ])->values(),
        ];
    }
}
