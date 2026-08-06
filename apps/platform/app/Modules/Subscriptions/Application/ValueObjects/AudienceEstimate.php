<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\ValueObjects;

final class AudienceEstimate
{
    /**
     * @param  array<string, int>  $perClassCount  code => real count (never leaves this module)
     */
    public function __construct(
        public readonly int $estimatedMin,
        public readonly int $estimatedMax,
        public readonly bool $tooSmall,
        public readonly array $perClassCount,
    ) {}

    public function total(): int
    {
        return array_sum($this->perClassCount);
    }
}
