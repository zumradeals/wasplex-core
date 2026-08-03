<?php

namespace App\Modules\Ledger\Domain\Events;

final readonly class LedgerTransactionReversed
{
    public function __construct(
        public string $originalTransactionId,
        public string $reversalTransactionId,
    ) {}
}
