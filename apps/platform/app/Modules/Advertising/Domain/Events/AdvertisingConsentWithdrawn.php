<?php

namespace App\Modules\Advertising\Domain\Events;

final readonly class AdvertisingConsentWithdrawn
{
    public function __construct(
        public string $accountId,
        public string $purposeCode,
        public string $consentId,
    ) {}
}
