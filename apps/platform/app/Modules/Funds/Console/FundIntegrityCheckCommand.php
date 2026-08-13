<?php

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
            'overcollected_snapshots' => (int) DB::query()
                ->fromSub(
                    DB::table('fund_collection_snapshots as snapshots')
                        ->leftJoin('fund_collection_debits as debits', 'debits.snapshot_id', '=', 'snapshots.id')
                        ->selectRaw('snapshots.id')
                        ->groupBy('snapshots.id', 'snapshots.collective_amount_minor')
                        ->havingRaw('COALESCE(SUM(debits.debited_solidarity_minor), 0) > snapshots.collective_amount_minor'),
                    'overcollected',
                )
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
