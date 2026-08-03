<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class DepositCredited
{
    public function __construct(
        public string $depositId,
        public string $walletId,
        public string $ledgerTransactionId,
        public int $amountMinor,
    ) {}
}
