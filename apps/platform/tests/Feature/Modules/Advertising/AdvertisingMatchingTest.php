<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\AdvertiserStudio\Application\Services\BrandService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetService;
use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingExplanationService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Application\Services\AdvertisingSegmentService;
use App\Modules\Advertising\Application\Services\CampaignEligibilityService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingFrequencyCounter;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatchAudit;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSegment;
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
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletProjector;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdvertisingMatchingTest extends TestCase
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

    public function test_gold_cocody_orange_profile_is_eligible_without_identity_or_financial_side_effect(): void
    {
        $user = $this->readyGoldUser();
        [$campaign, $segment] = $this->approvedOrangeCampaign();
        $ledgerBefore = LedgerTransaction::query()->count();
        $service = app(CampaignEligibilityService::class);

        $match = $service->evaluate($user, $campaign);
        $replayed = $service->evaluate($user, $campaign);
        $explanation = app(AdvertisingExplanationService::class)->explain($user, $match);
        $estimate = app(AdvertisingSegmentService::class)->estimate($segment);
        $presentedEstimate = app(AdvertisingSegmentService::class)->presentEstimate($estimate);

        expect($match->decision)->toBe('eligible')
            ->and($match->score_band)->toBe('high')
            ->and($replayed->id)->toBe($match->id)
            ->and($match->explanation_tokens)->toContain('class:GOLD')
            ->and($match->explanation_tokens)->toContain('usage:telecom.network.primary:orange')
            ->and($match->explanation_tokens)->toContain('interest:interest.mobile_internet:yes')
            ->and($match->explanation_tokens)->toContain('territory:geo.ci.abidjan.commune:cocody')
            ->and($explanation['advertiserReceivedIdentity'])->toBeFalse()
            ->and($explanation['financialOperationCreated'])->toBeFalse()
            ->and(json_encode($explanation))->not->toContain($user->id)
            ->and($presentedEstimate)->not->toHaveKey('raw_count')
            ->and($presentedEstimate)->not->toHaveKey('members')
            ->and($presentedEstimate['membersExported'])->toBeFalse()
            ->and(LedgerTransaction::query()->count())->toBe($ledgerBefore)
            ->and(AdvertisingMatchAudit::query()->value('subject_hash'))->not->toBe($user->id);
    }

    public function test_consent_withdrawal_immediately_creates_a_new_ineligible_decision(): void
    {
        $user = $this->readyGoldUser();
        [$campaign] = $this->approvedOrangeCampaign();
        $matching = app(CampaignEligibilityService::class);
        $eligible = $matching->evaluate($user, $campaign);

        app(AdvertisingConsentService::class)->decide(
            $user,
            'smart_profile_usage',
            AdvertisingConsentStatus::Withdrawn,
        );
        $blocked = $matching->evaluate($user, $campaign);

        expect($eligible->decision)->toBe('eligible')
            ->and($blocked->id)->not->toBe($eligible->id)
            ->and($blocked->decision)->toBe('ineligible')
            ->and($blocked->exclusion_codes)->toContain('required_consent_inactive');
    }

    public function test_suspended_campaign_is_never_eligible(): void
    {
        $user = $this->readyGoldUser();
        [$campaign] = $this->approvedOrangeCampaign();
        $reviewer = $this->account();
        $suspended = app(CampaignReviewService::class)->suspend(
            $campaign,
            $reviewer,
            'Suspension de contrôle P008.',
        );

        $match = app(CampaignEligibilityService::class)->evaluate($user, $suspended);

        expect($match->decision)->toBe('ineligible')
            ->and($match->exclusion_codes)->toContain('campaign_suspended');
    }

    public function test_small_segment_is_withheld_and_never_exposes_the_raw_count(): void
    {
        config()->set('advertising.minimum_segment_size', 5);
        $user = $this->readyGoldUser();
        [$campaign, $segment] = $this->approvedOrangeCampaign();
        $segments = app(AdvertisingSegmentService::class);
        $estimate = $segments->estimate($segment);
        $presented = $segments->presentEstimate($estimate);
        $match = app(CampaignEligibilityService::class)->evaluate($user, $campaign);

        expect($estimate->status)->toBe('withheld')
            ->and($presented['status'])->toBe('withheld')
            ->and($presented['approximateCount'])->toBeNull()
            ->and($presented['protectedRange'])->toBeNull()
            ->and($presented)->not->toHaveKey('raw_count')
            ->and($match->decision)->toBe('withheld')
            ->and($match->exclusion_codes)->toContain('privacy_threshold_not_met');
    }

    public function test_frequency_limit_blocks_a_new_matching_state_without_consuming_quota(): void
    {
        $user = $this->readyGoldUser();
        [$campaign] = $this->approvedOrangeCampaign();
        $matching = app(CampaignEligibilityService::class);
        $first = $matching->evaluate($user, $campaign);
        AdvertisingFrequencyCounter::query()->create([
            'account_id' => $user->id,
            'campaign_id' => $campaign->id,
            'window_start' => now()->subHour(),
            'window_end' => now()->addHours(23),
            'impressions' => 3,
            'fatigue_score' => 20,
            'version' => 1,
        ]);

        $limited = $matching->evaluate($user, $campaign);

        expect($first->decision)->toBe('eligible')
            ->and($limited->id)->not->toBe($first->id)
            ->and($limited->decision)->toBe('ineligible')
            ->and($limited->exclusion_codes)->toContain('frequency_limit_reached');
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
            'Campagne Orange conforme pour le Matching P008.',
        );

        return [$approved, $segment->refresh()->load(['rules.taxonomy', 'campaignVersion'])];
    }

    private function readyGoldUser(): Account
    {
        $user = app(IdentityProvisioner::class)->createAccount(
            displayName: 'Utilisateur Gold Cocody',
            email: 'gold-cocody-'.Str::lower(Str::random(8)).'@example.com',
            password: 'Wasplex-P008-Gold7',
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
            'slug' => 'orange-'.substr($account->id, -6),
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
            UploadedFile::fake()->create('orange-cocody.png', 200, 'image/png'),
            null,
            ['label' => 'Offre Internet Orange', 'usage_context' => 'campaign'],
        );
        $brand = app(BrandService::class)->create($space, $account, [
            'public_name' => $name,
            'slogan' => 'Internet mobile pour tous',
            'description' => 'Marque de démonstration P008.',
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
            'name' => 'Orange Internet Cocody',
            'brand_id' => $brand->id,
            'objective' => 'conversion',
            'headline' => 'Votre offre Internet mobile Orange',
            'body' => 'Une offre destinée aux utilisateurs intéressés à Cocody.',
            'call_to_action' => 'Découvrir',
            'destination_url' => 'https://example.test/orange-internet',
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
            type: 'TEST_P008_ADVERTISER_CREDIT',
            sourceModule: 'advertising-matching-tests',
            businessReference: "p008-wallet-credit:{$wallet->id}",
            idempotencyKey: "p008-wallet-credit:{$wallet->id}:{$amount}",
            unit: 'WP',
            currency: 'XOF',
            entries: [
                new PostingEntry($clearing, EntryDirection::Debit, $amount, 'Actif de test P008'),
                new PostingEntry($wallet->available_ledger_account_id, EntryDirection::Credit, $amount, 'Crédit Wallet P008'),
            ],
        ));
        app(WalletProjector::class)->rebuild($wallet, $posted->transactionId);
    }
}
