<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Campaigns\Application\Contracts\CampaignsReportingContract;
use App\Modules\Feed\Application\Contracts\FeedCampaignStatsContract;
use App\Modules\Ledger\Application\Contracts\LedgerReportingContract;
use App\Modules\Reporting\Application\ValueObjects\FounderDashboardSummary;
use App\Modules\Subscriptions\Application\Contracts\SubscriptionsReportingContract;

final class FounderDashboardService
{
    public function __construct(
        private readonly LedgerReportingContract $ledger,
        private readonly FeedCampaignStatsContract $feed,
        private readonly CampaignsReportingContract $campaigns,
        private readonly SubscriptionsReportingContract $subscriptions,
    ) {}

    public function summary(): FounderDashboardSummary
    {
        return new FounderDashboardSummary(
            ledger: $this->ledger->globalSummary(),
            feed: $this->feed->globalStats(),
            campaigns: $this->campaigns->globalBudgetSummary(),
            subscriptions: $this->subscriptions->summary(),
        );
    }
}
