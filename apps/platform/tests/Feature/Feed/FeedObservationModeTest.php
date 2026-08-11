<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('credits a complete view immediately in antifraud observation mode even when risk signals are recorded', function (): void {
    config([
        'feed.antifraud_mode' => 'observe',
        'feed.risk_hold_threshold' => PHP_INT_MAX,
    ]);

    approvedCampaignForMatchingTests(
        'feed-observe-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    $candidate = readyFeedCandidate('feed-observe-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();

    $base = Carbon::now('UTC');
    Carbon::setTestNow($base);

    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$next['id']}/start")->assertOk();

    $requiredMs = $next['required_duration_ms'];
    Carbon::setTestNow($base->clone()->addMilliseconds($requiredMs + 500));

    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $requiredMs])
        ->assertOk()
        ->assertJsonPath('delivery.progress_percent', 100)
        ->assertJsonPath('delivery.risk_signal_count', 0);

    // Simulate two signals that would normally reach the historical hold threshold.
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $requiredMs])
        ->assertOk()
        ->assertJsonPath('delivery.risk_signal_count', 1);
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $requiredMs])
        ->assertOk()
        ->assertJsonPath('delivery.risk_signal_count', 2);

    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($candidate->id);

    $completed = test()->postJson("/api/feed/deliveries/{$next['id']}/complete")->assertOk();
    $completed->assertJsonPath('delivery.status', 'completed');
    $completed->assertJsonPath('gain_minor', $next['gain_minor']);

    expect(app(UserWalletQueryService::class)->balanceMinor($candidate->id))
        ->toBe($walletBefore + $next['gain_minor']);

    $delivery = FeedAdDelivery::query()->findOrFail($next['id']);
    expect(FeedAdDeliveryHold::query()->where('feed_ad_delivery_id', $delivery->id)->exists())->toBeFalse();

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery->campaign_envelope_consumption_id);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_CAPTURED);
});
