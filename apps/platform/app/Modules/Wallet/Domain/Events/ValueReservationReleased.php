<?php

namespace App\Modules\Wallet\Domain\Events;

final readonly class ValueReservationReleased
{
    public function __construct(public string $reservationId, public string $walletId, public int $amountMinor, public string $reason) {}
}
