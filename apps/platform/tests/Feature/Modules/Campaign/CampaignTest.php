<?php

namespace Tests\Feature\Modules\Campaign;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaign\Application\Services\CampaignService;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignAudience;
use App\Modules\Campaign\Infrastructure\Models\CampaignBudgetReservation;
use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use App\Modules\Campaign\Infrastructure\Models\CampaignPriceCatalog;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Domain\Exceptions\InsufficientWalletBalance;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('advertiser-studio.disk', 'public');
        config()->set('campaign.sandbox_cpm_minor', 1000);
        config()->set('campaign.minimum_budget_minor', 5000);
    }

    public function test_campaign_versions_quote_and_planning_estimate_are_created_without_personal_data(): void
    {
        [$account, $space, $brand, $asset] = $this->studio('GamaDeals');
        $service = app(CampaignService::class);
        $campaign = $service->create($space, $account, $this->campaignData($brand, $asset));
        $updated = $service->save($campaign, $space, $account, [
            ...$this->campaignData($brand, $asset),
            'headline' => 'Autodesk pour les professionnels',
            'budget_minor' => 40000,
            'current_step' => 6,
        ]);
        $quote = $service->quote($updated, $space, $account);
        $audience = $quote->audience;

        expect(Campaign::query()->count())->toBe(1)
            ->and(CampaignVersion::query()->count())->toBe(2)
            ->and($updated->activeVersion?->headline)->toBe('Autodesk pour les professionnels')
            ->and(CampaignAudience::query()->count())->toBe(1)
            ->and($audience->estimate_kind)->toBe('planning')
            ->and($audience->assumptions['personal_data_used'] ?? true)->toBeFalse()
            ->and($audience->assumptions['smartprofile_used'] ?? true)->toBeFalse()
            ->and($quote->gross_amount_minor)->toBe(40000)
            ->and($quote->platform_share_minor)->toBe(20000)
            ->and($quote->user_share_minor)->toBe(20000)
            ->and($quote->estimated_impressions)->toBe(40000)
            ->and($updated->refresh()->status)->toBe('quoted');
    }

    public function test_funding_reserves_the_advertiser_wallet_and_submission_keeps_value_protected(): void
    {
        [$account, $space, $brand, $asset] = $this->studio('GamaDeals');
        $wallet = app(WalletCatalog::class)->forSpace($space);
        $this->creditWallet($wallet, 100000);
        $service = app(CampaignService::class);
        $campaign = $service->create($space, $account, $this->campaignData($brand, $asset));
        $quote = $service->quote($campaign, $space, $account);
        $funding = $service->fund($campaign, $quote, $space, $account);
        $submitted = $service->submit($campaign->refresh(), $space);

        expect($funding->status)->toBe('reserved')
            ->and(CampaignFunding::query()->count())->toBe(1)
            ->and(CampaignBudgetReservation::query()->count())->toBe(1)
            ->and(WalletReservation::query()->count())->toBe(1)
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(65000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(35000)
            ->and($submitted->status)->toBe('submitted')
            ->and($submitted->funding?->status)->toBe('submitted')
            ->and($submitted->funding?->budgetReservation?->status)->toBe('active');
    }

    public function test_funding_is_refused_when_the_wallet_balance_is_insufficient(): void
    {
        [$account, $space, $brand, $asset] = $this->studio('GamaDeals');
        app(WalletCatalog::class)->forSpace($space);
        $service = app(CampaignService::class);
        $campaign = $service->create($space, $account, $this->campaignData($brand, $asset));
        $quote = $service->quote($campaign, $space, $account);

        $this->expectException(InsufficientWalletBalance::class);
        $service->fund($campaign, $quote, $space, $account);
    }

    public function test_campaign_cannot_reuse_a_brand_or_media_from_another_advertiser(): void
    {
        [$account, $space] = $this->studio('GamaDeals');
        [, , $otherBrand, $otherAsset] = $this->studio('Autre annonceur');

        $this->expectException(ValidationException::class);
        app(CampaignService::class)->create($space, $account, $this->campaignData($otherBrand, $otherAsset));
    }

    public function test_cancellation_releases_a_reserved_campaign_budget(): void
    {
        [$account, $space, $brand, $asset] = $this->studio('GamaDeals');
        $wallet = app(WalletCatalog::class)->forSpace($space);
        $this->creditWallet($wallet, 100000);
        $service = app(CampaignService::class);
        $campaign = $service->create($space, $account, $this->campaignData($brand, $asset));
        $quote = $service->quote($campaign, $space, $account);
        $service->fund($campaign, $quote, $space, $account);
        $cancelled = $service->cancel($campaign->refresh(), $space, $account);

        expect($cancelled->status)->toBe('cancelled')
            ->and($wallet->projection()->firstOrFail()->available_minor)->toBe(100000)
            ->and($wallet->projection()->firstOrFail()->reserved_minor)->toBe(0)
            ->and($cancelled->funding?->status)->toBe('released')
            ->and($cancelled->funding?->budgetReservation?->status)->toBe('released');
    }

    public function test_bootstrap_creates_the_sandbox_catalog_and_grants_campaign_capabilities(): void
    {
        [$account, $space] = $this->studio('GamaDeals');

        $this->artisan('campaign:bootstrap')->assertSuccessful();

        expect(CampaignPriceCatalog::query()->where('status', 'published')->count())->toBe(1)
            ->and(CapabilityGrant::query()
                ->where('account_id', $account->id)
                ->where('space_id', $space->id)
                ->where('capability', 'advertiser.campaign.submit')
                ->whereNull('revoked_at')
                ->exists())->toBeTrue();
    }

    /** @return array{0:Account,1:UserSpace,2:Brand,3:CreativeAsset} */
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
        ])->load('organization');
        $asset = app(CreativeAssetService::class)->store(
            $space,
            $account,
            UploadedFile::fake()->create('campagne.png', 200, 'image/png'),
            null,
            ['label' => "Contenu {$name}", 'usage_context' => 'campaign'],
        );
        $brand = app(BrandService::class)->create($space, $account, [
            'public_name' => $name,
            'slogan' => 'La marque de test',
            'description' => 'Identité publicitaire de test.',
            'primary_color' => '#0EA5E9',
            'secondary_color' => '#F97316',
            'logo_asset_id' => $asset->id,
        ]);

        return [$account, $space, $brand, $asset];
    }

    /** @return array<string, mixed> */
    private function campaignData(Brand $brand, CreativeAsset $asset): array
    {
        return [
            'name' => 'Autodesk Abidjan',
            'brand_id' => $brand->id,
            'objective' => 'conversion',
            'headline' => 'Autodesk 3 ans',
            'body' => 'Une offre destinée aux professionnels.',
            'call_to_action' => 'Découvrir',
            'destination_url' => 'https://example.test/autodesk',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-17',
            'territory_name' => 'Abidjan',
            'radius_km' => 40,
            'selected_classes' => ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM'],
            'budget_minor' => 35000,
            'creative_asset_ids' => [$asset->id],
            'current_step' => 5,
        ];
    }

    private function creditWallet(Wallet $wallet, int $amount): void
    {
        app('Illuminate\Contracts\Console\Kernel')->call('ledger:bootstrap-core');
        $clearing = app(WalletCatalog::class)->clearingAccountId();
        $posted = app(LedgerContract::class)->post(new PostingRequest(
            journalCode: 'WALLET',
            type: 'TEST_CAMPAIGN_WALLET_CREDIT',
            sourceModule: 'campaign-tests',
            businessReference: "campaign-wallet-credit:{$wallet->id}",
            idempotencyKey: "campaign-wallet-credit:{$wallet->id}:{$amount}",
            unit: 'WP',
            currency: 'XOF',
            entries: [
                new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test'),
                new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet campagne'),
            ],
        ));
        app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
    }
}
