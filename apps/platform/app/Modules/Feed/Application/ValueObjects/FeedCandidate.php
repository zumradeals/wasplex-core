<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\ValueObjects;

final class FeedCandidate
{
    public function __construct(
        public readonly string $campaignId,
        public readonly string $economicClass,
    ) {}
}
