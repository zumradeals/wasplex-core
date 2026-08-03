<?php

namespace App\Modules\Ledger\Domain\Events;

final readonly class LedgerImbalanceDetected
{
    public function __construct(
        public string $sourceModule,
        public string $businessReference,
        public string $unit,
        public string $currency,
        public int $debitTotal,
        public int $creditTotal,
        public ?string $traceId,
    ) {}
}
