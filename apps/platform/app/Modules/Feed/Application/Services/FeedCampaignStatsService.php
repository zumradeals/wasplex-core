<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\Feed\Application\Contracts\FeedCampaignStatsContract;
use App\Modules\Feed\Application\ValueObjects\FeedCampaignStats;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use Illuminate\Support\Collection;

final class FeedCampaignStatsService implements FeedCampaignStatsContract
{
    public function statsForCampaign(string $campaignId): FeedCampaignStats
    {
        return $this->statsForCampaigns([$campaignId])[$campaignId] ?? FeedCampaignStats::empty();
    }

    /**
     * @param  array<int, string>  $campaignIds
     * @return array<string, FeedCampaignStats>
     */
    public function statsForCampaigns(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $counts = FeedAdDelivery::query()
            ->whereIn('campaign_id', $campaignIds)
            ->selectRaw('campaign_id, status, count(*) as total')
            ->groupBy('campaign_id', 'status')
            ->get()
            ->groupBy('campaign_id');

        $gains = FeedAdDelivery::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', FeedAdDelivery::STATUS_COMPLETED)
            ->selectRaw('campaign_id, coalesce(sum(gain_minor), 0) as total')
            ->groupBy('campaign_id')
            ->pluck('total', 'campaign_id');

        $stats = [];

        foreach ($campaignIds as $campaignId) {
            $stats[$campaignId] = $this->buildStats($counts->get($campaignId, collect()), (int) ($gains[$campaignId] ?? 0));
        }

        return $stats;
    }

    public function globalStats(): FeedCampaignStats
    {
        $counts = FeedAdDelivery::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        $gainDistributed = (int) FeedAdDelivery::query()
            ->where('status', FeedAdDelivery::STATUS_COMPLETED)
            ->sum('gain_minor');

        return $this->buildStats($counts, $gainDistributed);
    }

    /**
     * @param  Collection<int, object{status: string, total: int}>  $counts
     */
    private function buildStats(Collection $counts, int $gainDistributedMinor): FeedCampaignStats
    {
        $byStatus = $counts->pluck('total', 'status');

        return new FeedCampaignStats(
            reserved: (int) ($byStatus[FeedAdDelivery::STATUS_RESERVED] ?? 0),
            started: (int) ($byStatus[FeedAdDelivery::STATUS_STARTED] ?? 0),
            completed: (int) ($byStatus[FeedAdDelivery::STATUS_COMPLETED] ?? 0),
            abandoned: (int) ($byStatus[FeedAdDelivery::STATUS_ABANDONED] ?? 0),
            expired: (int) ($byStatus[FeedAdDelivery::STATUS_EXPIRED] ?? 0),
            held: (int) ($byStatus[FeedAdDelivery::STATUS_HELD] ?? 0),
            rejected: (int) ($byStatus[FeedAdDelivery::STATUS_REJECTED] ?? 0),
            gainDistributedMinor: $gainDistributedMinor,
        );
    }
}
