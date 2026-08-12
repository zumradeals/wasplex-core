<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Subscriptions\Application\Services\FreeSubscriptionProvisioner;
use App\Modules\Subscriptions\Application\Services\NoActiveSubscriptionException;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;

/**
 * Prépare le prochain média probable sans créer de livraison Feed,
 * sans réserver d'enveloppe campagne et sans consommer de quota.
 *
 * La vraie livraison reste composée par AttentionService::next() lorsque
 * l'utilisateur passe effectivement à la publicité suivante.
 */
final class FeedPreloadService
{
    private const MAX_COMPOSITION_ATTEMPTS = 5;

    public function __construct(
        private readonly FeedCompositionService $composition,
        private readonly ApprovedCampaignAudienceContract $campaigns,
        private readonly CreativeAssetDirectoryContract $creativeAssets,
        private readonly FreeSubscriptionProvisioner $freeSubscription,
        private readonly SubscriptionQuotaContract $quota,
    ) {}

    /**
     * @return array{campaign_id: string, creative: array{url: string, type: string, duration: ?int, duration_ms: ?int}}|null
     */
    public function peek(string $accountId, ?string $currentCampaignId = null): ?array
    {
        $this->freeSubscription->ensureFor($accountId);

        try {
            $remainingQuotaUnits = $this->quota->currentCounter($accountId)->remaining();
        } catch (NoActiveSubscriptionException) {
            $remainingQuotaUnits = 0;
        }

        $allowRewardable = $remainingQuotaUnits > 0;
        $excluded = $currentCampaignId !== null && $currentCampaignId !== '' ? [$currentCampaignId] : [];

        for ($attempt = 0; $attempt < self::MAX_COMPOSITION_ATTEMPTS; $attempt++) {
            $candidate = $this->composition->nextCandidate($accountId, $excluded, $allowRewardable);

            if ($candidate === null) {
                return null;
            }

            $campaign = $this->campaigns->find($candidate->campaignId);

            if ($campaign === null || $campaign->creativeAssetId === null) {
                $excluded[] = $candidate->campaignId;

                continue;
            }

            $asset = $this->creativeAssets->find($campaign->organizationId, $campaign->creativeAssetId);

            if ($asset === null || $asset->type !== 'video') {
                $excluded[] = $candidate->campaignId;

                continue;
            }

            $durationMs = (int) ($asset->durationMs ?? 0);
            if ($durationMs <= 0) {
                $durationMs = ((int) ($asset->duration ?? 0)) * 1000;
            }

            if ($durationMs <= 0) {
                $excluded[] = $candidate->campaignId;

                continue;
            }

            $attentionUnits = (int) ceil($durationMs / max(1, (int) config('campaigns.attention_unit_ms')));

            if (! $candidate->isReplay && $attentionUnits > $remainingQuotaUnits) {
                $excluded[] = $candidate->campaignId;

                continue;
            }

            return [
                'campaign_id' => $candidate->campaignId,
                'creative' => [
                    'url' => $asset->url,
                    'type' => $asset->type,
                    'duration' => $asset->duration,
                    'duration_ms' => $asset->durationMs,
                ],
            ];
        }

        return null;
    }
}
