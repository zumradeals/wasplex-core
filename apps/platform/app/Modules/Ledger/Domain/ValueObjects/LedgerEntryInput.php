<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObjects;

use App\Modules\Ledger\Infrastructure\Models\LedgerEntry;
use App\Shared\Money\Money;

final class LedgerEntryInput
{
    private function __construct(
        public readonly LedgerAccountReference $account,
        public readonly string $direction,
        public readonly Money $amount,
        public readonly ?string $description = null,
    ) {}

    public static function debit(LedgerAccountReference $account, Money $amount, ?string $description = null): self
    {
        return new self($account, LedgerEntry::DIRECTION_DEBIT, $amount, $description);
    }

    public static function credit(LedgerAccountReference $account, Money $amount, ?string $description = null): self
    {
        return new self($account, LedgerEntry::DIRECTION_CREDIT, $amount, $description);
    }
}
