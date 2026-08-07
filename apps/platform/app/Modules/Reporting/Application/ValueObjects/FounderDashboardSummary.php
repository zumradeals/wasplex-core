<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\ValueObjects;

use App\Modules\Campaigns\Application\ValueObjects\CampaignsBudgetSummary;
use App\Modules\Feed\Application\ValueObjects\FeedCampaignStats;
use App\Modules\Ledger\Application\ValueObjects\LedgerGlobalSummary;
use App\Modules\Subscriptions\Application\ValueObjects\SubscriptionsSummary;

/**
 * docs/18-reporting-statistiques-audit-observabilite-wasplex.md §18-19 :
 * vue économique globale du fondateur — chaque section déclare sa propre
 * source (Grand Livre, Feed, Campagnes, Abonnements), assemblée ici en
 * lecture seule, jamais recalculée (docs/CLAUDE.md §7).
 */
final class FounderDashboardSummary
{
    public function __construct(
        public readonly LedgerGlobalSummary $ledger,
        public readonly FeedCampaignStats $feed,
        public readonly CampaignsBudgetSummary $campaigns,
        public readonly SubscriptionsSummary $subscriptions,
    ) {}
}
