<?php

namespace App\Modules\Advertising\Domain\Events;

final readonly class AdvertisingProfileUpdated
{
    public function __construct(
        public string $accountId,
        public string $questionCode,
        public string $answerId,
    ) {}
}
