<?php

namespace App\Modules\AdvertiserStudio\Domain\Enums;

enum AssetStatus: string
{
    case Ready = 'ready';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
