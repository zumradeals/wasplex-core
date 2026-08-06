<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWallet;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The advertiser Wallet is a projection over the Grand Livre (docs/06 §3:
 * "le Wallet est une projection utilisateur calculée à partir du grand
 * livre") — the available balance is always summed live from
 * ledger_entries, never cached in a column here.
 */
final class AdvertiserWalletQueryService
{
    public const AVAILABLE_ACCOUNT_CODE = 'advertiser.budget.available';

    public function getOrCreateWallet(string $organizationId): AdvertiserWallet
    {
        return AdvertiserWallet::query()->firstOrCreate(['organization_id' => $organizationId]);
    }

    public function availableBalanceMinor(string $organizationId): int
    {
        $account = LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_ORGANIZATION)
            ->where('owner_id', $organizationId)
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

    public function listDeposits(string $organizationId, int $perPage = 25): LengthAwarePaginator
    {
        return AdvertiserWalletDeposit::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findDeposit(string $organizationId, string $depositId): ?AdvertiserWalletDeposit
    {
        return AdvertiserWalletDeposit::query()
            ->where('organization_id', $organizationId)
            ->where('id', $depositId)
            ->first();
    }
}
