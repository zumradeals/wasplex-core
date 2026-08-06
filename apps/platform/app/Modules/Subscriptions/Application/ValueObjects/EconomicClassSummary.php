<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\ValueObjects;

final class EconomicClassSummary
{
    public function __construct(
        public readonly string $code,
        public readonly float $weightPercent,
        public readonly float $coefficient,
    ) {}
}
