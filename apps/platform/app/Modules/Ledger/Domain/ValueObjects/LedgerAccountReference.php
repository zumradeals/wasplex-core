<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObjects;

use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;

/**
 * Identifies a ledger account without requiring it to already exist —
 * LedgerAccountResolver resolves this into a real LedgerAccount row,
 * creating it on first use. `code` is the catalog identifier shared across
 * many instances (e.g. "user.available.wp"), disambiguated by owner.
 */
final class LedgerAccountReference
{
    private function __construct(
        public readonly string $code,
        public readonly string $ownerType,
        public readonly string $ownerId,
        public readonly string $accountTypeCode,
        public readonly string $currency,
    ) {}

    public static function system(string $code, string $accountTypeCode, string $currency): self
    {
        return new self($code, LedgerAccount::OWNER_TYPE_SYSTEM, LedgerAccount::SYSTEM_OWNER_ID, $accountTypeCode, $currency);
    }

    public static function forIdentityAccount(
        string $code,
        string $identityAccountId,
        string $accountTypeCode,
        string $currency,
    ): self {
        return new self($code, LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT, $identityAccountId, $accountTypeCode, $currency);
    }
}
