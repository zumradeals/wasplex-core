<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignPublicVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Couche publique volontairement séparée du Feed rémunéré : elle mesure la
 * portée organique d'une campagne sans réserver de budget, consommer de quota
 * membre ni créer de droit à récompense.
 */
final class PublicCampaignService
{
    public function __construct(
        private readonly BrandDirectoryContract $brands,
        private readonly CreativeAssetDirectoryContract $creativeAssets,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function feed(int $limit = 20): array
    {
        return $this->visibleCampaignQuery()
            ->whereNotNull('public_slug')
            ->orderByDesc('updated_at')
            ->limit(max(1, min($limit, 50)))
            ->with('versions')
            ->get()
            ->map(fn (Campaign $campaign): ?array => $this->presentCampaign($campaign))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function presentation(string $slug): ?array
    {
        $campaign = $this->visibleCampaign($slug);

        return $campaign === null ? null : $this->presentCampaign($campaign);
    }

    public function startVisit(string $slug, string $visitorToken, string $visitToken): ?CampaignPublicVisit
    {
        $campaign = $this->visibleCampaign($slug);
        $version = $campaign?->currentVersion();

        if ($campaign === null || $version === null) {
            return null;
        }

        $visitorHash = $this->hashToken('visitor', $visitorToken);
        $visitHash = $this->hashToken('visit', $visitToken);

        return CampaignPublicVisit::query()->firstOrCreate(
            ['campaign_id' => $campaign->id, 'visit_hash' => $visitHash],
            [
                'campaign_version_id' => $version->id,
                'visitor_hash' => $visitorHash,
                'started_at' => Carbon::now('UTC'),
                'share_count' => 0,
            ],
        );
    }

    public function completeVisit(string $slug, string $visitId): ?CampaignPublicVisit
    {
        return $this->updateVisit($slug, $visitId, function (CampaignPublicVisit $visit): void {
            if ($visit->completed_at === null) {
                $visit->update(['completed_at' => Carbon::now('UTC')]);
            }
        });
    }

    public function recordJoinClick(string $slug, string $visitId): ?CampaignPublicVisit
    {
        return $this->updateVisit($slug, $visitId, function (CampaignPublicVisit $visit): void {
            if ($visit->join_clicked_at === null) {
                $visit->update(['join_clicked_at' => Carbon::now('UTC')]);
            }
        });
    }

    public function recordShare(string $slug, string $visitId): ?CampaignPublicVisit
    {
        return $this->updateVisit($slug, $visitId, function (CampaignPublicVisit $visit): void {
            $visit->increment('share_count');
        });
    }

    /**
     * @return array{views: int, unique_visitors: int, completions: int, shares: int, wasplex_cta_clicks: int}
     */
    public function stats(string $organizationId, string $campaignId): array
    {
        $campaign = Campaign::query()
            ->where('organization_id', $organizationId)
            ->find($campaignId);

        if ($campaign === null) {
            throw new CampaignNotFoundException($campaignId);
        }

        $query = CampaignPublicVisit::query()->where('campaign_id', $campaign->id);

        return [
            'views' => (clone $query)->count(),
            'unique_visitors' => (clone $query)->distinct()->count('visitor_hash'),
            'completions' => (clone $query)->whereNotNull('completed_at')->count(),
            'shares' => (int) (clone $query)->sum('share_count'),
            'wasplex_cta_clicks' => (clone $query)->whereNotNull('join_clicked_at')->count(),
        ];
    }

    public function publicUrl(Campaign $campaign): ?string
    {
        if ($campaign->public_slug === null) {
            return null;
        }

        return url('/c/'.$campaign->public_slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentCampaign(Campaign $campaign): ?array
    {
        $version = $campaign->currentVersion();
        if ($version === null) {
            return null;
        }

        $brand = $this->brands->find($campaign->organization_id, $campaign->brand_id);
        $asset = null;
        $assetId = $version->creative_configuration['asset_id'] ?? null;

        if (is_string($assetId) && $assetId !== '') {
            $asset = $this->creativeAssets->find($campaign->organization_id, $assetId);
        }

        return [
            'id' => $campaign->id,
            'slug' => $campaign->public_slug,
            'title' => trim((string) ($version->creative_configuration['title'] ?? '')) ?: ($brand?->name ?? 'Publicité Wasplex'),
            'brand_name' => $brand?->name ?? 'Annonceur Wasplex',
            'objective_code' => $campaign->objective_code,
            'cta_label' => Campaign::ctaFor($campaign->objective_code),
            'creative' => $asset === null ? null : [
                'url' => $asset->url,
                'type' => $asset->type,
                'duration' => $asset->duration,
            ],
        ];
    }

    private function visibleCampaign(string $slug): ?Campaign
    {
        return $this->visibleCampaignQuery()
            ->where('public_slug', $slug)
            ->with('versions')
            ->first();
    }

    private function visibleCampaignQuery(): Builder
    {
        $today = Carbon::now('UTC')->toDateString();

        return Campaign::query()
            ->where('distribution_mode', Campaign::DISTRIBUTION_MEMBERS_PUBLIC)
            ->where('status', Campaign::STATUS_APPROVED)
            ->where(fn (Builder $query) => $query->whereNull('scheduled_start')->orWhere('scheduled_start', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('scheduled_end')->orWhere('scheduled_end', '>=', $today));
    }

    private function updateVisit(string $slug, string $visitId, callable $callback): ?CampaignPublicVisit
    {
        $campaign = $this->visibleCampaign($slug);
        if ($campaign === null) {
            return null;
        }

        $visit = CampaignPublicVisit::query()
            ->where('campaign_id', $campaign->id)
            ->find($visitId);

        if ($visit === null) {
            return null;
        }

        $callback($visit);

        return $visit->refresh();
    }

    private function hashToken(string $purpose, string $token): string
    {
        return hash_hmac('sha256', $purpose.'|'.$token, (string) config('app.key'));
    }
}
