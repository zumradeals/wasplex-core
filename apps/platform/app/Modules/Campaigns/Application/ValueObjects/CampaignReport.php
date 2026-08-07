<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\ValueObjects;

use App\Modules\Feed\Application\ValueObjects\FeedCampaignStats;

/**
 * docs/18-reporting-statistiques-audit-observabilite-wasplex.md §24-26 :
 * le budget (source Campaigns) et les livraisons/attention (source Feed,
 * via FeedCampaignStatsContract) sont deux sources déclarées séparément,
 * assemblées ici en lecture seule — jamais une nouvelle vérité financière.
 */
final class CampaignReport
{
    public function __construct(
        public readonly string $campaignId,
        public readonly string $organizationId,
        public readonly string $status,
        public readonly int $budgetAmountMinor,
        public readonly int $budgetReservedMinor,
        public readonly int $budgetCapturedMinor,
        public readonly int $budgetReleasedMinor,
        public readonly FeedCampaignStats $feed,
    ) {}
}
