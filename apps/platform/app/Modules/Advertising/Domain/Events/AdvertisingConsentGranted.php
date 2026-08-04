<?php

namespace App\Modules\Advertising\Domain\Events;

final readonly class AdvertisingConsentGranted
{
    public function __construct(
        public string $accountId,
        public string $purposeCode,
        public string $consentId,
    ) {}
}
