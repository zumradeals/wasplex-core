<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use App\Modules\Wallet\Events\WalletBalanceChanged;
use App\Modules\Wallet\Infrastructure\Models\UserWallet;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /** @return array{today_credits_minor: int, month_credits_minor: int, month_debits_minor: int, pending_deposits_minor: int} */
    public function summary(string $accountId): array
    {
        $account = LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT)
            ->where('owner_id', $accountId)
            ->first();

        $todayCredits = 0;
        $monthCredits = 0;
        $monthDebits = 0;

        if ($account !== null) {
            $todayCredits = (int) LedgerEntry::query()
                ->where('account_id', $account->id)
                ->where('direction', LedgerEntry::DIRECTION_CREDIT)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('amount_minor');

            $monthCredits = (int) LedgerEntry::query()
                ->where('account_id', $account->id)
                ->where('direction', LedgerEntry::DIRECTION_CREDIT)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount_minor');

            $monthDebits = (int) LedgerEntry::query()
                ->where('account_id', $account->id)
                ->where('direction', LedgerEntry::DIRECTION_DEBIT)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount_minor');
        }

        $pendingDeposits = (int) UserWalletDeposit::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [
                UserWalletDeposit::STATUS_CREATED,
                UserWalletDeposit::STATUS_AWAITING_PAYMENT,
                UserWalletDeposit::STATUS_CONFIRMED,
            ])
            ->sum('amount_minor');

        return [
            'today_credits_minor' => $todayCredits,
            'month_credits_minor' => $monthCredits,
            'month_debits_minor' => $monthDebits,
            'pending_deposits_minor' => $pendingDeposits,
        ];
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

    /**
     * Les accordéons Wallet sont des projections du Grand Livre. On ne crée
     * jamais un historique parallèle : source_module reste la source de
     * catégorisation et les écritures Ledger restent la source de vérité.
     *
     * @return array<int, array{key: string, label: string, description: string, icon: string, count: int}>
     */
    public function historyCategories(string $accountId): array
    {
        $account = $this->availableLedgerAccount($accountId);
        $counts = [];

        if ($account !== null) {
            $rows = LedgerEntry::query()
                ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.transaction_id')
                ->where('ledger_entries.account_id', $account->id)
                ->selectRaw('ledger_transactions.source_module AS source_module, COUNT(*) AS total')
                ->groupBy('ledger_transactions.source_module')
                ->get();

            foreach ($rows as $row) {
                $source = $row->source_module === null ? 'other' : (string) $row->source_module;
                $counts[$source] = (int) $row->total;
            }
        }

        $definitions = $this->historyCategoryDefinitions();
        $categories = [];
        $knownModules = [];

        foreach ($definitions as $key => $definition) {
            $count = 0;
            foreach ($definition['modules'] as $module) {
                $knownModules[] = $module;
                $count += $counts[$module] ?? 0;
            }

            $categories[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'count' => $count,
            ];
        }

        foreach ($counts as $module => $count) {
            if ($module === 'other' || in_array($module, $knownModules, true) || $count <= 0) {
                continue;
            }

            $categories[] = [
                'key' => 'module:'.$module,
                'label' => 'Historique '.Str::headline($module),
                'description' => 'Mouvements enregistrés par ce module dans le Grand Livre.',
                'icon' => '•',
                'count' => $count,
            ];
        }

        if (($counts['other'] ?? 0) > 0) {
            $categories[] = [
                'key' => 'other',
                'label' => 'Autres opérations',
                'description' => 'Écritures Wallet sans module métier identifié.',
                'icon' => '•',
                'count' => $counts['other'],
            ];
        }

        return $categories;
    }

    public function history(string $accountId, int $perPage = 10, ?string $category = null): LengthAwarePaginator
    {
        $account = $this->availableLedgerAccount($accountId);

        if ($account === null) {
            return LedgerEntry::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $query = LedgerEntry::query()
            ->where('account_id', $account->id)
            ->with('transaction');

        if ($category !== null && $category !== '') {
            $definitions = $this->historyCategoryDefinitions();

            if (isset($definitions[$category])) {
                $modules = $definitions[$category]['modules'];
                $query->whereHas('transaction', fn ($transaction) => $transaction->whereIn('source_module', $modules));
            } elseif (str_starts_with($category, 'module:')) {
                $module = substr($category, 7);
                $query->whereHas('transaction', fn ($transaction) => $transaction->where('source_module', $module));
            } elseif ($category === 'other') {
                $knownModules = collect($definitions)->flatMap(fn (array $definition): array => $definition['modules'])->values()->all();
                $query->whereHas('transaction', fn ($transaction) => $transaction
                    ->whereNull('source_module')
                    ->orWhereNotIn('source_module', $knownModules));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    private function availableLedgerAccount(string $accountId): ?LedgerAccount
    {
        return LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT)
            ->where('owner_id', $accountId)
            ->first();
    }

    /** @return array<string, array{label: string, description: string, icon: string, modules: array<int, string>}> */
    private function historyCategoryDefinitions(): array
    {
        return [
            'advertising' => [
                'label' => 'Historique publicité',
                'description' => 'Gains issus des publicités et opérations Feed.',
                'icon' => '↗',
                'modules' => ['feed', 'campaigns'],
            ],
            'wallet' => [
                'label' => 'Historique Wallet',
                'description' => 'Dépôts et transferts entre membres.',
                'icon' => '⇄',
                'modules' => ['wallet'],
            ],
            'funds' => [
                'label' => 'Historique Fonds',
                'description' => 'Adhésions et mouvements entre le Wallet et Fonds.',
                'icon' => '◎',
                'modules' => ['funds'],
            ],
            'card' => [
                'label' => 'Historique Carte Wasplex',
                'description' => 'Paiements et opérations Carte Wasplex.',
                'icon' => '▣',
                'modules' => ['card', 'cards', 'wasplex_card'],
            ],
            'live' => [
                'label' => 'Historique Live',
                'description' => 'Gains et dépenses enregistrés par Wasplex Live.',
                'icon' => '◉',
                'modules' => ['live'],
            ],
        ];
    }
}
