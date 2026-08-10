<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Campaigns\Infrastructure\Models\CampaignQuote;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionCycle;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionQuotaCounter;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('smartprofile:seed-catalog');
    Artisan::call('matching:seed-configuration');
    config(['campaigns.minimum_segment_size' => 2]);
});

function accountForFeedTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForFeedTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

/**
 * Registers the candidate, subscribes them to the given class, and grants
 * the only consent that currently gates a Matching decision — the minimal
 * setup shared by every Feed test (docs/chantiers/P008-CHANTIER.md §8).
 */
function readyFeedCandidate(string $email, string $classCode, string $country = 'CI'): Account
{
    registerAndLogin($email, country: $country);
    $account = accountForFeedTests($email);
    subscribeAccountToClassForMatchingTests($account->id, $classCode);
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    return $account;
}

function startFeedSession(): string
{
    return test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
}

it('delivers the full advertising vertical: gain known before play, real reservation, and exactly one Wallet credit', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'feed-vertical-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $candidate = readyFeedCandidate('feed-vertical-candidate@example.com', 'GOLD');
    $advertiserAvailableAfterFunding = app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId);
    expect($advertiserAvailableAfterFunding)->toBe(200000);
    $sessionId = startFeedSession();

    $next = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    expect($next)->not->toBeNull();
    expect($next['campaign_id'])->toBe($campaignId);
    expect($next['gain_minor'])->toBeGreaterThan(0);
    expect($next['status'])->toBe('reserved');
    $deliveryId = $next['id'];
    $gainMinor = $next['gain_minor'];

    $counterBefore = SubscriptionQuotaCounter::query()
        ->whereHas('cycle', fn ($q) => $q->where('user_subscription_id', UserSubscription::query()->where('account_id', $candidate->id)->value('id')))
        ->first();
    expect($counterBefore->quota_consumed)->toBe(0);

    test()->postJson("/api/feed/deliveries/{$deliveryId}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', 'started');

    $counterAfterStart = SubscriptionQuotaCounter::query()->find($counterBefore->id);
    expect($counterAfterStart->quota_consumed)->toBe(1);

    // The server clamps reported progress to real wall-clock time elapsed
    // since start() (docs/chantiers/P009-CHANTIER.md §2.4) — travel time
    // forward so the heartbeat can honestly reach 100%.
    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($next['required_duration_ms'] + 500));

    test()->postJson("/api/feed/deliveries/{$deliveryId}/heartbeat", ['visible_duration_ms' => $next['required_duration_ms']])
        ->assertOk()
        ->assertJsonPath('delivery.progress_percent', 100);

    Carbon::setTestNow();

    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($candidate->id);

    $completed = test()->postJson("/api/feed/deliveries/{$deliveryId}/complete")->assertOk();
    $completed->assertJsonPath('gain_minor', $gainMinor);
    $completed->assertJsonPath('delivery.status', 'completed');

    $walletAfter = app(UserWalletQueryService::class)->balanceMinor($candidate->id);
    expect($walletAfter)->toBe($walletBefore + $gainMinor);
    expect($completed->json('balance_minor'))->toBe($walletAfter);

    // Replaying complete() must never credit twice.
    test()->postJson("/api/feed/deliveries/{$deliveryId}/complete")
        ->assertOk()
        ->assertJsonPath('gain_minor', $gainMinor);
    expect(app(UserWalletQueryService::class)->balanceMinor($candidate->id))->toBe($walletAfter);

    $consumption = CampaignEnvelopeConsumption::query()
        ->whereHas('quote.campaignVersion', fn ($q) => $q->where('campaign_id', $campaignId))
        ->firstOrFail();
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_CAPTURED);
});

it('lets the user like, save and share an ad, and post a comment', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-social-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    readyFeedCandidate('feed-social-candidate@example.com', 'GOLD');

    test()->postJson("/api/feed/campaigns/{$campaignId}/like")
        ->assertOk()
        ->assertJsonPath('active', true)
        ->assertJsonPath('count', 1);

    test()->postJson("/api/feed/campaigns/{$campaignId}/like")
        ->assertOk()
        ->assertJsonPath('active', false)
        ->assertJsonPath('count', 0);

    test()->postJson("/api/feed/campaigns/{$campaignId}/save")->assertOk()->assertJsonPath('active', true);
    test()->postJson("/api/feed/campaigns/{$campaignId}/share")->assertOk()->assertJsonPath('count', 1);
    test()->postJson("/api/feed/campaigns/{$campaignId}/share")->assertOk()->assertJsonPath('count', 2);

    test()->postJson("/api/feed/campaigns/{$campaignId}/comments", ['body' => 'Bonne offre !'])->assertCreated();
    $comments = test()->getJson("/api/feed/campaigns/{$campaignId}/comments")->assertOk()->json('comments');
    expect(collect($comments)->pluck('body'))->toContain('Bonne offre !');
});

it('abandoning a delivery releases the envelope slot without any gain', function (): void {
    approvedCampaignForMatchingTests(
        'feed-abandon-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    $candidate = readyFeedCandidate('feed-abandon-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();

    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($candidate->id);

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/abandon")
        ->assertOk()
        ->assertJsonPath('delivery.status', 'abandoned');

    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery['campaign_envelope_consumption_id']);
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
    expect(app(UserWalletQueryService::class)->balanceMinor($candidate->id))->toBe($walletBefore);
});

it('rejects completion before the required attention duration is reached', function (): void {
    approvedCampaignForMatchingTests(
        'feed-early-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    readyFeedCandidate('feed-early-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();

    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")->assertOk();
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/heartbeat", ['visible_duration_ms' => 1])->assertOk();

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/complete")->assertStatus(422);
});

it('excludes a campaign from delivery once its envelope for the class is exhausted', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-exhausted-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );

    $quote = CampaignQuote::query()->whereHas('campaignVersion', fn ($q) => $q->where('campaign_id', $campaignId))->firstOrFail();
    $breakdown = $quote->class_breakdown;
    $breakdown['GOLD']['events'] = 1;
    $quote->update(['class_breakdown' => $breakdown]);

    readyFeedCandidate('feed-exhausted-candidate-1@example.com', 'GOLD');
    $session1 = startFeedSession();
    $first = test()->getJson("/api/feed/next?feed_session_id={$session1}")->assertOk()->json('delivery');
    expect($first)->not->toBeNull();
    test()->postJson('/api/logout')->assertNoContent();

    readyFeedCandidate('feed-exhausted-candidate-2@example.com', 'GOLD');
    $session2 = startFeedSession();
    $second = test()->getJson("/api/feed/next?feed_session_id={$session2}")->assertOk()->json('delivery');
    expect($second)->toBeNull();
});

it('offers no ad once the monthly quota is exhausted', function (): void {
    approvedCampaignForMatchingTests(
        'feed-quota-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    $candidate = readyFeedCandidate('feed-quota-candidate@example.com', 'GOLD');

    // currentCounter() lazily creates the cycle/counter on first read
    // (docs/03 §8) — materialize it before overwriting it as exhausted.
    app(SubscriptionQuotaContract::class)->currentCounter($candidate->id);

    $subscriptionId = UserSubscription::query()->where('account_id', $candidate->id)->value('id');
    $cycleId = SubscriptionCycle::query()->where('user_subscription_id', $subscriptionId)->value('id');
    SubscriptionQuotaCounter::query()->where('subscription_cycle_id', $cycleId)
        ->update(['quota_allocated' => 1, 'quota_consumed' => 1]);

    $sessionId = startFeedSession();
    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    expect($delivery)->toBeNull();
});

it('refuses the Feed admin dashboard without the dedicated capability', function (): void {
    $adminEmail = 'feed-nocap-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), []);
    verifyRecentMfaForFeedTests();

    test()->getJson('/api/admin/feed/dashboard')->assertStatus(403);
});

it('lets an authorized admin read aggregated Feed delivery counts', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-dashboard-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
    );
    readyFeedCandidate('feed-dashboard-candidate@example.com', 'GOLD');
    $sessionId = startFeedSession();
    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    $adminEmail = 'feed-dashboard-admin@wasplex.test';
    registerAndLogin($adminEmail);
    grantFounderAccessForTests(accountForFeedTests($adminEmail), ['admin.feed.dashboard.view']);
    verifyRecentMfaForFeedTests();

    $dashboard = test()->getJson('/api/admin/feed/dashboard')->assertOk();
    expect($dashboard->json('delivery_counts.started'))->toBeGreaterThanOrEqual(1);
});
