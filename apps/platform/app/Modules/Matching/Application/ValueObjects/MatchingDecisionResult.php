<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\ValueObjects;

final class MatchingDecisionResult
{
    /**
     * @param  string[]  $reasonCodes
     */
    public function __construct(
        public readonly string $campaignId,
        public readonly string $decision,
        public readonly array $reasonCodes,
        public readonly string $decidedAt,
    ) {}

    public function isEligible(): bool
    {
        return $this->decision === 'eligible';
    }
}
