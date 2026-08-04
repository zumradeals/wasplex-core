<?php

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\AdvertiserProfile;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\BrandVersion;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAssetVersion;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class AdvertiserStudioPresenter
{
    public function __construct(private AdvertiserProfileService $profiles) {}

    /** @return array<string, mixed> */
    public function profile(AdvertiserProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'displayName' => $profile->display_name,
            'legalName' => $profile->legal_name,
            'industry' => $profile->industry,
            'websiteUrl' => $profile->website_url,
            'countryCode' => $profile->country_code,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'description' => $profile->description,
            'status' => $profile->status,
            'completion' => $this->profiles->completion($profile),
            'updatedAt' => $profile->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function brand(Brand $brand): array
    {
        $version = $brand->activeVersion;

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'status' => $brand->status->value,
            'versionCount' => $brand->versions_count ?? $brand->versions->count(),
            'activeVersion' => $version instanceof BrandVersion ? [
                'id' => $version->id,
                'version' => $version->version,
                'publicName' => $version->public_name,
                'slogan' => $version->slogan,
                'description' => $version->description,
                'primaryColor' => $version->primary_color,
                'secondaryColor' => $version->secondary_color,
                'logoAssetId' => $version->logo_asset_id,
                'logoUrl' => $version->logoAsset instanceof CreativeAsset ? $this->url($version->logoAsset) : null,
                'publishedAt' => $version->published_at?->toIso8601String(),
            ] : null,
            'createdAt' => $brand->created_at?->toIso8601String(),
            'updatedAt' => $brand->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function asset(CreativeAsset $asset): array
    {
        $metadata = $asset->versions->first();

        return [
            'id' => $asset->id,
            'brandId' => $asset->brand_id,
            'brandName' => $asset->brand?->name,
            'kind' => $asset->kind->value,
            'status' => $asset->status->value,
            'originalName' => $asset->original_name,
            'mimeType' => $asset->mime_type,
            'sizeBytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'durationSeconds' => $asset->duration_seconds,
            'url' => $this->url($asset),
            'label' => $metadata instanceof CreativeAssetVersion ? $metadata->label : null,
            'altText' => $metadata instanceof CreativeAssetVersion ? $metadata->alt_text : null,
            'usageContext' => $metadata instanceof CreativeAssetVersion ? $metadata->usage_context : null,
            'version' => $metadata instanceof CreativeAssetVersion ? $metadata->version : 1,
            'createdAt' => $asset->created_at?->toIso8601String(),
        ];
    }

    private function url(CreativeAsset $asset): ?string
    {
        try {
            return Storage::disk($asset->disk)->url($asset->path);
        } catch (Throwable) {
            return null;
        }
    }
}
