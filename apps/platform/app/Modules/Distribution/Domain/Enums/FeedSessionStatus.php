<?php

namespace App\Modules\Distribution\Domain\Enums;

enum FeedSessionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Closed = 'closed';
}
