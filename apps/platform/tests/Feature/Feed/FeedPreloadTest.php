<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionCycle;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionQuotaCounter;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

it('preloads the next video without creating a delivery, reserving budget or consuming quota', function (): void {
    approvedCampaignForMatchingTests(
        'feed-preload-advertiser-1@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );
    approvedCampaignForMatchingTests(
        'feed-preload-advertiser-2@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $candidate = readyFeedCandidate('feed-preload-candidate@example.com', 'GOLD');
    app(SubscriptionQuotaContract::class)->currentCounter($candidate->id);

    $sessionId = startFeedSession();
    $current = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->json('delivery');

    expect($current)->not->toBeNull();

    $subscriptionId = UserSubscription::query()->where('account_id', $candidate->id)->value('id');
    $cycleId = SubscriptionCycle::query()->where('user_subscription_id', $subscriptionId)->value('id');
    $counter = SubscriptionQuotaCounter::query()->where('subscription_cycle_id', $cycleId)->firstOrFail();

    $deliveriesBefore = FeedAdDelivery::query()->count();
    $consumptionsBefore = CampaignEnvelopeConsumption::query()->count();
    $quotaConsumedBefore = (int) $counter->quota_consumed;

    $preload = test()->getJson('/api/feed/preload?current_campaign_id='.$current['campaign_id'])
        ->assertOk()
        ->json('preload');

    expect($preload)->not->toBeNull();
    expect($preload['campaign_id'])->not->toBe($current['campaign_id']);
    expect($preload['creative']['type'])->toBe('video');
    expect($preload['creative']['url'])->not->toBeEmpty();

    expect(FeedAdDelivery::query()->count())->toBe($deliveriesBefore);
    expect(CampaignEnvelopeConsumption::query()->count())->toBe($consumptionsBefore);
    expect((int) SubscriptionQuotaCounter::query()->findOrFail($counter->id)->quota_consumed)->toBe($quotaConsumedBefore);
});
