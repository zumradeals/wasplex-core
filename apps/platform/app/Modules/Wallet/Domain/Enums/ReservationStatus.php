<?php

namespace App\Modules\Wallet\Domain\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Captured = 'captured';
    case Released = 'released';
    case Expired = 'expired';
}
