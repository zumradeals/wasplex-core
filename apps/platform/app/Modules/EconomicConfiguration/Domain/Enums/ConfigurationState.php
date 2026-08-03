<?php

namespace App\Modules\EconomicConfiguration\Domain\Enums;

enum ConfigurationState: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';
    case Suspended = 'suspended';
}
