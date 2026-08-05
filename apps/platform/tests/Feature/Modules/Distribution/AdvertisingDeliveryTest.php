<?php

namespace Tests\Feature\Modules\Distribution;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Application\Services\AdvertisingSegmentService;
use App\Modules\Advertising\Application\Services\CampaignEligibilityService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingFrequencyCounter;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Campaign\Application\Services\CampaignReviewService;
use App\Modules\Campaign\Application\Services\CampaignService;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Distribution\Application\Services\AdvertisingDeliveryService;
use App\Modules\Distribution\Domain\Enums\AdvertisingDeliveryStatus;
use App\Modules\Distribution\Domain\Exceptions\DistributionException;
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
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\ValueEngine\Infrastructure\Models\ValueAttempt;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdvertisingDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('app.key', 'p009b-distribution-test-key');
        config()->set('advertiser-studio.disk', 'public');
        config()->set('campaign.sandbox_cpm_minor', 1000);
        config()->set('campaign.minimum_budget_minor', 5000);
        config()->set('campaign-review.require_distinct_decider', false);
        config()->set('advertising.minimum_segment_size', 1);
        config()->set('advertising.estimate_rounding_step', 1);
        config()->set('advertising.frequency_limit', 3);
        config()->set('advertising.fatigue_limit', 100);
        config()->set('profile_intelligence.default_market', 'CI');
        config()->set('distribution.default_market', 'CI');
        config()->set('distribution.default_currency', 'XOF');
        config()->set('distribution.feed_session_ttl_seconds', 900);
        config()->set('distribution.delivery_lease_ttl_seconds', 30);
        config()->set('distribution.match_ttl_minutes', 60);
    }

    public function test_ad_delivered_consumes_one_quota_once_without_creating_financial_value(): void
    {
        [$user] = $this->readyDelivery();
        $service = app(AdvertisingDeliveryService::class);
        $session = $service->startSession($user, 'feed-session-p009b-001', 'Cocody', 'CI');
        $replayedSession = $service->startSession($user, 'feed-session-p009b-001', 'Cocody', 'CI');
        $ledgerBefore = LedgerTransaction::query()->count();
        $walletOperationsBefore = WalletOperation::query()->count();
        $attemptsBefore = ValueAttempt::query()->count();

        $prepared = $service->reserveNext($user, $session, 'delivery-p009b-001');
        $replayedPrepared = $service->reserveNext($user, $session, 'delivery-p009b-001');

        self::assertSame($session->id, $replayedSession->id);
        self::assertSame($prepared->id, $replayedPrepared->id);
        self::assertSame(AdvertisingDeliveryStatus::Prepared, $prepared->status);
        self::assertSame(
            0,
            (int) DB::table('subscription_quota_counters')
                ->where('id', $prepared->subscription_quota_counter_id)
                ->value('consumed'),
        );

        $delivered = $service->deliver($user, $prepared, 'delivery-p009b-001');
        $replayed = $service->deliver($user, $delivered, 'delivery-p009b-001');

        self::assertSame($delivered->id, $replayed->id);
        self::assertSame(AdvertisingDeliveryStatus::Delivered, $delivered->status);
        self::assertSame(
            1,
            (int) DB::table('subscription_quota_counters')
                ->where('id', $delivered->subscription_quota_counter_id)
                ->value('consumed'),
        );
        self::assertSame(
            1,
            DB::table('advertising_quota_consumptions')
                ->where('advertising_delivery_id', $delivered->id)
                ->count(),
        );
        self::assertSame(
            1,
            DB::table('advertising_delivery_events')
                ->where('advertising_delivery_id', $delivered->id)
                ->where('event_type', 'AdDelivered')
                ->count(),
        );
        self::assertSame(
            1,
            AdvertisingFrequencyCounter::query()
                ->where('account_id', $user->id)
                ->where('campaign_id', $delivered->campaign_id)
                ->value('impressions'),
        );
        self::assertSame($ledgerBefore, LedgerTransaction::query()->count());
        self::assertSame($walletOperationsBefore, WalletOperation::query()->count());
        self::assertSame($attemptsBefore, ValueAttempt::query()->count());
    }

    public function test_released_prepared_delivery_consumes_zero_quota_and_zero_frequency(): void
    {
        [$user] = $this->readyDelivery();
        $service = app(AdvertisingDeliveryService::class);
        $session = $service->startSession($user, 'feed-session-p009b-002');
        $prepared = $service->reserveNext($user, $session, 'delivery-p009b-002');
        $released = $service->release($user, $prepared, 'release-p009b-002');

        self::assertSame(AdvertisingDeliveryStatus::Invalidated, $released->status);
        self::assertSame(
            0,
            (int) DB::table('subscription_quota_counters')
                ->where('id', $released->subscription_quota_counter_id)
                ->value('consumed'),
        );
        self::assertSame(0, DB::table('advertising_quota_consumptions')->count());
        self::assertSame(0, AdvertisingFrequencyCounter::query()->count());
        self::assertSame(0, LedgerTransaction::query()->where('source_module', 'distribution')->count());
        self::assertSame(0, WalletOperation::query()->where('type', 'advertising_reward')->count());
    }

    public function test_exhausted_quota_blocks_selection_before_a_delivery_is_created(): void
    {
        [$user] = $this->readyDelivery();
        $service = app(AdvertisingDeliveryService::class);
        $session = $service->startSession($user, 'feed-session-p009b-003');
        DB::table('subscription_quota_counters')
            ->where('id', $session->subscription_quota_counter_id)
            ->update(['consumed' => DB::raw('quota_limit')]);

        $this->expectException(DistributionException::class);
        $this->expectExceptionMessage('quota publicitaire actif est épuisé');

        $service->reserveNext($user, $session, 'delivery-p009b-003');
    }

    public function test_a_delivery_is_strictly_isolated_to_its_authenticated_account(): void
    {
        [$user] = $this->readyDelivery();
        $other = app(IdentityProvisioner::class)->createAccount(
            displayName: 'Autre utilisateur P009-B',
            email: 'other-'.Str::lower(Str::random(8)).'@example.com',
            password: 'Wasplex-P009B-Secure7',
        );
        $service = app(AdvertisingDeliveryService::class);
        $session = $service->startSession($user, 'feed-session-p009b-004');
        $prepared = $service->reserveNext($user, $session, 'delivery-p009b-004');

        $this->expectException(DistributionException::class);
        $this->expectExceptionMessage('compte authentifié');

        $service->present($other, $prepared);
    }

    /** @return array{0:Account,1:Campaign,2:AdvertisingMatch} */
    private function readyDelivery(): array
    {
        $user = $this->readyGoldUser();
        $campaign = $this->approvedOrangeCampaign();
        $match = app(CampaignEligibilityService::class)->evaluate($user, $campaign);
        $this->artisan('value-engine:bootstrap-advertising')->assertSuccessful();

        return [$user, $campaign, $match];
    }

    private function approvedOrangeCampaign(): Campaign
    {
        [$advertiser, $space, $brand, $asset] = $this->studio('Orange Côte d’Ivoire');
        $wallet = app(WalletCatalog::class)->forSpace($space);
        $this->creditWallet($wallet, 100000);
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->create($space, $advertiser, $this->campaignData($brand, $asset));
        app(AdvertisingSegmentService::class)->configure($campaign, $advertiser, [
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

        return app(CampaignReviewService::class)->approve(
            $submitted,
            $this->account(),
            'Campagne conforme pour la livraison P009-B.',
        );
    }

    private function readyGoldUser(): Account
    {
        $user = app(IdentityProvisioner::class)->createAccount(
            displayName: 'Utilisateur P009-B',
            email: 'p009b-'.Str::lower(Str::random(8)).'@example.com',
            password: 'Wasplex-P009B-Secure7',
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
            'slug' => 'p009b-'.substr($account->id, -6),
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
            UploadedFile::fake()->create('p009b.png', 200, 'image/png'),
            null,
            ['label' => 'Création P009-B', 'usage_context' => 'campaign'],
        );
        $brand = app(BrandService::class)->create($space, $account, [
            'public_name' => $name,
            'slogan' => 'Internet mobile pour tous',
            'description' => 'Marque de démonstration P009-B.',
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
            'name' => 'Orange Internet P009-B',
            'brand_id' => $brand->id,
            'objective' => 'conversion',
            'headline' => 'Votre offre Internet mobile Orange',
            'body' => 'Une offre destinée aux utilisateurs intéressés.',
            'call_to_action' => 'Découvrir',
            'destination_url' => 'https://example.test/p009b',
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
        app('Illuminate\\Contracts\\Console\\Kernel')->call('ledger:bootstrap-core');
        $clearing = app(WalletCatalog::class)->clearingAccountId();
        $posted = app(LedgerContract::class)->post(new PostingRequest(
            journalCode: 'WALLET',
            type: 'TEST_P009B_ADVERTISER_CREDIT',
            sourceModule: 'distribution-tests',
            businessReference: 'p009b-wallet-credit:'.$wallet->id,
            idempotencyKey: 'p009b-wallet-credit:'.$wallet->id.':'.$amount,
            unit: 'WP',
            currency: 'XOF',
            entries: [
                new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test P009-B'),
                new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet P009-B'),
            ],
        ));
        app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
    }
}
