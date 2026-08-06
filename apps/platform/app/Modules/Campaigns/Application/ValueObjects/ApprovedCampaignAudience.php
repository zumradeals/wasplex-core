<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\ValueObjects;

final class ApprovedCampaignAudience
{
    /**
     * @param  array<string, mixed>  $audienceConfiguration
     */
    public function __construct(
        public readonly string $campaignId,
        public readonly string $campaignVersionId,
        public readonly string $organizationId,
        public readonly string $brandId,
        public readonly ?string $objectiveCode,
        public readonly array $audienceConfiguration,
        public readonly ?string $scheduledStart,
        public readonly ?string $scheduledEnd,
        public readonly ?string $creativeAssetId = null,
    ) {}
}
