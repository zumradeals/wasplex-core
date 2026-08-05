<?php

namespace App\Modules\Distribution\Domain\Enums;

enum AdvertisingDeliveryStatus: string
{
    case Prepared = 'prepared';
    case Delivered = 'delivered';
    case Invalidated = 'invalidated';
    case Superseded = 'superseded';
}
