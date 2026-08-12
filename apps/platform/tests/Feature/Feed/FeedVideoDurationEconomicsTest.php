<?php

declare(strict_types=1);

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Campaigns\Infrastructure\Models\Campaign;
use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionQuotaCounter;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config([
        'campaigns.minimum_segment_size' => 2,
        'feed.antifraud_mode' => 'observe',
        'feed.risk_hold_threshold' => PHP_INT_MAX,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function videoDurationQuotaConsumed(string $accountId): int
{
    $subscriptionId = UserSubscription::query()->where('account_id', $accountId)->latest('created_at')->value('id');

    return (int) (SubscriptionQuotaCounter::query()
        ->whereHas('cycle', fn ($query) => $query->where('user_subscription_id', $subscriptionId))
        ->value('quota_consumed') ?? 0);
}

it('requires the true end of a sixty second video and consumes four attention credits', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-duration-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $campaign = Campaign::query()->findOrFail($campaignId);
    $version = $campaign->currentVersion();
    $creative = $version->creative_configuration ?? [];
    $asset = CreativeAsset::query()->findOrFail($creative['asset_id']);
    $asset->update(['type' => 'video', 'duration' => 60, 'duration_ms' => 60000]);
    $version->update(['creative_configuration' => [
        ...$creative, 'format' => 'video', 'duration_ms' => 60000, 'attention_units' => 4,
    ]]);

    $quote = $version->latestQuote();
    $breakdown = $quote->class_breakdown;
    $breakdown['GOLD']['base_reward_minor'] = 50;
    $breakdown['GOLD']['attention_units'] = 4;
    $breakdown['GOLD']['duration_ms'] = 60000;
    $breakdown['GOLD']['gain_unitaire_minor'] = 200;
    $quote->update(['class_breakdown' => $breakdown]);

    registerAndLogin('feed-duration-member@example.com', country: 'CI');
    $account = accountForMatchingTests('feed-duration-member@example.com');
    subscribeAccountToClassForMatchingTests($account->id, 'GOLD');
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');

    expect($delivery['campaign_id'])->toBe($campaignId);
    expect($delivery['required_duration_ms'])->toBe(60000);
    expect($delivery['quota_units'])->toBe(4);
    expect($delivery['gain_minor'])->toBe(200);
    expect($delivery['requires_media_end'])->toBeTrue();

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")->assertOk();
    expect(videoDurationQuotaConsumed($account->id))->toBe(4);

    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds(60500));

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/ended", [
        'visible_duration_ms' => 60000,
    ])->assertStatus(422);

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/heartbeat", [
        'visible_duration_ms' => 60000,
    ])->assertOk()->assertJsonPath('delivery.progress_percent', 100);

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/complete")->assertStatus(422);
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/ended", [
        'visible_duration_ms' => 60000,
    ])->assertOk();
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/complete")
        ->assertOk()
        ->assertJsonPath('gain_minor', 200);

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery['campaign_envelope_consumption_id']);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_CAPTURED);
});
