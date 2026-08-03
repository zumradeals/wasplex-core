<?php

namespace App\Modules\Ledger\Domain\Events;

final readonly class LedgerTransactionPosted
{
    public function __construct(
        public string $transactionId,
        public string $sourceModule,
        public string $businessReference,
        public int $totalMinor,
        public string $unit,
        public string $currency,
    ) {}
}
