<?php

namespace App\Modules\Identity\Domain\Events;

final readonly class CapabilityGranted
{
    public function __construct(public string $grantId, public string $accountId, public string $capability) {}
}
