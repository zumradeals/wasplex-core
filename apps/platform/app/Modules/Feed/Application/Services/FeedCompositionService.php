<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Feed\Application\ValueObjects\FeedCandidate;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use Illuminate\Support\Carbon;

/**
 * docs/08-feed-principal-wasplex.md §49 : « vérifier urgence P0 » et
 * « insertion institutionnelle » n'ont pas de contenu réel dans ce
 * chantier (décision §2.3 du chantier) — la composition se réduit à
 * Matching + fréquence + fatigue, seuls axes réels disponibles.
 */
final class FeedCompositionService
{
    public function __construct(
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly MatchingContract $matching,
        private readonly EconomicClassCatalogContract $economicClasses,
    ) {}

    /**
     * @param  string[]  $excludingCampaignIds  tried and failed to reserve
     *                                          within the same request (e.g. envelope just exhausted) — skipped
     *                                          so the caller can retry against the next candidate without
     *                                          looping forever on the same one.
     */
    public function nextCandidate(string $accountId, array $excludingCampaignIds = []): ?FeedCandidate
    {
        $economicClass = $this->economicClasses->classForAccount($accountId);

        if ($economicClass === null) {
            return null;
        }

        $policy = $this->matching->activeFrequencyPolicy();
        $windowStart = Carbon::now('UTC')->subHours($policy->windowHours);

        foreach ($this->campaigns->listApproved() as $campaign) {
            if (in_array($campaign->campaignId, $excludingCampaignIds, true)) {
                continue;
            }

            $decision = $this->matching->checkEligibility($campaign->campaignId, $accountId);

            if (! $decision->isEligible()) {
                continue;
            }

            $deliveredInWindow = FeedAdDelivery::query()
                ->where('account_id', $accountId)
                ->where('campaign_id', $campaign->campaignId)
                ->whereIn('status', [FeedAdDelivery::STATUS_STARTED, FeedAdDelivery::STATUS_COMPLETED])
                ->where('created_at', '>=', $windowStart)
                ->count();

            if ($deliveredInWindow >= $policy->maxPerWindow) {
                continue;
            }

            $deliveredLifetime = FeedAdDelivery::query()
                ->where('account_id', $accountId)
                ->where('campaign_id', $campaign->campaignId)
                ->whereIn('status', [FeedAdDelivery::STATUS_STARTED, FeedAdDelivery::STATUS_COMPLETED])
                ->count();

            if ($deliveredLifetime >= $policy->fatigueThreshold) {
                continue;
            }

            return new FeedCandidate($campaign->campaignId, $economicClass);
        }

        return null;
    }
}
