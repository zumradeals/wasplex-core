<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class DepositPaymentConfirmed
{
    public function __construct(public string $depositId, public string $providerReference) {}
}
