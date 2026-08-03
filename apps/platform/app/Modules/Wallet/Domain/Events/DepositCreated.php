<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class DepositCreated
{
    public function __construct(public string $depositId, public string $walletId, public int $amountMinor) {}
}
