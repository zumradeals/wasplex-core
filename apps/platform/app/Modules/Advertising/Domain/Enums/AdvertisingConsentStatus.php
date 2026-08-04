<?php

namespace App\Modules\Advertising\Domain\Enums;

enum AdvertisingConsentStatus: string
{
    case Granted = 'granted';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function isActive(): bool
    {
        return $this === self::Granted;
    }
}
