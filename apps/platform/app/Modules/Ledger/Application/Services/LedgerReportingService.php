<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Application\Contracts\LedgerReportingContract;
use App\Modules\Ledger\Application\ValueObjects\LedgerGlobalSummary;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;

final class LedgerReportingService implements LedgerReportingContract
{
    public function globalSummary(): LedgerGlobalSummary
    {
        $totalDebit = (int) LedgerEntry::query()->where('direction', LedgerEntry::DIRECTION_DEBIT)->sum('amount_minor');
        $totalCredit = (int) LedgerEntry::query()->where('direction', LedgerEntry::DIRECTION_CREDIT)->sum('amount_minor');
        $transactionCount = LedgerTransaction::query()->count();

        $rows = LedgerEntry::query()
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->join('ledger_account_types', 'ledger_account_types.id', '=', 'ledger_accounts.account_type_id')
            ->selectRaw('ledger_account_types.code as account_type_code, ledger_entries.direction, coalesce(sum(ledger_entries.amount_minor), 0) as total')
            ->groupBy('ledger_account_types.code', 'ledger_entries.direction')
            ->get();

        $netByAccountType = [];

        foreach ($rows as $row) {
            $sign = $row->direction === LedgerEntry::DIRECTION_CREDIT ? 1 : -1;
            $netByAccountType[$row->account_type_code] = ($netByAccountType[$row->account_type_code] ?? 0) + $sign * (int) $row->total;
        }

        return new LedgerGlobalSummary($totalDebit, $totalCredit, $transactionCount, $netByAccountType);
    }
}
