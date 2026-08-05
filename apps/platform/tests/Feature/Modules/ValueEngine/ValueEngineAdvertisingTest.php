<?php

namespace Tests\Feature\Modules\ValueEngine;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Application\Services\AdvertisingSegmentService;
use App\Modules\Advertising\Application\Services\CampaignEligibilityService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
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
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\ValueEngine\Application\Data\AttentionHeartbeatData;
use App\Modules\ValueEngine\Application\Services\AdvertisingValueEngine;
use App\Modules\ValueEngine\Domain\Enums\ValueAttemptStatus;
use App\Modules\ValueEngine\Infrastructure\Models\ValueCampaignBudgetCounter;
use App\Modules\ValueEngine\Infrastructure\Models\ValueOutboxEvent;
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

final class ValueEngineAdvertisingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config()->set('app.key', 'p009-super-engine-test-key');
        config()->set('advertiser-studio.disk', 'public');
        config()->set('campaign.sandbox_cpm_minor', 1000);
        config()->set('campaign.minimum_budget_minor', 5000);
        config()->set('campaign-review.require_distinct_decider', false);
        config()->set('advertising.minimum_segment_size', 1);
        config()->set('advertising.estimate_rounding_step', 1);
        config()->set('advertising.frequency_limit', 3);
        config()->set('advertising.fatigue_limit', 100);
        config()->set('profile_intelligence.default_market', 'CI');
        config()->set('value-engine.required_attention_ms', 1000);
        config()->set('value-engine.heartbeat_interval_ms', 500);
        config()->set('value-engine.attempt_ttl_seconds', 300);
    }

    public function test_start_reserves_one_idempotent_value_attempt_without_creating_money(): void
    {
        [$user, $campaign, $match] = $this->readyDelivery();
        $engine = app(AdvertisingValueEngine::class);
        $ledgerBefore = LedgerTransaction::query()->count();

        $started = $engine->start(
            account: $user,
            campaign: $campaign,
            match: $match,
            deviceSessionId: 'device-session-p009',
            idempotencyKey: 'delivery-p009-001',
            countryCode: 'CI',
        );
        $replayed = $engine->start(
            account: $user,
            campaign: $campaign,
            match: $match,
            deviceSessionId: 'device-session-p009',
            idempotencyKey: 'delivery-p009-001',
            countryCode: 'CI',
        );

        $counter = ValueCampaignBudgetCounter::query()->firstOrFail();

        self::assertSame($started->attempt->id, $replayed->attempt->id);
        self::assertSame($started->attentionToken, $replayed->attentionToken);
        self::assertSame(ValueAttemptStatus::Reserved, $started->attempt->status);
        self::assertGreaterThan(0, $started->quote->userAmountMinor);
        self::assertSame(
            $started->quote->grossAmountMinor,
            $started->quote->userAmountMinor + $started->quote->wasplexAmountMinor,
        );
        self::assertSame($started->quote->grossAmountMinor, $counter->reserved_amount_minor);
        self::assertSame($ledgerBefore, LedgerTransaction::query()->count());
        self::assertSame(
            1,
            ValueOutboxEvent::query()->where('event_code', 'VALUE_ATTEMPT_RESERVED')->count(),
        );
    }

    public function test_valid_attention_settles_once_and_credits_the_user_wallet_automatically(): void
    {
        [$user, $campaign, $match] = $this->readyDelivery();
        $engine = app(AdvertisingValueEngine::class);
        $started = $engine->start(
            account: $user,
            campaign: $campaign,
            match: $match,
            deviceSessionId: 'device-session-p009',
            idempotencyKey: 'delivery-p009-002',
            countryCode: 'CI',
        );
        $advertiserWallet = Wallet::query()->findOrFail($started->attempt->advertiser_wallet_id);
        $advertiserReservedBefore = $advertiserWallet->projection()->firstOrFail()->reserved_minor;
        $ledgerBefore = LedgerTransaction::query()->count();

        $proofPending = $engine->heartbeat(
            attempt: $started->attempt,
            account: $user,
            attentionToken: $started->attentionToken,
            heartbeat: new AttentionHeartbeatData(
                sequence: 1,
                activeDurationMs: 1000,
                visible: true,
                clientOccurredAt: now(),
                idempotencyKey: 'heartbeat-p009-002-1',
            ),
        );

        self::assertSame(ValueAttemptStatus::ProofPending, $proofPending->status);

        $settled = $engine->settle(
            attempt: $proofPending,
            account: $user,
            idempotencyKey: 'settlement-p009-002',
        );
        $replayed = $engine->settle(
            attempt: $settled,
            account: $user,
            idempotencyKey: 'settlement-p009-002',
        );

        $userWallet = Wallet::query()->findOrFail($started->attempt->user_wallet_id);
        $userProjection = $userWallet->projection()->firstOrFail();
        $advertiserProjection = $advertiserWallet->projection()->firstOrFail();
        $counter = ValueCampaignBudgetCounter::query()->firstOrFail();

        self::assertSame(ValueAttemptStatus::Completed, $settled->status);
        self::assertSame($settled->id, $replayed->id);
        self::assertSame($started->quote->userAmountMinor, $userProjection->available_minor);
        self::assertSame(
            $advertiserReservedBefore - $started->quote->grossAmountMinor,
            $advertiserProjection->reserved_minor,
        );
        self::assertSame(0, $counter->reserved_amount_minor);
        self::assertSame($started->quote->grossAmountMinor, $counter->settled_amount_minor);
        self::assertSame($ledgerBefore + 1, LedgerTransaction::query()->count());
        self::assertTrue(WalletOperation::query()
            ->where('wallet_id', $userWallet->id)
            ->where('type', 'advertising_reward')
            ->where('amount_minor', $started->quote->userAmountMinor)
            ->exists());
        self::assertTrue(ValueOutboxEvent::query()
            ->where('event_code', 'ADVERTISING_VALUE_SETTLED')
            ->exists());
    }

    public function test_abandoned_attempt_releases_the_logical_budget_without_credit(): void
    {
        [$user, $campaign, $match] = $this->readyDelivery();
        $engine = app(AdvertisingValueEngine::class);
        $started = $engine->start(
            account: $user,
            campaign: $campaign,
            match: $match,
            deviceSessionId: 'device-session-p009',
            idempotencyKey: 'delivery-p009-003',
            countryCode: 'CI',
        );
        $ledgerBefore = LedgerTransaction::query()->count();

        $released = $engine->release(
            attempt: $started->attempt,
            account: $user,
            reason: 'USER_SWIPED_BEFORE_THRESHOLD',
        );

        $userWallet = Wallet::query()->findOrFail($started->attempt->user_wallet_id);
        $counter = ValueCampaignBudgetCounter::query()->firstOrFail();

        self::assertSame(ValueAttemptStatus::Released, $released->status);
        self::assertSame(0, $counter->reserved_amount_minor);
        self::assertSame($started->quote->grossAmountMinor, $counter->released_amount_minor);
        self::assertSame(0, $userWallet->projection()->firstOrFail()->available_minor);
        self::assertSame($ledgerBefore, LedgerTransaction::query()->count());
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
            'Campagne conforme pour la verticale P009.',
        );
    }

    private function readyGoldUser(): Account
    {
        $user = app(IdentityProvisioner::class)->createAccount(
            displayName: 'Utilisateur P009',
            email: 'p009-'.Str::lower(Str::random(8)).'@example.com',
            password: 'Wasplex-P009-Secure7',
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
            'slug' => 'p009-'.substr($account->id, -6),
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
            UploadedFile::fake()->create('p009.png', 200, 'image/png'),
            null,
            ['label' => 'Création P009', 'usage_context' => 'campaign'],
        );
        $brand = app(BrandService::class)->create($space, $account, [
            'public_name' => $name,
            'slogan' => 'Internet mobile pour tous',
            'description' => 'Marque de démonstration P009.',
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
            'name' => 'Orange Internet P009',
            'brand_id' => $brand->id,
            'objective' => 'conversion',
            'headline' => 'Votre offre Internet mobile Orange',
            'body' => 'Une offre destinée aux utilisateurs intéressés.',
            'call_to_action' => 'Découvrir',
            'destination_url' => 'https://example.test/p009',
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
            type: 'TEST_P009_ADVERTISER_CREDIT',
            sourceModule: 'value-engine-tests',
            businessReference: 'p009-wallet-credit:'.$wallet->id,
            idempotencyKey: 'p009-wallet-credit:'.$wallet->id.':'.$amount,
            unit: 'WP',
            currency: 'XOF',
            entries: [
                new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test P009'),
                new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet P009'),
            ],
        ));
        app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
    }
}
