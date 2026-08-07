<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use App\Modules\Wallet\Events\WalletBalanceChanged;
use App\Modules\Wallet\Infrastructure\Models\UserWallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The user Wallet is a projection over the Grand Livre (docs/06 §3), same
 * discipline as AdvertiserWalletQueryService (P003): the available balance
 * is always summed live from ledger_entries, never cached in a column.
 */
final class UserWalletQueryService implements UserWalletContract
{
    public const AVAILABLE_ACCOUNT_CODE = 'user.available.wp';

    public const ACCOUNT_TYPE_CODE = 'LIABILITY_USER';

    public function getOrCreate(string $accountId): UserWallet
    {
        return UserWallet::query()->firstOrCreate(['account_id' => $accountId]);
    }

    public function availableAccountReference(string $accountId): LedgerAccountReference
    {
        return LedgerAccountReference::forIdentityAccount(self::AVAILABLE_ACCOUNT_CODE, $accountId, self::ACCOUNT_TYPE_CODE, 'WP');
    }

    public function balanceMinor(string $accountId): int
    {
        $account = LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT)
            ->where('owner_id', $accountId)
            ->first();

        if ($account === null) {
            return 0;
        }

        $credits = (int) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', LedgerEntry::DIRECTION_CREDIT)
            ->sum('amount_minor');

        $debits = (int) LedgerEntry::query()
            ->where('account_id', $account->id)
            ->where('direction', LedgerEntry::DIRECTION_DEBIT)
            ->sum('amount_minor');

        return $credits - $debits;
    }

    /**
     * Best-effort (docs/chantiers/P011-CHANTIER.md §2.4): the Ledger
     * transaction the caller just committed is already the source of
     * truth — a Reverb outage must never turn an already-successful
     * credit into a failed HTTP response for the caller.
     */
    public function notifyBalanceChanged(
        string $accountId,
        int $amountMinor,
        string $origin,
        string $operation,
        string $ledgerTransactionId,
    ): void {
        try {
            WalletBalanceChanged::dispatch(
                $accountId,
                $amountMinor,
                $this->balanceMinor($accountId),
                $origin,
                $operation,
                $ledgerTransactionId,
            );
        } catch (Throwable $exception) {
            Log::channel('structured')->warning('wallet.balance_changed.broadcast_failed', [
                'account_id' => $accountId,
                'ledger_transaction_id' => $ledgerTransactionId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function history(string $accountId, int $perPage = 25): LengthAwarePaginator
    {
        $account = LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT)
            ->where('owner_id', $accountId)
            ->first();

        if ($account === null) {
            return LedgerEntry::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return LedgerEntry::query()
            ->where('account_id', $account->id)
            ->with('transaction')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
