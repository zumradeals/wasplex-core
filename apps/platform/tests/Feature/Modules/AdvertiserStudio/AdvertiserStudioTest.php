<?php

namespace Tests\Feature\Modules\AdvertiserStudio;

use App\Modules\AdvertiserStudio\Application\Services\AdvertiserProfileService;
use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\AdvertiserProfile;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\BrandVersion;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAssetVersion;
use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdvertiserStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('advertiser-studio.disk', 'public');
    }

    public function test_profile_is_created_once_and_updates_the_studio_label(): void
    {
        [$account, $space] = $this->studio('GamaDeals');
        $service = app(AdvertiserProfileService::class);

        $profile = $service->ensure($space, $account);
        $updated = $service->update($space, $account, [
            'display_name' => 'GamaDeals Côte d’Ivoire',
            'legal_name' => 'GAMAD SARL',
            'industry' => 'Commerce numérique',
            'website_url' => 'https://gamadeals.example',
            'country_code' => 'ci',
            'city' => 'Abidjan',
            'phone' => '+2250102030405',
            'description' => 'Services numériques et logiciels professionnels.',
        ]);

        expect($profile->id)->toBe($updated->id)
            ->and(AdvertiserProfile::query()->count())->toBe(1)
            ->and($updated->country_code)->toBe('CI')
            ->and($service->completion($updated))->toBe(100)
            ->and($space->refresh()->label)->toBe('GamaDeals Côte d’Ivoire')
            ->and($space->organization?->refresh()->name)->toBe('GamaDeals Côte d’Ivoire');
    }

    public function test_brand_changes_create_published_versions_without_erasing_history(): void
    {
        [$account, $space] = $this->studio('GamaDeals');
        $service = app(BrandService::class);

        $brand = $service->create($space, $account, [
            'public_name' => 'GamaDeals',
            'slogan' => 'Les bons logiciels, simplement.',
            'description' => 'Distribution de logiciels professionnels.',
            'primary_color' => '#0EA5E9',
            'secondary_color' => '#F97316',
            'logo_asset_id' => null,
        ]);
        $firstVersion = $brand->active_version_id;

        $updated = $service->update($brand, $space, $account, [
            'public_name' => 'GamaDeals Pro',
            'slogan' => 'Les outils des professionnels.',
            'description' => 'Une identité améliorée sans effacer la précédente.',
            'primary_color' => '#0284C7',
            'secondary_color' => '#FB923C',
            'logo_asset_id' => null,
        ]);

        expect(Brand::query()->count())->toBe(1)
            ->and(BrandVersion::query()->count())->toBe(2)
            ->and($updated->active_version_id)->not->toBe($firstVersion)
            ->and($updated->activeVersion?->version)->toBe(2)
            ->and($updated->activeVersion?->public_name)->toBe('GamaDeals Pro')
            ->and(BrandVersion::query()->findOrFail($firstVersion)->public_name)->toBe('GamaDeals');
    }

    public function test_media_is_stored_versioned_and_isolated_by_advertiser_space(): void
    {
        [$account, $space] = $this->studio('GamaDeals');
        [, $otherSpace] = $this->studio('Autre annonceur');
        $service = app(CreativeAssetService::class);
        $file = UploadedFile::fake()->create('autodesk-promo.png', 240, 'image/png');

        $asset = $service->store($space, $account, $file, null, [
            'label' => 'Autodesk promotion',
            'alt_text' => 'Affiche promotionnelle Autodesk',
            'usage_context' => 'campaign',
        ]);
        $service->updateMetadata($asset, $space, $account, [
            'label' => 'Autodesk promotion août',
            'alt_text' => 'Affiche Autodesk mise à jour',
            'usage_context' => 'campaign',
        ]);

        expect(CreativeAsset::query()->count())->toBe(1)
            ->and(CreativeAssetVersion::query()->count())->toBe(2)
            ->and($asset->versions()->latest('version')->firstOrFail()->label)->toBe('Autodesk promotion août');
        Storage::disk('public')->assertExists($asset->path);

        $this->expectException(ValidationException::class);
        $service->updateMetadata($asset, $otherSpace, $account, ['label' => 'Interdit']);
    }

    public function test_active_logo_cannot_be_archived_until_brand_releases_it(): void
    {
        [$account, $space] = $this->studio('GamaDeals');
        $assets = app(CreativeAssetService::class);
        $brands = app(BrandService::class);
        $logo = $assets->store(
            $space,
            $account,
            UploadedFile::fake()->create('logo.png', 80, 'image/png'),
            null,
            ['label' => 'Logo GamaDeals'],
        );
        $brand = $brands->create($space, $account, [
            'public_name' => 'GamaDeals',
            'slogan' => null,
            'description' => null,
            'primary_color' => '#0EA5E9',
            'secondary_color' => '#F97316',
            'logo_asset_id' => $logo->id,
        ]);

        try {
            $assets->archive($logo, $space);
            self::fail('Le logo actif aurait dû être protégé.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $brands->update($brand, $space, $account, [
            'public_name' => 'GamaDeals',
            'slogan' => null,
            'description' => null,
            'primary_color' => '#0EA5E9',
            'secondary_color' => '#F97316',
            'logo_asset_id' => null,
        ]);
        $assets->archive($logo->refresh(), $space);

        expect(CreativeAsset::query()->count())->toBe(0);
        Storage::disk('public')->assertMissing($logo->path);
    }

    public function test_bootstrap_command_grants_p005_capabilities_to_existing_studios(): void
    {
        [$account, $space] = $this->studio('GamaDeals');

        $this->artisan('studio:bootstrap')->assertSuccessful();

        expect(AdvertiserProfile::query()->where('advertiser_space_id', $space->id)->exists())->toBeTrue()
            ->and(CapabilityGrant::query()
                ->where('account_id', $account->id)
                ->where('space_id', $space->id)
                ->where('capability', 'advertiser.brand.manage')
                ->whereNull('revoked_at')
                ->exists())->toBeTrue()
            ->and(CapabilityGrant::query()
                ->where('account_id', $account->id)
                ->where('space_id', $space->id)
                ->where('capability', 'advertiser.media.upload')
                ->whereNull('revoked_at')
                ->exists())->toBeTrue();
    }

    /** @return array{0:Account,1:UserSpace} */
    private function studio(string $name): array
    {
        $account = Account::query()->create([
            'status' => AccountStatus::Active,
            'password_hash' => str_repeat('x', 60),
            'locale' => 'fr',
            'timezone' => 'Africa/Abidjan',
        ]);
        $organization = Organization::query()->create([
            'created_by_account_id' => $account->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.substr($account->id, -6),
            'kind' => 'advertiser',
            'status' => 'active',
        ]);
        $space = UserSpace::query()->create([
            'account_id' => $account->id,
            'organization_id' => $organization->id,
            'kind' => SpaceKind::Advertiser,
            'label' => $name,
            'status' => 'active',
        ]);

        return [$account, $space->load('organization')];
    }
}
