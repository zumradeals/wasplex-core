<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\AdvertiserStudio\Application\ValueObjects\CreativeAssetSummary;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;

final class CreativeAssetDirectoryService implements CreativeAssetDirectoryContract
{
    public function find(string $organizationId, string $assetId): ?CreativeAssetSummary
    {
        $asset = CreativeAsset::query()
            ->whereHas('brand.advertiserProfile', fn ($query) => $query->where('organization_id', $organizationId))
            ->find($assetId);

        if ($asset === null) {
            return null;
        }

        return new CreativeAssetSummary(
            id: $asset->id,
            brandId: $asset->brand_id,
            type: $asset->type,
            url: $asset->url(),
            moderationStatus: $asset->moderation_status,
            width: $asset->width,
            height: $asset->height,
            duration: $asset->duration,
            durationMs: $asset->duration_ms,
        );
    }
}
