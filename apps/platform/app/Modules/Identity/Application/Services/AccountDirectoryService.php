<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Subscriptions\Application\Contracts\SubscriptionsReportingContract;
use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Docs/chantiers/P017-CHANTIER.md (écran Utilisateurs) : premier accès
 * admin en lecture à un compte quelconque — auparavant inexistant (chaque
 * autre endpoint Identity ne lit que le compte connecté). Le solde wallet
 * et la classe économique active sont lus via les contrats internes déjà
 * en place (UserWalletContract, SubscriptionsReportingContract) — jamais
 * un accès direct aux tables d'un autre module (CLAUDE.md #6).
 */
final class AccountDirectoryService
{
    private const ONLINE_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly UserWalletContract $wallet,
        private readonly SubscriptionsReportingContract $subscriptions,
    ) {}

    /**
     * @return Collection<int, Account>
     */
    public function search(?string $query, int $limit = 50): Collection
    {
        $accounts = Account::query()
            ->with('identifiers')
            ->when($query !== null && $query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->whereHas('identifiers', function ($identifierQuery) use ($query): void {
                        $identifierQuery->where('value', 'like', "%{$query}%");
                    })->orWhere('id', $query);
                });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $accounts;
    }

    /**
     * @return array{
     *     account: Account,
     *     is_online: bool,
     *     wallet_balance_minor: int,
     *     economic_class_code: ?string,
     *     advertiser_organization_name: ?string,
     *     organizations: array<int, array{id: string, name: string, type: string, status: string}>,
     * }
     */
    public function detail(string $accountId): array
    {
        /** @var Account $account */
        $account = Account::query()->with(['identifiers', 'organizationMemberships.organization'])->findOrFail($accountId);

        $isOnline = AccountSession::query()
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->where('last_active_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES))
            ->exists();

        $organizations = $account->organizationMemberships
            ->filter(fn ($membership) => $membership->organization !== null)
            ->map(fn ($membership) => [
                'id' => $membership->organization->id,
                'name' => $membership->organization->name,
                'type' => $membership->organization->type,
                'status' => $membership->status,
            ])
            ->values()
            ->all();

        $advertiserOrganizationName = collect($organizations)
            ->firstWhere('type', 'advertiser')['name'] ?? null;

        return [
            'account' => $account,
            'is_online' => $isOnline,
            'wallet_balance_minor' => $this->wallet->balanceMinor($accountId),
            'economic_class_code' => $this->subscriptions->activeEconomicClassCodeForAccount($accountId),
            'advertiser_organization_name' => $advertiserOrganizationName,
            'organizations' => $organizations,
        ];
    }

    public function restrict(string $accountId): Account
    {
        /** @var Account $account */
        $account = Account::query()->findOrFail($accountId);
        $account->update(['restricted_at' => now()]);

        return $account;
    }

    public function unrestrict(string $accountId): Account
    {
        /** @var Account $account */
        $account = Account::query()->findOrFail($accountId);
        $account->update(['restricted_at' => null]);

        return $account;
    }
}
