<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Contracts;

use App\Modules\Campaigns\Application\ValueObjects\CampaignReport;
use App\Modules\Campaigns\Application\ValueObjects\CampaignsBudgetSummary;

interface CampaignsReportingContract
{
    public function reportFor(string $organizationId, string $campaignId): ?CampaignReport;

    /**
     * @return array<int, CampaignReport>
     */
    public function reportForOrganization(string $organizationId): array;

    public function globalBudgetSummary(): CampaignsBudgetSummary;
}
