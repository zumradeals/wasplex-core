<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Events;

final class LedgerTransactionPosted
{
    public function __construct(public readonly string $transactionId) {}
}
