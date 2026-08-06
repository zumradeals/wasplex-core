<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Matching\Application\Contracts\MatchingContract;

/**
 * « Campagnes qui vous correspondent » (Mon Espace) — la seule façon dont
 * l'explicabilité du Matching est démontrée sans Feed réel (P009, non
 * construit). Ne renvoie que les campagnes `eligible` ; jamais l'identité
 * du compte à l'annonceur (docs/chantiers/P008-CHANTIER.md §4).
 */
final class EligibleCampaignsForAccountService
{
    public function __construct(
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly MatchingContract $matching,
        private readonly BrandDirectoryContract $brands,
    ) {}

    /**
     * @return array<int, array{campaign_id: string, brand_name: ?string, objective_code: ?string, explanation: string[]}>
     */
    public function forAccount(string $accountId): array
    {
        $results = [];

        foreach ($this->campaigns->listApproved() as $campaign) {
            $decision = $this->matching->checkEligibility($campaign->campaignId, $accountId);

            if (! $decision->isEligible()) {
                continue;
            }

            $brand = $this->brands->find($campaign->organizationId, $campaign->brandId);

            $results[] = [
                'campaign_id' => $campaign->campaignId,
                'brand_name' => $brand?->name,
                'objective_code' => $campaign->objectiveCode,
                'cta_label' => Campaign::ctaFor($campaign->objectiveCode),
                'explanation' => $this->matching->explain($campaign->campaignId, $accountId),
            ];
        }

        return $results;
    }
}
