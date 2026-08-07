<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Contracts;

use App\Modules\Feed\Application\ValueObjects\FeedCampaignStats;

interface FeedCampaignStatsContract
{
    public function statsForCampaign(string $campaignId): FeedCampaignStats;

    /**
     * @param  array<int, string>  $campaignIds
     * @return array<string, FeedCampaignStats> keyed by campaign id
     */
    public function statsForCampaigns(array $campaignIds): array;

    public function globalStats(): FeedCampaignStats;
}
