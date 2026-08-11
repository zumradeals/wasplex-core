<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Feed\Application\ValueObjects\FeedCandidate;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedCampaignReward;
use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use App\Modules\Subscriptions\Application\Services\FreeSubscriptionProvisioner;
use Illuminate\Support\Carbon;

/**
 * Composition du Feed : les campagnes jamais récompensées restent
 * prioritaires. Une campagne déjà récompensée reste toutefois disponible
 * comme replay tant qu'elle demeure approuvée et éligible ; fréquence et
 * fatigue deviennent alors des préférences de classement, jamais une cause
 * de Feed vide à elles seules.
 */
final class FeedCompositionService
{
    public function __construct(
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly MatchingContract $matching,
        private readonly EconomicClassCatalogContract $economicClasses,
        private readonly FreeSubscriptionProvisioner $freeSubscription,
    ) {}

    /**
     * @param  string[]  $excludingCampaignIds  campagnes temporairement écartées
     * @param  bool  $allowRewardable  false lorsque le quota rémunéré est épuisé ; les replays restent possibles
     */
    public function nextCandidate(string $accountId, array $excludingCampaignIds = [], bool $allowRewardable = true): ?FeedCandidate
    {
        $this->freeSubscription->ensureFor($accountId);

        $economicClass = $this->economicClasses->classForAccount($accountId);

        if ($economicClass === null) {
            return null;
        }

        $policy = $this->matching->activeFrequencyPolicy();
        $windowStart = Carbon::now('UTC')->subHours($policy->windowHours);
        $rewardedCampaigns = FeedCampaignReward::query()
            ->where('account_id', $accountId)
            ->pluck('campaign_id')
            ->flip();

        $fallbackFresh = null;
        $oldestReplay = null;
        $oldestReplaySeenAt = null;

        foreach ($this->campaigns->listApproved() as $campaign) {
            if (in_array($campaign->campaignId, $excludingCampaignIds, true)) {
                continue;
            }

            $decision = $this->matching->checkEligibility($campaign->campaignId, $accountId);

            if (! $decision->isEligible()) {
                continue;
            }

            if ($rewardedCampaigns->has($campaign->campaignId)) {
                $lastSeenAt = FeedAdDelivery::query()
                    ->where('account_id', $accountId)
                    ->where('campaign_id', $campaign->campaignId)
                    ->whereIn('status', [
                        FeedAdDelivery::STATUS_STARTED,
                        FeedAdDelivery::STATUS_COMPLETED,
                        FeedAdDelivery::STATUS_REPLAY,
                    ])
                    ->max('created_at');

                $lastSeenAt = $lastSeenAt !== null ? Carbon::parse($lastSeenAt, 'UTC') : Carbon::createFromTimestampUTC(0);

                if ($oldestReplay === null || $lastSeenAt->lt($oldestReplaySeenAt)) {
                    $oldestReplay = new FeedCandidate($campaign->campaignId, $economicClass, true);
                    $oldestReplaySeenAt = $lastSeenAt;
                }

                continue;
            }

            if (! $allowRewardable) {
                continue;
            }

            $deliveredInWindow = FeedAdDelivery::query()
                ->where('account_id', $accountId)
                ->where('campaign_id', $campaign->campaignId)
                ->whereIn('status', [FeedAdDelivery::STATUS_STARTED, FeedAdDelivery::STATUS_COMPLETED])
                ->where('created_at', '>=', $windowStart)
                ->count();

            $deliveredLifetime = FeedAdDelivery::query()
                ->where('account_id', $accountId)
                ->where('campaign_id', $campaign->campaignId)
                ->whereIn('status', [FeedAdDelivery::STATUS_STARTED, FeedAdDelivery::STATUS_COMPLETED])
                ->count();

            $candidate = new FeedCandidate($campaign->campaignId, $economicClass);

            // Tant que la politique de fréquence n'est pas atteinte, la
            // campagne fraîche est servie immédiatement. Une campagne non
            // récompensée qui atteint le seuil reste toutefois en fallback :
            // la fréquence doit varier le Feed, pas supprimer une chance de
            // première récompense lorsque rien d'autre n'est disponible.
            if ($deliveredInWindow < $policy->maxPerWindow && $deliveredLifetime < $policy->fatigueThreshold) {
                return $candidate;
            }

            $fallbackFresh ??= $candidate;
        }

        return $fallbackFresh ?? $oldestReplay;
    }
}
