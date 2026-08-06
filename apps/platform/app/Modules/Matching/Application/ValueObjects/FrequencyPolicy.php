<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\ValueObjects;

final class FrequencyPolicy
{
    public function __construct(
        public readonly int $windowHours,
        public readonly int $maxPerWindow,
        public readonly int $fatigueThreshold,
    ) {}
}
