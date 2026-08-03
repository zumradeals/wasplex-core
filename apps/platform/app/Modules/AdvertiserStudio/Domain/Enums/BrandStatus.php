<?php

namespace App\Modules\AdvertiserStudio\Domain\Enums;

enum BrandStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
