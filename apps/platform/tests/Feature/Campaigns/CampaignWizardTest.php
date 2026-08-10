<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
});

it('lists safe profile criteria for the advertiser audience wizard', function (): void {
    registerAndLogin('campaign-targeting-catalog@example.com');
    createAdvertiserOrganization();

    $criteria = test()->getJson('/api/advertiser/targeting/profile-criteria')
        ->assertOk()->json('profile_criteria');

    expect(collect($criteria['interest'] ?? [])->pluck('code'))->toContain('interest.formation');
    expect(collect($criteria['demographic'] ?? [])->pluck('code'))->toContain('demographic.gender.woman');
});

/**
 * Directly inserts an active subscriber account in the given economic
 * class and country — bypasses the Subscriptions HTTP flow (which only
 * allows subscribing to a *published* plan) since this is pure test setup
 * for the audience-estimate contract, not a Subscriptions feature test.
 */
function createActiveSubscriberInClass(string $classCode, string $countryCode): void
{
    $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($q) => $q->where('code', $classCode))
        ->firstOrFail();

    $account = Account::query()->create([
        'status' => 'verified',
        'password' => bcrypt('Password123!'),
        'country_code' => $countryCode,
        'language' => 'fr',
        'timezone' => 'UTC',
    ]);

    UserSubscription::query()->create([
        'account_id' => $account->id,
        'plan_version_id' => $planVersion->id,
        'economic_class_id' => $economicClass->id,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
}

function publishPriceCatalog(int $basePriceMinorPerEvent = 500, string $imageMultiplier = '1.0000', string $videoMultiplier = '1.2000'): AdvertisingPriceVersion
{
    Artisan::call('campaigns:seed-price-catalog');

    $version = AdvertisingPriceVersion::query()->where('status', AdvertisingPriceVersion::STATUS_DRAFT)->firstOrFail();
    $version->update([
        'base_price_minor_per_event' => $basePriceMinorPerEvent,
        'image_multiplier' => $imageMultiplier,
        'video_multiplier' => $videoMultiplier,
        'status' => AdvertisingPriceVersion::STATUS_PUBLISHED,
        'effective_from' => now(),
        'published_at' => now(),
    ]);

    return $version->refresh();
}

function createBrandForCampaignTests(string $name = 'GamaDeals'): string
{
    createAdvertiserOrganization();

    return test()->postJson('/api/advertiser/brands', ['name' => $name])->assertCreated()->json('brand.id');
}

it('creates a campaign with a first draft version', function (): void {
    registerAndLogin('campaign-wizard-1@example.com');
    $brandId = createBrandForCampaignTests();

    test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])
        ->assertCreated()
        ->assertJsonPath('campaign.status', 'draft')
        ->assertJsonPath('campaign.type', 'fast');
});

it('refuses to create a campaign for a brand belonging to another organization', function (): void {
    registerAndLogin('campaign-wizard-owner@example.com');
    $brandId = createBrandForCampaignTests();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('campaign-wizard-intruder@example.com');
    createAdvertiserOrganization('Org B');

    test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertStatus(404);
});

it('autosaves objective, audience and budget configuration', function (): void {
    registerAndLogin('campaign-wizard-2@example.com');
    $brandId = createBrandForCampaignTests();
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'objective_code' => 'faire_connaitre',
        'audience_configuration' => ['economic_classes' => ['GOLD'], 'profile_taxonomies' => ['interest.formation']],
        'budget_configuration' => ['budget_amount_minor' => 100000],
    ])->assertOk()->assertJsonPath('campaign.objective_code', 'faire_connaitre');

    $campaign = test()->getJson("/api/advertiser/campaigns/{$campaignId}")->assertOk()->json('campaign');
    $version = collect($campaign['versions'])->sortByDesc('version_number')->first();

    expect($version['audience_configuration'])->toBe(['economic_classes' => ['GOLD'], 'profile_taxonomies' => ['interest.formation']]);
    expect($version['budget_configuration'])->toBe(['budget_amount_minor' => 100000]);
});

it('rejects an unknown objective code', function (): void {
    registerAndLogin('campaign-wizard-3@example.com');
    $brandId = createBrandForCampaignTests();
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", ['objective_code' => 'inexistant'])
        ->assertStatus(422);
});

it('rejects a forbidden audience targeting key', function (): void {
    registerAndLogin('campaign-wizard-4@example.com');
    $brandId = createBrandForCampaignTests();
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'audience_configuration' => ['economic_classes' => ['GOLD'], 'health_status' => 'diabetic'],
    ])->assertStatus(422);
});

it('rejects an unknown economic class code', function (): void {
    registerAndLogin('campaign-wizard-5@example.com');
    $brandId = createBrandForCampaignTests();
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'audience_configuration' => ['economic_classes' => ['NOT_A_CLASS']],
    ])->assertStatus(422);
});

it('never leaks a campaign between two organizations', function (): void {
    registerAndLogin('campaign-wizard-owner-2@example.com');
    $brandId = createBrandForCampaignTests();
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('campaign-wizard-intruder-2@example.com');
    createAdvertiserOrganization('Org B');

    test()->getJson("/api/advertiser/campaigns/{$campaignId}")->assertStatus(404);
});

it('estimates a real audience count from active subscriptions filtered by country', function (): void {
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('GOLD', 'CI');
    createActiveSubscriberInClass('GOLD', 'SN');

    registerAndLogin('campaign-wizard-6@example.com');
    $brandId = createBrandForCampaignTests();
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'audience_configuration' => ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    ])->assertOk();

    $estimate = test()->postJson("/api/advertiser/campaigns/{$campaignId}/estimate-audience")->assertOk()->json('estimate');

    // Banded to the nearest 5 (docs/13 §39): 2 real CI subscribers -> [0, 5].
    expect($estimate['estimated_min'])->toBe(0);
    expect($estimate['estimated_max'])->toBe(5);
});
