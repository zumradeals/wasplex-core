<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class ValueReservationCaptured
{
    public function __construct(public string $reservationId, public string $walletId, public int $amountMinor) {}
}
