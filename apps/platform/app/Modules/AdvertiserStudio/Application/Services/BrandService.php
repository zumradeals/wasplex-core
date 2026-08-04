<?php

namespace App\Modules\AdvertiserStudio\Application\Services;

use App\Modules\AdvertiserStudio\Domain\Enums\BrandStatus;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\BrandVersion;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class BrandService
{
    /** @param array<string, mixed> $data */
    public function create(UserSpace $space, Account $actor, array $data): Brand
    {
        $this->guardSpace($space);

        return DB::transaction(function () use ($space, $actor, $data): Brand {
            $publicName = trim((string) $data['public_name']);
            $brand = Brand::query()->create([
                'advertiser_space_id' => $space->id,
                'organization_id' => $space->organization_id,
                'created_by_account_id' => $actor->id,
                'name' => $publicName,
                'slug' => $this->uniqueSlug($space, $publicName),
                'status' => BrandStatus::Active,
            ]);

            $version = $this->createVersion($brand, $space, $actor, $data, 1);
            $brand->forceFill(['active_version_id' => $version->id])->save();
            $this->attachLogo($brand, $version);

            DB::afterCommit(static fn () => event('advertiser.brand.created', [$brand->id, $space->id]));

            return $brand->load(['activeVersion.logoAsset', 'versions.logoAsset']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Brand $brand, UserSpace $space, Account $actor, array $data): Brand
    {
        $this->guardOwnership($brand, $space);

        return DB::transaction(function () use ($brand, $space, $actor, $data): Brand {
            $locked = Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $nextVersion = (int) BrandVersion::query()->where('brand_id', $locked->id)->max('version') + 1;
            $version = $this->createVersion($locked, $space, $actor, $data, $nextVersion);

            $locked->forceFill([
                'name' => $version->public_name,
                'active_version_id' => $version->id,
                'status' => BrandStatus::Active,
            ])->save();
            $this->attachLogo($locked, $version);

            DB::afterCommit(static fn () => event('advertiser.brand.updated', [$locked->id, $version->id]));

            return $locked->load(['activeVersion.logoAsset', 'versions.logoAsset']);
        });
    }

    public function archive(Brand $brand, UserSpace $space): Brand
    {
        $this->guardOwnership($brand, $space);
        $brand->forceFill(['status' => BrandStatus::Archived])->save();

        return $brand->refresh();
    }

    /** @param array<string, mixed> $data */
    private function createVersion(Brand $brand, UserSpace $space, Account $actor, array $data, int $version): BrandVersion
    {
        $logo = $this->logo($space, $data['logo_asset_id'] ?? null);

        return $brand->versions()->create([
            'version' => $version,
            'public_name' => trim((string) $data['public_name']),
            'slogan' => $this->nullableString($data['slogan'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'primary_color' => strtoupper((string) ($data['primary_color'] ?? '#0EA5E9')),
            'secondary_color' => strtoupper((string) ($data['secondary_color'] ?? '#F97316')),
            'logo_asset_id' => $logo?->id,
            'state' => 'published',
            'created_by_account_id' => $actor->id,
            'published_by_account_id' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function attachLogo(Brand $brand, BrandVersion $version): void
    {
        $brand->assets()->wherePivot('role', 'logo')->detach();

        if ($version->logo_asset_id !== null) {
            $brand->assets()->attach($version->logo_asset_id, [
                'id' => (string) Str::ulid(),
                'role' => 'logo',
                'sort_order' => 0,
            ]);
        }
    }

    private function logo(UserSpace $space, mixed $assetId): ?CreativeAsset
    {
        if ($assetId === null || trim((string) $assetId) === '') {
            return null;
        }

        $asset = CreativeAsset::query()
            ->where('advertiser_space_id', $space->id)
            ->where('kind', 'image')
            ->find((string) $assetId);

        if (! $asset instanceof CreativeAsset) {
            throw ValidationException::withMessages([
                'logo_asset_id' => 'Le logo sélectionné ne fait pas partie de cette médiathèque.',
            ]);
        }

        return $asset;
    }

    private function uniqueSlug(UserSpace $space, string $name): string
    {
        $base = Str::slug($name) ?: 'marque';
        $slug = $base;
        $suffix = 2;

        while (Brand::query()->where('advertiser_space_id', $space->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function guardSpace(UserSpace $space): void
    {
        if ($space->kind !== SpaceKind::Advertiser || $space->organization_id === null) {
            throw new LogicException('Une marque exige un espace annonceur actif.');
        }
    }

    private function guardOwnership(Brand $brand, UserSpace $space): void
    {
        $this->guardSpace($space);

        if ($brand->advertiser_space_id !== $space->id) {
            throw ValidationException::withMessages(['brand' => 'Cette marque appartient à un autre espace annonceur.']);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
