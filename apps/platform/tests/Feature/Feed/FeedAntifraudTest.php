<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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

/**
 * Freezes time, starts a delivery at a deterministic instant, and returns
 * its id plus required_duration_ms so callers can drive heartbeats with
 * exact control over elapsed time (docs/chantiers/P010-CHANTIER.md §5).
 */
function startFrozenFeedDelivery(string $advertiserEmail, string $candidateEmail): array
{
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        $advertiserEmail,
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    $candidate = readyFeedCandidate($candidateEmail, 'GOLD');
    $sessionId = startFeedSession();

    $base = Carbon::now('UTC');
    Carbon::setTestNow($base);

    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$next['id']}/start")->assertOk();

    return [
        'delivery_id' => $next['id'],
        'required_duration_ms' => $next['required_duration_ms'],
        'candidate' => $candidate,
        'base' => $base,
    ];
}

it('flags an abnormal heartbeat rate as a risk signal', function (): void {
    $ctx = startFrozenFeedDelivery('feed-risk-rate-advertiser@example.com', 'feed-risk-rate-candidate@example.com');

    Carbon::setTestNow($ctx['base']->clone()->addMilliseconds(300));
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => 300])
        ->assertOk()
        ->assertJsonPath('delivery.risk_signal_count', 0);

    Carbon::setTestNow($ctx['base']->clone()->addMilliseconds(350));
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => 350])
        ->assertOk()
        ->assertJsonPath('delivery.risk_signal_count', 1)
        ->assertJsonPath('delivery.last_risk_signal_code', 'heartbeat_rate_abuse');
});

it('flags an overclaimed duration as a risk signal', function (): void {
    $ctx = startFrozenFeedDelivery('feed-risk-overclaim-advertiser@example.com', 'feed-risk-overclaim-candidate@example.com');

    Carbon::setTestNow($ctx['base']->clone()->addMilliseconds(100));
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => 5000])
        ->assertOk()
        ->assertJsonPath('delivery.last_risk_signal_code', 'overclaimed_duration')
        ->assertJsonPath('delivery.risk_signal_count', 1);
});

it('places a delivery on hold instead of crediting once the risk threshold is reached', function (): void {
    $ctx = startFrozenFeedDelivery('feed-risk-hold-advertiser@example.com', 'feed-risk-hold-candidate@example.com');
    $requiredMs = $ctx['required_duration_ms'];

    // First heartbeat: reach 100% honestly, no signal yet (no prior heartbeat to compare against).
    Carbon::setTestNow($ctx['base']->clone()->addMilliseconds($requiredMs + 500));
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => $requiredMs])
        ->assertOk()
        ->assertJsonPath('delivery.progress_percent', 100)
        ->assertJsonPath('delivery.risk_signal_count', 0);

    // Two more heartbeats in immediate succession: two rate-abuse signals, reaching the default threshold (2).
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => $requiredMs])
        ->assertOk()
        ->assertJsonPath('delivery.risk_signal_count', 1);
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => $requiredMs])
        ->assertOk()
        ->assertJsonPath('delivery.risk_signal_count', 2);
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/ended", ['visible_duration_ms' => $requiredMs])->assertOk();

    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($ctx['candidate']->id);

    $completed = test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/complete")->assertOk();
    $completed->assertJsonPath('delivery.status', 'held');
    $completed->assertJsonPath('gain_minor', 0);

    expect(app(UserWalletQueryService::class)->balanceMinor($ctx['candidate']->id))->toBe($walletBefore);

    $delivery = FeedAdDelivery::query()->findOrFail($ctx['delivery_id']);
    $hold = FeedAdDeliveryHold::query()->where('feed_ad_delivery_id', $delivery->id)->firstOrFail();
    expect($hold->status)->toBe(FeedAdDeliveryHold::STATUS_CREATED);
    expect($hold->reason_code)->toBe('heartbeat_rate_abuse');

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery->campaign_envelope_consumption_id);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RESERVED);
});

function heldDeliveryForReviewTests(string $advertiserEmail, string $candidateEmail): array
{
    $ctx = startFrozenFeedDelivery($advertiserEmail, $candidateEmail);
    $requiredMs = $ctx['required_duration_ms'];

    Carbon::setTestNow($ctx['base']->clone()->addMilliseconds($requiredMs + 500));
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => $requiredMs])->assertOk();
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => $requiredMs])->assertOk();
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/heartbeat", ['visible_duration_ms' => $requiredMs])->assertOk();
    test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/ended", ['visible_duration_ms' => $requiredMs])->assertOk();
    $completed = test()->postJson("/api/feed/deliveries/{$ctx['delivery_id']}/complete")->assertOk();
    expect($completed->json('delivery.status'))->toBe('held');

    $delivery = FeedAdDelivery::query()->findOrFail($ctx['delivery_id']);
    $hold = FeedAdDeliveryHold::query()->where('feed_ad_delivery_id', $delivery->id)->firstOrFail();

    test()->postJson('/api/logout')->assertNoContent();

    return ['delivery' => $delivery, 'hold' => $hold, 'candidate' => $ctx['candidate']];
}

it('refuses the Feed risk review queue without the dedicated capability', function (): void {
    heldDeliveryForReviewTests('feed-risk-nocap-advertiser@example.com', 'feed-risk-nocap-candidate@example.com');

    $adminEmail = 'feed-risk-nocap-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), []);
    verifyRecentMfaForFeedTests();

    test()->getJson('/api/admin/feed/holds')->assertStatus(403);
});

it('lets an authorized admin release a hold, crediting the Wallet exactly once', function (): void {
    $ctx = heldDeliveryForReviewTests('feed-risk-release-advertiser@example.com', 'feed-risk-release-candidate@example.com');

    $adminEmail = 'feed-risk-release-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), ['admin.feed.risk.review']);
    verifyRecentMfaForFeedTests();

    $queue = test()->getJson('/api/admin/feed/holds')->assertOk()->json('holds');
    expect(collect($queue)->pluck('id'))->toContain($ctx['hold']->id);

    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($ctx['candidate']->id);

    test()->postJson("/api/admin/feed/holds/{$ctx['hold']->id}/release", ['note' => 'Vérifié manuellement, rythme réseau normal.'])
        ->assertOk()
        ->assertJsonPath('hold.status', 'released');

    $delivery = $ctx['delivery']->refresh();
    expect($delivery->status)->toBe(FeedAdDelivery::STATUS_COMPLETED);
    expect(app(UserWalletQueryService::class)->balanceMinor($ctx['candidate']->id))->toBe($walletBefore + $delivery->gain_minor);

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery->campaign_envelope_consumption_id);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_CAPTURED);
});

it('lets an authorized admin reject a hold, releasing the envelope without any gain', function (): void {
    $ctx = heldDeliveryForReviewTests('feed-risk-reject-advertiser@example.com', 'feed-risk-reject-candidate@example.com');

    $adminEmail = 'feed-risk-reject-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), ['admin.feed.risk.review']);
    verifyRecentMfaForFeedTests();

    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($ctx['candidate']->id);

    test()->postJson("/api/admin/feed/holds/{$ctx['hold']->id}/reject", ['note' => 'Rejeu confirmé.'])
        ->assertOk()
        ->assertJsonPath('hold.status', 'rejected');

    $delivery = $ctx['delivery']->refresh();
    expect($delivery->status)->toBe(FeedAdDelivery::STATUS_REJECTED);
    expect(app(UserWalletQueryService::class)->balanceMinor($ctx['candidate']->id))->toBe($walletBefore);

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery->campaign_envelope_consumption_id);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
});

it('recovers a started delivery stuck without a recent heartbeat via the recovery command', function (): void {
    $ctx = startFrozenFeedDelivery('feed-recovery-stuck-advertiser@example.com', 'feed-recovery-stuck-candidate@example.com');
    $requiredMs = $ctx['required_duration_ms'];

    $timeoutSeconds = (int) ($requiredMs * (int) config('feed.stuck_delivery_timeout_multiplier') / 1000);
    Carbon::setTestNow($ctx['base']->clone()->addSeconds($timeoutSeconds + 5));

    Artisan::call('feed:release-expired-deliveries');

    $delivery = FeedAdDelivery::query()->findOrFail($ctx['delivery_id']);
    expect($delivery->status)->toBe(FeedAdDelivery::STATUS_EXPIRED);

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery->campaign_envelope_consumption_id);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
});

it('does not touch a started delivery that is still plausibly active', function (): void {
    $ctx = startFrozenFeedDelivery('feed-recovery-active-advertiser@example.com', 'feed-recovery-active-candidate@example.com');

    Carbon::setTestNow($ctx['base']->clone()->addSeconds(1));
    Artisan::call('feed:release-expired-deliveries');

    $delivery = FeedAdDelivery::query()->findOrFail($ctx['delivery_id']);
    expect($delivery->status)->toBe(FeedAdDelivery::STATUS_STARTED);
});

it('logs a critical anomaly for a completed delivery found without a Grand Livre transaction', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-recovery-orphan-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    readyFeedCandidate('feed-recovery-orphan-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();

    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$next['id']}/start")->assertOk();

    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($next['required_duration_ms'] + 500));
    test()->postJson("/api/feed/deliveries/{$next['id']}/heartbeat", ['visible_duration_ms' => $next['required_duration_ms']])->assertOk();
    test()->postJson("/api/feed/deliveries/{$next['id']}/ended", ['visible_duration_ms' => $next['required_duration_ms']])->assertOk();
    Carbon::setTestNow();

    test()->postJson("/api/feed/deliveries/{$next['id']}/complete")->assertOk();

    // Simulate the impossible anomaly directly (the atomic transaction in
    // AttentionService::complete() never allows this in practice).
    FeedAdDelivery::query()->whereKey($next['id'])->update(['ledger_transaction_id' => null]);

    Log::spy();

    Artisan::call('feed:release-expired-deliveries');

    Log::shouldHaveReceived('critical')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'feed.delivery.completed_without_ledger_transaction' && $context['delivery_id'] === $next['id']);
});
