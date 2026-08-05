<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Events;

final class LedgerImbalanceDetected
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly array $details,
    ) {}
}
