<?php

namespace App\Modules\Identity\Domain\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
