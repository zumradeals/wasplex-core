<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-only consultation (docs/chantiers/P002-CHANTIER.md Inclus:
 * "consultation administrative en lecture seule"). No balance/projection
 * computation here — that belongs to the Wallet module (Exclus: "Wallet,
 * solde projeté et historique utilisateur").
 */
final class LedgerQueryService
{
    public function listTransactions(int $perPage = 25): LengthAwarePaginator
    {
        return LedgerTransaction::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findTransaction(string $transactionId): ?LedgerTransaction
    {
        return LedgerTransaction::query()
            ->with(['entries.account.accountType', 'journal', 'outgoingLinks', 'incomingLinks'])
            ->find($transactionId);
    }

    public function listAccounts(int $perPage = 25): LengthAwarePaginator
    {
        return LedgerAccount::query()
            ->with('accountType')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
