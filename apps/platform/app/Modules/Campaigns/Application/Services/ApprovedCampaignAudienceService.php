<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Campaigns\Application\ValueObjects\ApprovedCampaignAudience;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;

final class ApprovedCampaignAudienceService implements ApprovedCampaignAudienceContract
{
    public function find(string $campaignId): ?ApprovedCampaignAudience
    {
        $campaign = Campaign::query()
            ->where('status', Campaign::STATUS_APPROVED)
            ->find($campaignId);

        if ($campaign === null) {
            return null;
        }

        return $this->toValueObject($campaign);
    }

    public function listApproved(): array
    {
        return Campaign::query()
            ->where('status', Campaign::STATUS_APPROVED)
            ->with('versions')
            ->get()
            ->map(fn (Campaign $campaign) => $this->toValueObject($campaign))
            ->filter()
            ->values()
            ->all();
    }

    private function toValueObject(Campaign $campaign): ?ApprovedCampaignAudience
    {
        $version = $campaign->currentVersion();

        if ($version === null) {
            return null;
        }

        return new ApprovedCampaignAudience(
            campaignId: $campaign->id,
            campaignVersionId: $version->id,
            organizationId: $campaign->organization_id,
            brandId: $campaign->brand_id,
            objectiveCode: $campaign->objective_code,
            audienceConfiguration: $version->audience_configuration ?? [],
            scheduledStart: $campaign->scheduled_start?->toDateString(),
            scheduledEnd: $campaign->scheduled_end?->toDateString(),
        );
    }
}
