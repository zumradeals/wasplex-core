<?php

namespace App\Modules\Distribution\Domain\Enums;

enum DeliveryLeaseStatus: string
{
    case Reserved = 'reserved';
    case Delivered = 'delivered';
    case Expired = 'expired';
    case Released = 'released';
}
