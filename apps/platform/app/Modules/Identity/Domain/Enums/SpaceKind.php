<?php

namespace App\Modules\Identity\Domain\Enums;

enum SpaceKind: string
{
    case User = 'user';
    case Advertiser = 'advertiser';
    case Administration = 'administration';
}
