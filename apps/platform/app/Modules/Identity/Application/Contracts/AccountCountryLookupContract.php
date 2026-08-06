<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

/**
 * The only projection other modules may use to relate a set of account
 * identifiers to a declared country (docs/CLAUDE.md §6: "projections
 * minimales entre modules"). Country is not a sensitive domain (§11's list
 * is KYC/Wallet/Fonds/Alertes/Santé/sécurité/audit), but the identifiers
 * themselves never leave the caller — this contract returns only a count.
 */
interface AccountCountryLookupContract
{
    /**
     * @param  string[]  $accountIds
     */
    public function countInCountry(array $accountIds, string $countryCode): int;

    /**
     * The declared country of a single account, or null if unknown. Used
     * by Matching (P008) to check territory eligibility for one candidate
     * rather than counting a whole segment.
     */
    public function countryForAccount(string $accountId): ?string;
}
