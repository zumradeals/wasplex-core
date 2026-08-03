<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Ledger\Domain\Enums\LedgerTransactionStatus;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Wallet\Domain\Events\WalletBalanceChanged;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletBalanceProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WalletProjector
{
    public function rebuild(Wallet $wallet, ?string $lastLedgerTransactionId = null): WalletBalanceProjection
    {
        $possiblyNullAccountIds = [
            $wallet->available_ledger_account_id,
            $wallet->reserved_ledger_account_id,
            $wallet->pending_ledger_account_id,
        ];

        if (in_array(null, $possiblyNullAccountIds, true)) {
            throw new WalletException('Les comptes comptables du Wallet ne sont pas initialisés.');
        }

        /** @var list<string> $accountIds */
        $accountIds = array_map(static fn (?string $accountId): string => (string) $accountId, $possiblyNullAccountIds);

        /** @var array<string, int> $balances */
        $balances = [];
        $rows = LedgerEntry::query()
            ->select('account_id')
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END) AS balance_minor")
            ->whereIn('account_id', $accountIds)
            ->whereHas('transaction', static fn ($query) => $query->where('status', LedgerTransactionStatus::Posted->value))
            ->groupBy('account_id')
            ->get();

        foreach ($rows as $row) {
            $balances[$row->account_id] = (int) $row->getAttribute('balance_minor');
        }

        [$availableAccountId, $reservedAccountId, $pendingAccountId] = $accountIds;
        $available = $balances[$availableAccountId] ?? 0;
        $reserved = $balances[$reservedAccountId] ?? 0;
        $pending = $balances[$pendingAccountId] ?? 0;

        if ($available < 0 || $reserved < 0 || $pending < 0) {
            Log::critical('wallet.projection.negative', ['wallet_id' => $wallet->id]);
            throw new WalletException('La reconstruction du Wallet a détecté un compartiment négatif.');
        }

        $projection = DB::transaction(function () use ($wallet, $available, $reserved, $pending, $lastLedgerTransactionId): WalletBalanceProjection {
            $projection = WalletBalanceProjection::query()->where('wallet_id', $wallet->id)->lockForUpdate()->firstOrFail();
            $changed = $projection->available_minor !== $available
                || $projection->reserved_minor !== $reserved
                || $projection->pending_minor !== $pending;
            $projection->forceFill([
                'available_minor' => $available,
                'reserved_minor' => $reserved,
                'pending_minor' => $pending,
                'version' => $projection->version + ($changed ? 1 : 0),
                'last_ledger_transaction_id' => $lastLedgerTransactionId ?? $projection->last_ledger_transaction_id,
                'rebuilt_at' => now(),
            ])->save();

            if ($changed) {
                DB::afterCommit(static fn () => event(new WalletBalanceChanged($wallet->id, $available, $reserved, $pending)));
            }

            return $projection;
        });

        return $projection;
    }
}
