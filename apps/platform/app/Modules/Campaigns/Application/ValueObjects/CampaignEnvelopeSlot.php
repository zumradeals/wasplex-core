<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\ValueObjects;

final class CampaignEnvelopeSlot
{
    public function __construct(
        public readonly string $consumptionId,
        public readonly string $organizationId,
        public readonly string $brandId,
        public readonly ?string $objectiveCode,
        public readonly int $gainMinor,
        public readonly int $requiredDurationMs,
        public readonly string $expiresAt,
        public readonly int $attentionUnits = 1,
        public readonly bool $requiresMediaEnd = false,
    ) {}
}
