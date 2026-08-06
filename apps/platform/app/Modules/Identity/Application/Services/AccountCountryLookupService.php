<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Application\Contracts\AccountCountryLookupContract;
use App\Modules\Identity\Infrastructure\Models\Account;

final class AccountCountryLookupService implements AccountCountryLookupContract
{
    public function countInCountry(array $accountIds, string $countryCode): int
    {
        if ($accountIds === []) {
            return 0;
        }

        return Account::query()
            ->whereIn('id', $accountIds)
            ->where('country_code', $countryCode)
            ->count();
    }

    public function countryForAccount(string $accountId): ?string
    {
        return Account::query()->whereKey($accountId)->value('country_code');
    }
}
