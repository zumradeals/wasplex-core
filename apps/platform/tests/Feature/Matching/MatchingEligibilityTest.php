<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Matching\Infrastructure\Models\MatchingDecision;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

function accountForMatchingTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForMatchingTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

/**
 * Attaches an already-registered account (candidate) to a real economic
 * class subscription — same bypass discipline as CampaignWizardTest's
 * createActiveSubscriberInClass(), but for a specific existing account so
 * the candidate can also drive self-service routes as itself.
 */
function subscribeAccountToClassForMatchingTests(string $accountId, string $classCode): void
{
    $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($q) => $q->where('code', $classCode))
        ->firstOrFail();

    UserSubscription::query()->create([
        'account_id' => $accountId,
        'plan_version_id' => $planVersion->id,
        'economic_class_id' => $economicClass->id,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
}

/**
 * Drives a campaign all the way to `approved` (submit as one advertiser,
 * approve as a throwaway admin), reusing the same lifecycle as P006/P007
 * — the only way a campaign can ever be a real Matching candidate
 * (docs/chantiers/P008-CHANTIER.md §4.2).
 */
function approvedCampaignForMatchingTests(string $advertiserEmail, array $audienceConfiguration, int $budgetAmountMinor = 100000): array
{
    // A wide, already-eligible segment so quoting never fails on its own
    // "segment too small" rule, independently from the Matching decision
    // under test.
    foreach (($audienceConfiguration['economic_classes'] ?? ['GOLD']) as $classCode) {
        subscribeAccountToClassForMatchingTests(Account::query()->create([
            'status' => 'verified',
            'password' => bcrypt('Password123!'),
            'country_code' => $audienceConfiguration['territory']['country_code'] ?? 'CI',
            'language' => 'fr',
            'timezone' => 'UTC',
        ])->id, $classCode);
        subscribeAccountToClassForMatchingTests(Account::query()->create([
            'status' => 'verified',
            'password' => bcrypt('Password123!'),
            'country_code' => $audienceConfiguration['territory']['country_code'] ?? 'CI',
            'language' => 'fr',
            'timezone' => 'UTC',
        ])->id, $classCode);
    }

    Artisan::call('campaigns:seed-price-catalog');
    $priceVersion = AdvertisingPriceVersion::query()
        ->where('status', AdvertisingPriceVersion::STATUS_DRAFT)
        ->first();
    $priceVersion?->update([
        'base_price_minor_per_event' => 500,
        'status' => AdvertisingPriceVersion::STATUS_PUBLISHED,
        'effective_from' => now(),
        'published_at' => now(),
    ]);

    registerAndLogin($advertiserEmail);
    $organizationId = createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'objective_code' => 'faire_connaitre',
        'audience_configuration' => $audienceConfiguration,
        'budget_configuration' => ['budget_amount_minor' => $budgetAmountMinor],
    ])->assertOk();

    creditAdvertiserWalletForTests($organizationId, $budgetAmountMinor * 2);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/submit")->assertOk();

    $caseId = CampaignReviewCase::query()->where('campaign_id', $campaignId)->orderByDesc('opened_at')->orderByDesc('id')->firstOrFail()->id;
    test()->postJson('/api/logout')->assertNoContent();

    $adminEmail = 'matching-admin-'.uniqid().'@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForMatchingTests($adminEmail), ['admin.campaign-reviews.decide']);
    verifyRecentMfaForMatchingTests();
    test()->postJson("/api/admin/campaign-reviews/{$caseId}/approve")->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    return ['campaign_id' => $campaignId, 'organization_id' => $organizationId];
}

it('finds a Gold subscriber in the targeted country and class eligible, with a plain-language explanation', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-positive-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-positive-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-positive-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $eligible = test()->getJson('/api/me/eligible-campaigns')->assertOk()->json('campaigns');

    $match = collect($eligible)->firstWhere('campaign_id', $campaignId);
    expect($match)->not->toBeNull();
    expect($match['brand_name'])->toBe('GamaDeals');
    expect($match['explanation'])->not->toBeEmpty();

    // The contract never exposes the candidate's identity to the caller.
    expect($match)->not->toHaveKey('account_id');
});

it('matches voluntary interests only with explicit Smart Profile consent', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-interest-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI'], 'profile_taxonomies' => ['interest.formation']],
    );

    registerAndLogin('matching-interest-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-interest-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    expect(app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id)->decision)->toBe('withheld');

    $interest = ProfileTaxonomy::query()->where('code', 'interest.formation')->firstOrFail();
    test()->postJson("/api/me/smart-profile/{$interest->id}")->assertCreated();
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_SMART_PROFILE_USAGE.'/grant')->assertOk();

    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);
    expect($decision->decision)->toBe('eligible');
    expect($decision->reasonCodes)->toContain('profile_match');
});

it('does not match a profile criterion explicitly answered no', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-interest-no-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'profile_taxonomies' => ['interest.formation']],
    );

    registerAndLogin('matching-interest-no-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-interest-no-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    $interest = ProfileTaxonomy::query()->where('code', 'interest.formation')->firstOrFail();
    test()->postJson("/api/me/smart-profile/{$interest->id}", ['answer' => false])->assertCreated();
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_SMART_PROFILE_USAGE.'/grant')->assertOk();
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);
    expect($decision->decision)->toBe('ineligible');
    expect($decision->reasonCodes)->toContain('profile_mismatch');
});

it('excludes a candidate outside the targeted country', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-territory-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-territory-candidate@example.com', country: 'SN');
    $candidate = accountForMatchingTests('matching-territory-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);

    expect($decision->decision)->toBe('ineligible');
    expect($decision->reasonCodes)->toContain('territory_mismatch');
});

it('excludes a candidate outside the targeted economic class', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-class-advertiser@example.com',
        ['economic_classes' => ['PLATINUM'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-class-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-class-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);

    expect($decision->decision)->toBe('ineligible');
    expect($decision->reasonCodes)->toContain('class_mismatch');
});

it('withdrawing consent makes an otherwise-eligible candidate immediately ineligible', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-consent-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-consent-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-consent-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');

    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();
    expect(app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id)->decision)->toBe('eligible');

    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/withdraw')->assertOk();
    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);

    expect($decision->decision)->toBe('ineligible');
    expect($decision->reasonCodes)->toContain('consent_denied');
});

it('withholds — rather than refuses — a candidate who never decided the consent', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-withheld-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-withheld-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-withheld-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');

    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);

    expect($decision->decision)->toBe('withheld');
    expect($decision->reasonCodes)->toContain('consent_not_decided');
});

it('never produces an eligible match for a suspended campaign', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-suspended-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-suspended-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-suspended-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    expect(app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id)->decision)->toBe('eligible');

    test()->postJson('/api/logout')->assertNoContent();
    $adminEmail = 'matching-suspend-admin-'.uniqid().'@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForMatchingTests($adminEmail), ['admin.campaigns.suspend']);
    verifyRecentMfaForMatchingTests();
    test()->postJson("/api/admin/campaigns/{$campaignId}/suspend", ['reason' => 'Test'])->assertOk();

    $decision = app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);
    expect($decision->decision)->toBe('ineligible');
    expect(Campaign::query()->findOrFail($campaignId)->status)->toBe(Campaign::STATUS_SUSPENDED);
});

it('does not duplicate the decision row when the same evaluation is replayed', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-idempotent-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-idempotent-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-idempotent-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);
    app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);
    app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);

    expect(MatchingDecision::query()->where('campaign_id', $campaignId)->where('account_id', $candidate->id)->count())
        ->toBe(1);
});

it('refuses Matching administration routes without their dedicated capabilities', function (): void {
    $adminEmail = 'matching-nocap-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForMatchingTests($adminEmail), []);
    verifyRecentMfaForMatchingTests();

    test()->getJson('/api/admin/matching/configuration')->assertStatus(403);
    test()->getJson('/api/admin/matching/audit')->assertStatus(403);
});

it('lets an authorized admin publish a Matching configuration and read the audit counts', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'matching-audit-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    registerAndLogin('matching-audit-candidate@example.com', country: 'CI');
    $candidate = accountForMatchingTests('matching-audit-candidate@example.com');
    subscribeAccountToClassForMatchingTests($candidate->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();
    app(MatchingContract::class)->checkEligibility($campaignId, $candidate->id);
    test()->postJson('/api/logout')->assertNoContent();

    $adminEmail = 'matching-audit-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForMatchingTests($adminEmail), ['admin.matching.configuration.manage', 'admin.matching.audit.view']);
    verifyRecentMfaForMatchingTests();

    $configurationId = test()->postJson('/api/admin/matching/configuration', [
        'frequency_window_hours' => 12,
        'frequency_max_per_window' => 2,
        'fatigue_threshold' => 5,
    ])->assertCreated()->json('configuration.id');

    test()->postJson("/api/admin/matching/configuration/{$configurationId}/publish")
        ->assertOk()
        ->assertJsonPath('configuration.status', 'published');

    $counts = test()->getJson('/api/admin/matching/audit')->assertOk()->json('decision_counts');
    expect($counts['eligible'] ?? 0)->toBeGreaterThanOrEqual(1);
});
