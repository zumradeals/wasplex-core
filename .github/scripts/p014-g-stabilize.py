from pathlib import Path

ROOT = Path('apps/platform')


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f'Pattern not found in {path}: {old[:160]!r}')
    path.write_text(text.replace(old, new, 1))


# 1) Queue fairness, reserve serialization and rehabilitation concurrency.
p = ROOT / 'app/Modules/Funds/Application/Services/FundPilotageService.php'
text = p.read_text()
text = text.replace(
    "$priorityScore = $lane === FundWishQueueEntry::LANE_EMERGENCY\n                ? 1_000_000\n                : (int) $reciprocity->score;",
    "$priorityScore = $lane === FundWishQueueEntry::LANE_EMERGENCY\n                ? 1_000_000\n                : 0;",
    1,
)
text = text.replace(
    "                    ->orderByRaw(\"CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END\")\n                    ->orderByDesc('priority_score')\n                    ->orderBy('queued_at')",
    "                    ->orderByRaw(\"CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END\")\n                    ->orderBy('queued_at')\n                    ->orderBy('id')",
    1,
)
text = text.replace(
    "            $wish = FundWish::query()->with('membership.version')->whereKey($wish->id)->lockForUpdate()->firstOrFail();\n            if ($wish->collectionSnapshot()->exists()) {",
    "            $wish = FundWish::query()->with('membership.version')->whereKey($wish->id)->lockForUpdate()->firstOrFail();\n            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:reserve']);\n            if ($wish->collectionSnapshot()->exists()) {",
    1,
)
start = text.index('    public function releaseReserveAllocation(')
end = text.index('    public function availableReserveMinor()', start)
text = text[:start] + '''    public function releaseReserveAllocation(FundReserveAllocation $allocation, string $actorAccountId): FundReserveAllocation
    {
        return DB::transaction(function () use ($allocation, $actorAccountId): FundReserveAllocation {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:reserve']);
            $allocation = FundReserveAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ((int) $allocation->consumed_minor > 0) {
                throw new RuntimeException('Une allocation déjà consommée ne peut pas être libérée intégralement.');
            }
            if ($allocation->status === FundReserveAllocation::STATUS_RELEASED) {
                return $allocation;
            }
            $allocation->update([
                'status' => FundReserveAllocation::STATUS_RELEASED,
                'released_at' => now(),
            ]);
            FundAuditEvent::record($actorAccountId, 'FundReserveAllocationReleased', 'fund_reserve_allocation', $allocation->id);

            return $allocation->refresh();
        });
    }

''' + text[end:]
start = text.index('    public function detectRehabilitationCases(')
end = text.index('    public function startRehabilitation(', start)
text = text[:start] + '''    public function detectRehabilitationCases(?string $actorAccountId = null): int
    {
        $created = 0;

        FundMembership::query()
            ->select('id')
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (FundMembership $membership) use ($actorAccountId, &$created): void {
                $createdCase = DB::transaction(function () use ($membership, $actorAccountId): bool {
                    $membership = FundMembership::query()
                        ->with('version')
                        ->whereKey($membership->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $threshold = max(1, (int) ($membership->version?->rehabilitation_incident_threshold ?? 3));
                    $overdue = FundArrear::query()
                        ->where('account_id', $membership->account_id)
                        ->where('status', FundArrear::STATUS_OPEN)
                        ->whereNotNull('grace_ends_at')
                        ->where('grace_ends_at', '<', now())
                        ->count();
                    if ($overdue < $threshold) {
                        return false;
                    }

                    $exists = FundRehabilitationCase::query()
                        ->where('fund_membership_id', $membership->id)
                        ->whereIn('status', [FundRehabilitationCase::STATUS_REQUIRED, FundRehabilitationCase::STATUS_IN_PROGRESS])
                        ->exists();
                    if ($exists) {
                        return false;
                    }

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

                    return true;
                });

                if ($createdCase) {
                    $created++;
                }
            });

        return $created;
    }

''' + text[end:]
p.write_text(text)

# Admin queue projection follows the same emergency-then-FIFO rule.
p = ROOT / 'app/Modules/Funds/Http/Controllers/Admin/AdminFundPilotageController.php'
replace_once(
    p,
    "            ->orderByRaw(\"CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END\")\n            ->orderByDesc('priority_score')\n            ->orderBy('queued_at')",
    "            ->orderByRaw(\"CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END\")\n            ->orderBy('queued_at')\n            ->orderBy('id')",
)

# 2) Complementary waves: idempotent creation, stable lifecycle, real fee-once semantics.
p = ROOT / 'app/Modules/Funds/Application/Services/FundCollectionWaveService.php'
text = p.read_text()
text = text.replace(
    "                if ($activeLatest) {\n                    throw new RuntimeException('La dernière vague doit être exécutée avant d’en préparer une nouvelle.');\n                }",
    "                if ($activeLatest) {\n                    return $this->wavePayload($snapshot, $latestWave);\n                }",
    1,
)
text = text.replace("            ->where('fee_due_minor', '>', 0)", "            ->where('fee_paid_minor', '>', 0)", 1)
text = text.replace(
    "            $snapshot->update([\n                'status' => FundCollectionSnapshot::STATUS_CANCELLED,\n                'completed_at' => null,\n            ]);",
    "            $snapshot->update([\n                'status' => FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED,\n                'completed_at' => null,\n            ]);",
    1,
)
text = text.replace(
    "                $snapshot->update(['status' => FundCollectionSnapshot::STATUS_CANCELLED]);\n            } else {\n                FundCollectionParticipant::query()\n                    ->where('snapshot_id', $snapshot->id)\n                    ->where('wave_number', $waveNumber)\n                    ->update(['wave_status' => self::WAVE_FAILED]);\n                $snapshot->update(['status' => FundCollectionSnapshot::STATUS_CANCELLED]);",
    "                $snapshot->update(['status' => FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED]);\n            } else {\n                FundCollectionParticipant::query()\n                    ->where('snapshot_id', $snapshot->id)\n                    ->where('wave_number', $waveNumber)\n                    ->update(['wave_status' => self::WAVE_FAILED]);\n                $snapshot->update([\n                    'status' => $collected > 0\n                        ? FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED\n                        : FundCollectionSnapshot::STATUS_FAILED,\n                ]);",
    1,
)
old = '''            $globalCollected = (int) FundCollectionParticipant::query()
                ->where('snapshot_id', $participant->snapshot_id)
                ->sum('solidarity_paid_minor');
            $globalRemaining = max(0, (int) $participant->snapshot->collective_amount_minor - $globalCollected);
            if ($globalRemaining <= 0) {
                return $this->skipParticipantRemainder($participant, 'target_already_funded');
            }

            $participantRemaining = max(0, (int) $participant->solidarity_due_minor - (int) $participant->solidarity_paid_minor);
            $remainingSolidarity = min($participantRemaining, $globalRemaining);
'''
new = '''            $participantRemaining = max(0, (int) $participant->solidarity_due_minor - (int) $participant->solidarity_paid_minor);
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
'''
if old not in text:
    raise SystemExit('wave debit ordering pattern missing')
text = text.replace(old, new, 1)
insert_at = text.index('    private function recordFailedDebit(')
noop = '''    private function recordNoopDebit(FundCollectionParticipant $participant, string $reason): FundCollectionDebit
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

'''
text = text[:insert_at] + noop + text[insert_at:]
p.write_text(text)

# Initial P014-D engine may not re-enter once a complementary wave exists.
p = ROOT / 'app/Modules/Funds/Application/Services/FundCollectionService.php'
replace_once(
    p,
    "        if ($snapshot->status === FundCollectionSnapshot::STATUS_FUNDED) {\n            return $snapshot->load(['participants.arrear', 'debits']);\n        }\n        if ($snapshot->status === FundCollectionSnapshot::STATUS_CANCELLED) {",
    "        if ($snapshot->status === FundCollectionSnapshot::STATUS_FUNDED) {\n            return $snapshot->load(['participants.arrear', 'debits']);\n        }\n        if (FundCollectionParticipant::query()\n            ->where('snapshot_id', $snapshot->id)\n            ->where('wave_number', '>', 1)\n            ->exists()) {\n            throw new RuntimeException('Une vague complémentaire existe pour cette collecte ; utilisez le moteur de vagues.');\n        }\n        if ($snapshot->status === FundCollectionSnapshot::STATUS_CANCELLED) {",
)

# 3) Legacy post-snapshot reserve mutation is explicitly sealed.
p = ROOT / 'app/Modules/Funds/Application/Services/FundRealizationService.php'
text = p.read_text()
start = text.index('    public function authorizeReserve(FundOrder $order, int $amountMinor): FundOrder')
end = text.index('    public function markDelivered(', start)
text = text[:start] + '''    public function authorizeReserve(FundOrder $order, int $amountMinor): FundOrder
    {
        throw new RuntimeException('La réserve Fonds doit être autorisée avant le snapshot de collecte depuis le pilotage Fonds.');
    }

''' + text[end:]
p.write_text(text)

# 4) Database guards and indexes for final invariants.
migration = ROOT / 'app/Modules/Funds/Database/Migrations/2026_08_13_030000_stabilize_fund_invariants.php'
migration.write_text(r'''<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE fund_wish_queue_entries SET priority_score = 0 WHERE lane = 'ordinary'");

        DB::unprepared(<<<'SQL'
CREATE INDEX IF NOT EXISTS fund_queue_fifo_idx
    ON fund_wish_queue_entries (fund_program_id, status, lane, queued_at, id);

CREATE UNIQUE INDEX IF NOT EXISTS fund_rehabilitation_one_active_per_membership
    ON fund_rehabilitation_cases (fund_membership_id)
    WHERE status IN ('required', 'in_progress');

CREATE UNIQUE INDEX IF NOT EXISTS fund_collection_fee_once_per_wish_participant
    ON fund_collection_participants (snapshot_id, account_id)
    WHERE fee_paid_minor > 0;

ALTER TABLE fund_wish_queue_entries
    DROP CONSTRAINT IF EXISTS fund_queue_ordinary_priority_zero;
ALTER TABLE fund_wish_queue_entries
    ADD CONSTRAINT fund_queue_ordinary_priority_zero
    CHECK (lane <> 'ordinary' OR priority_score = 0);

ALTER TABLE fund_reserve_allocations
    DROP CONSTRAINT IF EXISTS fund_reserve_consumption_within_authorization;
ALTER TABLE fund_reserve_allocations
    ADD CONSTRAINT fund_reserve_consumption_within_authorization
    CHECK (consumed_minor <= amount_minor);

ALTER TABLE fund_collection_participants
    DROP CONSTRAINT IF EXISTS fund_collection_participant_paid_within_due;
ALTER TABLE fund_collection_participants
    ADD CONSTRAINT fund_collection_participant_paid_within_due
    CHECK (solidarity_paid_minor <= solidarity_due_minor AND fee_paid_minor <= fee_due_minor);

ALTER TABLE fund_collection_debits
    DROP CONSTRAINT IF EXISTS fund_collection_positive_debit_requires_ledger;
ALTER TABLE fund_collection_debits
    ADD CONSTRAINT fund_collection_positive_debit_requires_ledger
    CHECK ((debited_solidarity_minor + debited_fee_minor) = 0 OR ledger_transaction_id IS NOT NULL);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE fund_collection_debits DROP CONSTRAINT IF EXISTS fund_collection_positive_debit_requires_ledger;
ALTER TABLE fund_collection_participants DROP CONSTRAINT IF EXISTS fund_collection_participant_paid_within_due;
ALTER TABLE fund_reserve_allocations DROP CONSTRAINT IF EXISTS fund_reserve_consumption_within_authorization;
ALTER TABLE fund_wish_queue_entries DROP CONSTRAINT IF EXISTS fund_queue_ordinary_priority_zero;
DROP INDEX IF EXISTS fund_collection_fee_once_per_wish_participant;
DROP INDEX IF EXISTS fund_rehabilitation_one_active_per_membership;
DROP INDEX IF EXISTS fund_queue_fifo_idx;
SQL);
    }
};
''')

# 5) Production integrity command for observability and post-deploy checks.
command = ROOT / 'app/Modules/Funds/Console/FundIntegrityCheckCommand.php'
command.parent.mkdir(parents=True, exist_ok=True)
command.write_text(r'''<?php

declare(strict_types=1);

namespace App\Modules\Funds\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class FundIntegrityCheckCommand extends Command
{
    protected $signature = 'funds:integrity-check';

    protected $description = 'Vérifie les invariants financiers et opérationnels critiques du module Fonds.';

    public function handle(): int
    {
        $checks = [
            'overcollected_snapshots' => (int) DB::table('fund_collection_snapshots as snapshots')
                ->leftJoin('fund_collection_debits as debits', 'debits.snapshot_id', '=', 'snapshots.id')
                ->groupBy('snapshots.id', 'snapshots.collective_amount_minor')
                ->havingRaw('COALESCE(SUM(debits.debited_solidarity_minor), 0) > snapshots.collective_amount_minor')
                ->get()
                ->count(),
            'positive_debits_without_ledger' => (int) DB::table('fund_collection_debits')
                ->whereRaw('(debited_solidarity_minor + debited_fee_minor) > 0')
                ->whereNull('ledger_transaction_id')
                ->count(),
            'duplicate_paid_fees' => (int) DB::query()
                ->fromSub(
                    DB::table('fund_collection_participants')
                        ->selectRaw('snapshot_id, account_id, COUNT(*) AS paid_fee_rows')
                        ->where('fee_paid_minor', '>', 0)
                        ->groupBy('snapshot_id', 'account_id')
                        ->havingRaw('COUNT(*) > 1'),
                    'duplicates',
                )
                ->count(),
            'ordinary_queue_priority_violations' => (int) DB::table('fund_wish_queue_entries')
                ->where('lane', 'ordinary')
                ->where('priority_score', '<>', 0)
                ->count(),
            'reserve_overconsumption' => (int) DB::table('fund_reserve_allocations')
                ->whereColumn('consumed_minor', '>', 'amount_minor')
                ->count(),
            'duplicate_active_rehabilitations' => (int) DB::query()
                ->fromSub(
                    DB::table('fund_rehabilitation_cases')
                        ->selectRaw('fund_membership_id, COUNT(*) AS active_cases')
                        ->whereIn('status', ['required', 'in_progress'])
                        ->groupBy('fund_membership_id')
                        ->havingRaw('COUNT(*) > 1'),
                    'duplicates',
                )
                ->count(),
        ];

        $failed = false;
        foreach ($checks as $name => $count) {
            $count === 0
                ? $this->info("OK  {$name}")
                : $this->error("FAIL {$name}: {$count}");
            $failed = $failed || $count > 0;
        }

        if ($failed) {
            $this->error('Intégrité Fonds: anomalies détectées.');

            return self::FAILURE;
        }

        $this->info('Intégrité Fonds: OK.');

        return self::SUCCESS;
    }
}
''')

# Register command.
p = ROOT / 'app/Modules/Funds/Infrastructure/Providers/FundsServiceProvider.php'
text = p.read_text()
text = text.replace(
    "namespace App\\Modules\\Funds\\Infrastructure\\Providers;\n\n",
    "namespace App\\Modules\\Funds\\Infrastructure\\Providers;\n\nuse App\\Modules\\Funds\\Console\\FundIntegrityCheckCommand;\n",
    1,
)
text = text.replace(
    "        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');\n\n",
    "        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');\n        $this->commands([FundIntegrityCheckCommand::class]);\n\n",
    1,
)
p.write_text(text)

# 6) Update P014-F wave regression expectations to the stabilized contract.
p = ROOT / 'tests/Feature/Funds/FundsCollectionWaveTest.php'
text = p.read_text()
text = text.replace(
    "        ->and($first->refresh()->status)->toBe(FundCollectionSnapshot::STATUS_CANCELLED)",
    "        ->and($first->refresh()->status)->toBe(FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED)",
    1,
)
old = '''    expect(fn () => $waves->createSecondWave($snapshot->refresh(), $beneficiary->id))
        ->toThrow(RuntimeException::class, 'La dernière vague doit être exécutée avant d’en préparer une nouvelle.');
    expect(fn () => $collections->execute($snapshot->refresh(), $beneficiary->id))
        ->toThrow(RuntimeException::class, 'Cette collecte est annulée.');
'''
new = '''    $sameWave = $waves->createSecondWave($snapshot->refresh(), $beneficiary->id);
    expect($sameWave['wave_number'])->toBe(2)
        ->and($sameWave['wave_hash'])->toBe($wave['wave_hash']);
    expect(fn () => $collections->execute($snapshot->refresh(), $beneficiary->id))
        ->toThrow(RuntimeException::class, 'Une vague complémentaire existe');
'''
if old not in text:
    raise SystemExit('wave regression expectation pattern missing')
text = text.replace(old, new, 1)
p.write_text(text)

# P014-E legacy reserve mutation must stay sealed.
p = ROOT / 'tests/Feature/Funds/FundsRealizationTest.php'
text = p.read_text()
anchor = "test('P014-E exige une preuve avant validation du jalon', function (): void {"
if anchor not in text:
    raise SystemExit('P014-E test anchor missing')
extra = '''test('P014-G interdit toute nouvelle autorisation de réserve après le snapshot', function (): void {
    $fixture = p014eFixture();
    $service = app(FundRealizationService::class);
    $order = $service->createOrder($fixture['wish'], $fixture['beneficiary']->id);

    expect(fn () => $service->authorizeReserve($order, 1000))
        ->toThrow(RuntimeException::class, 'avant le snapshot');
});

'''
text = text.replace(anchor, extra + anchor, 1)
p.write_text(text)

# Final stabilization documentation.
doc = Path('docs/chantiers/P014-G-FONDS-STABILISATION-FINALE.md')
doc.write_text('''# P014-G — Stabilisation finale du Fonds\n\n## Objectif\n\nP014-G ne crée aucun nouveau métier majeur. Il ferme les risques résiduels de P014-A à P014-F avant de déclarer le module Fonds stable.\n\n## Invariants durcis\n\n- urgence vitale vérifiée avant la file ordinaire ;\n- file ordinaire strictement FIFO : la réciprocité reste un critère d’éligibilité et ne donne jamais de priorité ;\n- création de seconde vague idempotente tant que la vague courante n’a pas été exécutée ;\n- le moteur P014-D ne peut plus reprendre une collecte lorsqu’une vague complémentaire existe ;\n- le statut global reste `partially_funded` entre deux vagues, et non `cancelled` ;\n- frais Wasplex facturé au plus une fois par participant et par vœu, sur la base d’un frais réellement payé ;\n- toute écriture de débit positive Fonds doit référencer une transaction Ledger ;\n- aucune collecte ne peut dépasser son objectif global ;\n- toute allocation de réserve est sérialisée et reste antérieure au snapshot immuable ;\n- l’ancien endpoint P014-E ne peut plus augmenter la réserve après snapshot ;\n- une seule réhabilitation active peut exister par adhésion ;\n- consommation de réserve et montants payés ne peuvent dépasser leurs autorisations/dus.\n\n## Observabilité\n\nLa commande suivante vérifie les invariants critiques en production :\n\n```bash\nphp8.4 artisan funds:integrity-check\n```\n\nElle échoue si elle détecte une surcollecte, un débit sans Ledger, un double frais payé, une priorité ordinaire non nulle, une surconsommation de réserve ou plusieurs réhabilitations actives pour la même adhésion.\n\n## Validation\n\nP014-G doit passer : Pint, Prettier, ESLint, TypeScript, build Vite, tests Fonds ciblés, suite Laravel complète et `funds:integrity-check` sur une base de test migrée.\n''')

# Self-remove the temporary applicator and workflow so only production artifacts remain.
Path('.github/scripts/p014-g-stabilize.py').unlink(missing_ok=True)
Path('.github/workflows/p014-g-stabilization.yml').unlink(missing_ok=True)
