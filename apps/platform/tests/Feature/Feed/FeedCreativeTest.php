<?php

declare(strict_types=1);

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaigns\Infrastructure\Models\AdvertisingPriceVersion;
use App\Modules\Campaigns\Infrastructure\Models\CampaignReviewCase;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

it('exposes the real creative asset on a Feed delivery (docs/chantiers/P010-B)', function (): void {
    foreach (range(1, 2) as $i) {
        subscribeAccountToClassForMatchingTests(Account::query()->create([
            'status' => 'verified',
            'password' => bcrypt('Password123!'),
            'country_code' => 'CI',
            'language' => 'fr',
            'timezone' => 'UTC',
        ])->id, 'GOLD');
    }

    Artisan::call('campaigns:seed-price-catalog');
    $priceVersion = AdvertisingPriceVersion::query()->where('status', AdvertisingPriceVersion::STATUS_DRAFT)->first();
    $priceVersion?->update([
        'base_price_minor_per_event' => 500,
        'status' => AdvertisingPriceVersion::STATUS_PUBLISHED,
        'effective_from' => now(),
        'published_at' => now(),
    ]);

    registerAndLogin('feed-creative-advertiser@example.com');
    $advertiser = accountForFeedTests('feed-creative-advertiser@example.com');
    $organizationId = createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');

    $asset = CreativeAsset::query()->create([
        'brand_id' => $brandId,
        'type' => CreativeAsset::TYPE_VIDEO,
        'filename' => 'demo.mp4',
        'format' => 'video',
        'size' => 1024,
        'duration' => 12,
        'moderation_status' => CreativeAsset::STATUS_APPROVED,
        'storage_disk' => 'public',
        'storage_path' => 'creatives/demo.mp4',
        'created_by' => $advertiser->id,
    ]);

    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'objective_code' => 'faire_connaitre',
        'audience_configuration' => ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        'budget_configuration' => ['budget_amount_minor' => 100000],
        'creative_configuration' => ['asset_id' => $asset->id, 'title' => 'Demo'],
    ])->assertOk();

    creditAdvertiserWalletForTests($organizationId, 200000);
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/fund")->assertOk();
    test()->postJson("/api/advertiser/campaigns/{$campaignId}/submit")->assertOk();

    $caseId = CampaignReviewCase::query()->where('campaign_id', $campaignId)->orderByDesc('opened_at')->orderByDesc('id')->firstOrFail()->id;
    test()->postJson('/api/logout')->assertNoContent();

    $adminEmail = 'feed-creative-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), ['admin.campaign-reviews.decide']);
    verifyRecentMfaForFeedTests();
    test()->postJson("/api/admin/campaign-reviews/{$caseId}/approve")->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    readyFeedCandidate('feed-creative-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();

    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');

    expect($delivery['creative'])->not->toBeNull();
    expect($delivery['creative']['type'])->toBe('video');
    expect($delivery['creative']['duration'])->toBe(12);
    expect($delivery['creative']['url'])->toContain('demo.mp4');
});

it('refuses to quote a V1 campaign without a video', function (): void {
    registerAndLogin('feed-creative-none-advertiser@example.com');
    createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'Sans vidéo'])->assertCreated()->json('brand.id');
    $campaignId = test()->postJson('/api/advertiser/campaigns', ['brand_id' => $brandId])->assertCreated()->json('campaign.id');

    test()->patchJson("/api/advertiser/campaigns/{$campaignId}", [
        'objective_code' => 'faire_connaitre',
        'audience_configuration' => ['territory' => ['country_code' => 'CI']],
        'budget_configuration' => ['daily_budget_minor' => 1000, 'duration_days' => 7],
    ])->assertOk();

    test()->postJson("/api/advertiser/campaigns/{$campaignId}/quote")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Ajoute une vidéo avant de générer le devis.');
});
