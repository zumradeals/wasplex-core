<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Advertising\Application\Services\AdvertisingConfigurationService;
use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Application\Services\AdvertisingSegmentService;
use App\Modules\Advertising\Application\Services\CampaignEligibilityService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingAdministrationEvent;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingConfigurationVersion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegment;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Campaign\Application\Services\CampaignReviewService;
use App\Modules\Campaign\Application\Services\CampaignService;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\Identity\Application\Services\IdentityProvisioner;
use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdvertisingAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('advertiser-studio.disk', 'public');
        config()->set('campaign.sandbox_cpm_minor', 1000);
        config()->set('campaign.minimum_budget_minor', 5000);
        config()->set('campaign-review.require_distinct_decider', false);
        config()->set('advertising.minimum_segment_size', 1);
        config()->set('advertising.estimate_rounding_step', 1);
        config()->set('advertising.frequency_limit', 3);
        config()->set('advertising.fatigue_limit', 100);
    }

    public function test_published_controls_and_taxonomy_suspension_change_new_matching_decisions(): void
    {
        $user = $this->readyGoldUser();
        [$campaign] = $this->approvedOrangeCampaign();
        $administrator = $this->account();
        $matching = app(CampaignEligibilityService::class);
        $configuration = app(AdvertisingConfigurationService::class);

        $initial = $matching->evaluate($user, $campaign);
        $privacyVersion = $configuration->publish($administrator, [
            'minimum_segment_size' => 5,
            'estimate_rounding_step' => 1,
            'frequency_window_hours' => 24,
            'frequency_limit' => 3,
            'fatigue_limit' => 100,
        ]);
        $privacyHold = $matching->evaluate($user, $campaign);
        $activeVersion = $configuration->publish($administrator, [
            'minimum_segment_size' => 1,
            'estimate_rounding_step' => 1,
            'frequency_window_hours' => 24,
            'frequency_limit' => 3,
            'fatigue_limit' => 100,
        ]);
        $reactivated = $matching->evaluate($user, $campaign);
        $taxonomy = AdvertisingTaxonomy::query()
            ->where('code', 'telecom.network.primary')
            ->firstOrFail();
        $configuration->setTaxonomyStatus($taxonomy, 'suspended', $administrator);
        $taxonomyHold = $matching->evaluate($user, $campaign);
        $audit = $configuration->aggregateAudit();

        expect($initial->decision)->toBe('eligible')
            ->and($privacyVersion->version)->toBe(1)
            ->and($privacyHold->decision)->toBe('withheld')
            ->and($privacyHold->exclusion_codes)->toContain('privacy_threshold_not_met')
            ->and($activeVersion->version)->toBe(2)
            ->and($reactivated->decision)->toBe('eligible')
            ->and($taxonomyHold->id)->not->toBe($reactivated->id)
            ->and($taxonomyHold->decision)->toBe('withheld')
            ->and($taxonomyHold->exclusion_codes)->toContain('targeting_taxonomy_unavailable')
            ->and(AdvertisingConfigurationVersion::query()->count())->toBe(2)
            ->and(AdvertisingAdministrationEvent::query()->count())->toBe(3)
            ->and($audit['matches']['total'])->toBe(4)
            ->and($audit['privacy']['subjectHashesReturned'])->toBeFalse()
            ->and($audit['privacy']['accountIdsReturned'])->toBeFalse()
            ->and($audit['privacy']['profilesReturned'])->toBeFalse()
            ->and(json_encode($audit))->not->toContain($user->id);
    }

    public function test_republishing_identical_controls_is_idempotent(): void
    {
        $administrator = $this->account();
        $configuration = app(AdvertisingConfigurationService::class);
        $settings = [
            'minimum_segment_size' => 25,
            'estimate_rounding_step' => 10,
            'frequency_window_hours' => 24,
            'frequency_limit' => 3,
            'fatigue_limit' => 100,
        ];

        $first = $configuration->publish($administrator, $settings);
        $replayed = $configuration->publish($administrator, $settings);

        expect($replayed->id)->toBe($first->id)
            ->and(AdvertisingConfigurationVersion::query()->count())->toBe(1)
            ->and(AdvertisingAdministrationEvent::query()->count())->toBe(1)
            ->and($configuration->runtime()['version'])->toBe(1);
    }

    /** @return array{0:Campaign,1:AdvertisingSegment} */
    private function approvedOrangeCampaign(): array
    {
        [$advertiser, $space, $brand, $asset] = $this->studio('Orange Côte d’Ivoire');
        $wallet = app(WalletCatalog::class)->forSpace($space);
        $this->creditWallet($wallet, 100000);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->create($space, $advertiser, $this->campaignData($brand, $asset));
        $segment = app(AdvertisingSegmentService::class)->configure($campaign, $advertiser, [
            [
                'taxonomy_code' => 'telecom.network.primary',
                'operator' => 'equals',
                'values' => ['orange'],
                'required' => true,
            ],
            [
                'taxonomy_code' => 'interest.mobile_internet',
                'operator' => 'equals',
                'values' => ['yes'],
                'required' => true,
            ],
            [
                'taxonomy_code' => 'geo.ci.abidjan.commune',
                'operator' => 'equals',
                'values' => ['cocody'],
                'required' => true,
            ],
        ]);
        $quote = $campaigns->quote($campaign, $space, $advertiser);
        $campaigns->fund($campaign, $quote, $space, $advertiser);
        $submitted = $campaigns->submit($campaign->refresh(), $space, $advertiser);
        $approved = app(CampaignReviewService::class)->approve(
            $submitted,
            $this->account(),
            'Campagne Orange conforme pour l’administration P008.',
        );

        return [$approved, $segment->refresh()->load(['rules.taxonomy', 'campaignVersion'])];
    }

    private function readyGoldUser(): Account
    {
        $user = app(IdentityProvisioner::class)->createAccount(
            displayName: 'Utilisateur Gold Cocody Administration',
            email: 'gold-admin-'.Str::lower(Str::random(8)).'@example.com',
            password: 'Wasplex-P008-Admin7',
        );
        $this->artisan('economic:bootstrap')->assertSuccessful();
        $this->artisan('advertising:bootstrap')->assertSuccessful();
        $this->subscribe($user, 'GOLD');
        $consents = app(AdvertisingConsentService::class);

        foreach ([
            'advertising_personalization',
            'smart_profile_usage',
            'approximate_location_targeting',
        ] as $purpose) {
            $consents->decide($user, $purpose, AdvertisingConsentStatus::Granted);
        }

        $profiles = app(AdvertisingProfileService::class);
        $profiles->answer($user, 'primary_telecom_network', 'orange');
        $profiles->answer($user, 'mobile_internet_interest', 'yes');
        $profiles->answer($user, 'abidjan_approximate_commune', 'cocody');

        return $user;
    }

    private function subscribe(Account $account, string $classCode): void
    {
        $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
        $planId = (string) Str::uuid();
        DB::table('subscription_plans')->insert([
            'id' => $planId,
            'code' => 'TEST-'.$classCode.'-'.Str::upper(Str::random(6)),
            'economic_class_id' => $economicClass->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'subscription_plan_id' => $planId,
            'economic_class_id' => $economicClass->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0:Account,1:UserSpace,2:Brand,3:CreativeAsset} */
    private function studio(string $name): array
    {
        $account = $this->account();
        $organization = Organization::query()->create([
            'created_by_account_id' => $account->id,
            'name' => $name,
            'slug' => 'orange-admin-'.substr($account->id, -6),
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
            UploadedFile::fake()->create('orange-admin.png', 200, 'image/png'),
            null,
            ['label' => 'Offre Internet Orange', 'usage_context' => 'campaign'],
        );
        $brand = app(BrandService::class)->create($space, $account, [
            'public_name' => $name,
            'slogan' => 'Internet mobile pour tous',
            'description' => 'Marque de démonstration administration P008.',
            'primary_color' => '#FF7900',
            'secondary_color' => '#111111',
            'logo_asset_id' => $asset->id,
        ]);

        return [$account, $space, $brand, $asset];
    }

    private function account(): Account
    {
        return Account::query()->create([
            'status' => AccountStatus::Active,
            'password_hash' => str_repeat('x', 60),
            'locale' => 'fr',
            'timezone' => 'Africa/Abidjan',
        ]);
    }

    /** @return array<string, mixed> */
    private function campaignData(Brand $brand, CreativeAsset $asset): array
    {
        return [
            'name' => 'Orange Internet Cocody Administration',
            'brand_id' => $brand->id,
            'objective' => 'conversion',
            'headline' => 'Votre offre Internet mobile Orange',
            'body' => 'Une offre destinée aux utilisateurs intéressés à Cocody.',
            'call_to_action' => 'Découvrir',
            'destination_url' => 'https://example.test/orange-internet-admin',
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDays(10)->toDateString(),
            'territory_name' => 'Cocody',
            'radius_km' => 10,
            'selected_classes' => ['GOLD', 'PLATINUM'],
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
            type: 'TEST_P008_ADMIN_ADVERTISER_CREDIT',
            sourceModule: 'advertising-administration-tests',
            businessReference: "p008-admin-wallet-credit:{$wallet->id}",
            idempotencyKey: "p008-admin-wallet-credit:{$wallet->id}:{$amount}",
            unit: 'WP',
            currency: 'XOF',
            entries: [
                new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test P008 administration'),
                new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet P008 administration'),
            ],
        ));
        app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
    }
}
