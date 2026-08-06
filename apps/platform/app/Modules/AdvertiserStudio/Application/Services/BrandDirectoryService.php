<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserStudio\Application\ValueObjects\BrandSummary;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;

final class BrandDirectoryService implements BrandDirectoryContract
{
    public function find(string $organizationId, string $brandId): ?BrandSummary
    {
        $brand = Brand::query()
            ->whereHas('advertiserProfile', fn ($query) => $query->where('organization_id', $organizationId))
            ->find($brandId);

        if ($brand === null) {
            return null;
        }

        return new BrandSummary(
            id: $brand->id,
            name: $brand->name,
            status: $brand->status,
            primaryLogoAssetId: $brand->primary_logo_asset_id,
        );
    }
}
