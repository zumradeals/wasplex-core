<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class DepositFailed
{
    public function __construct(public string $depositId, public string $providerStatus) {}
}
