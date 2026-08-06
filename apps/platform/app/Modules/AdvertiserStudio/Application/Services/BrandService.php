<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\BrandColor;
use App\Modules\AdvertiserStudio\Infrastructure\Models\BrandGuideline;
use App\Modules\AdvertiserStudio\Infrastructure\Models\BrandTypography;
use Illuminate\Database\Eloquent\Collection;

/**
 * docs/13 §13-17: le laboratoire de marque. Toute lecture/écriture est
 * scopée à l'organisation appelante — un identifiant de marque d'une autre
 * organisation lève BrandNotFoundException plutôt que de fuiter son
 * existence (docs/13 §102: "aucune fuite entre marques").
 */
final class BrandService
{
    public function __construct(private readonly AdvertiserProfileService $profiles) {}

    /**
     * @return Collection<int, Brand>
     */
    public function list(string $organizationId): Collection
    {
        $profile = $this->profiles->getOrCreate($organizationId);

        return $profile->brands()->with(['colors', 'typographies', 'guidelines'])->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $organizationId, array $data): Brand
    {
        $profile = $this->profiles->getOrCreate($organizationId);

        if (! $profile->canCreate()) {
            throw new StudioCreationRestrictedException($organizationId);
        }

        return Brand::query()->create([
            'advertiser_profile_id' => $profile->id,
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'sector' => $data['sector'] ?? null,
            'description' => $data['description'] ?? null,
            'slogan' => $data['slogan'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'website' => $data['website'] ?? null,
            'status' => Brand::STATUS_DRAFT,
        ]);
    }

    public function find(string $organizationId, string $brandId): Brand
    {
        $profile = $this->profiles->getOrCreate($organizationId);

        $brand = Brand::query()
            ->where('advertiser_profile_id', $profile->id)
            ->with(['colors', 'typographies', 'guidelines', 'creativeAssets'])
            ->find($brandId);

        if ($brand === null) {
            throw new BrandNotFoundException($brandId);
        }

        return $brand;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $organizationId, string $brandId, array $data): Brand
    {
        $brand = $this->find($organizationId, $brandId);
        $brand->update($data);

        return $brand->refresh();
    }

    /**
     * @param  array<int, array{name: string, hex: string, rgb?: ?string, cmyk?: ?string, usage: string, priority?: int}>  $colors
     */
    public function replaceColors(string $organizationId, string $brandId, array $colors): Brand
    {
        $brand = $this->find($organizationId, $brandId);

        $brand->colors()->delete();
        foreach ($colors as $color) {
            BrandColor::query()->create([
                'brand_id' => $brand->id,
                'name' => $color['name'],
                'hex' => $color['hex'],
                'rgb' => $color['rgb'] ?? null,
                'cmyk' => $color['cmyk'] ?? null,
                'usage' => $color['usage'],
                'priority' => $color['priority'] ?? 0,
            ]);
        }

        return $this->find($organizationId, $brandId);
    }

    /**
     * @param  array<int, array{role: string, family: string, usages?: ?string, recommended_sizes?: ?string}>  $typographies
     */
    public function replaceTypographies(string $organizationId, string $brandId, array $typographies): Brand
    {
        $brand = $this->find($organizationId, $brandId);

        $brand->typographies()->delete();
        foreach ($typographies as $typography) {
            BrandTypography::query()->create([
                'brand_id' => $brand->id,
                'role' => $typography['role'],
                'family' => $typography['family'],
                'usages' => $typography['usages'] ?? null,
                'recommended_sizes' => $typography['recommended_sizes'] ?? null,
            ]);
        }

        return $this->find($organizationId, $brandId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGuidelines(string $organizationId, string $brandId, array $data): Brand
    {
        $brand = $this->find($organizationId, $brandId);

        BrandGuideline::query()->updateOrCreate(['brand_id' => $brand->id], $data);

        return $this->find($organizationId, $brandId);
    }

    public function verify(Brand $brand): Brand
    {
        $brand->update(['status' => Brand::STATUS_VERIFIED, 'verified_at' => now()]);

        return $brand->refresh();
    }

    public function reject(Brand $brand, string $reason): Brand
    {
        $brand->update(['status' => Brand::STATUS_REJECTED, 'rejection_reason' => $reason]);

        return $brand->refresh();
    }
}
