<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Contracts;

use App\Modules\Campaigns\Application\ValueObjects\ApprovedCampaignAudience;

/**
 * The only way Matching (P008) may read a campaign's targeting criteria —
 * never a direct query against `campaigns`/`campaign_versions` from
 * outside this module (docs/CLAUDE.md §6). Only campaigns currently
 * `approved` are exposed: a rejected, suspended or not-yet-reviewed
 * campaign can never produce an eligible match (docs/chantiers/
 * P008-CHANTIER.md §4.2).
 */
interface ApprovedCampaignAudienceContract
{
    public function find(string $campaignId): ?ApprovedCampaignAudience;

    /**
     * @return ApprovedCampaignAudience[]
     */
    public function listApproved(): array;
}
