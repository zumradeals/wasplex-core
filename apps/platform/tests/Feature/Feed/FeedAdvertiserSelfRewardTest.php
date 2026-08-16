<?php

declare(strict_types=1);

use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Feed\Application\Services\FeedRiskReviewService;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use App\Modules\Feed\Infrastructure\Models\FeedCampaignReward;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionQuotaCounter;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
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

function accountForFeedIntegrityTests(string $email): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $email))
        ->firstOrFail();
}

function loginExistingFeedIntegrityAccount(string $email, string $password = 'Password123!'): Account
{
    test()->withCredentials();

    $login = test()->postJson('/api/login', [
        'identifier_value' => $email,
        'password' => $password,
    ])->assertOk();

    $sessionCookieName = config('session.cookie');

    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }

    return accountForFeedIntegrityTests($email);
}

function readyFeedIntegrityCandidate(string $email, string $classCode = 'GOLD'): Account
{
    registerAndLogin($email, country: 'CI');
    $account = accountForFeedIntegrityTests($email);
    subscribeAccountToClassForMatchingTests($account->id, $classCode);
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    return $account;
}

function grantFeedIntegrityConsent(Account $account, string $classCode = 'GOLD'): void
{
    subscribeAccountToClassForMatchingTests($account->id, $classCode);
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();
}

function feedIntegrityQuotaConsumed(string $accountId): int
{
    $subscriptionId = UserSubscription::query()
        ->where('account_id', $accountId)
        ->orderByDesc('created_at')
        ->value('id');

    if ($subscriptionId === null) {
        return 0;
    }

    return (int) (SubscriptionQuotaCounter::query()
        ->whereHas('cycle', fn ($query) => $query->where('user_subscription_id', $subscriptionId))
        ->value('quota_consumed') ?? 0);
}

function activeFeedIntegrityMembership(string $organizationId, string $accountId, string $title = 'member'): OrganizationMembership
{
    return OrganizationMembership::query()->create([
        'organization_id' => $organizationId,
        'account_id' => $accountId,
        'title' => $title,
        'status' => 'active',
        'joined_at' => Carbon::now('UTC'),
    ]);
}

it('composes the advertiser owner own campaign as an unrewarded preview', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'feed-own-owner@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $owner = loginExistingFeedIntegrityAccount('feed-own-owner@example.com');
    grantFeedIntegrityConsent($owner);

    expect(OrganizationMembership::query()
        ->where('organization_id', $organizationId)
        ->where('account_id', $owner->id)
        ->where('title', 'owner')
        ->where('status', 'active')
        ->exists())->toBeTrue();

    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');

    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->assertJsonPath('delivery.campaign_id', $campaignId)
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_REPLAY)
        ->assertJsonPath('delivery.gain_minor', 0)
        ->assertJsonPath('delivery.quota_units', 0)
        ->json('delivery');

    expect($delivery['is_replay'])->toBeTrue();
    expect(feedIntegrityQuotaConsumed($owner->id))->toBe(0);
    expect(CampaignEnvelopeConsumption::query()
        ->whereHas('quote.campaignVersion', fn ($query) => $query->where('campaign_id', $campaignId))
        ->exists())->toBeFalse();
    expect(FeedCampaignReward::query()->where('account_id', $owner->id)->where('campaign_id', $campaignId)->exists())->toBeFalse();
});

it('composes a campaign as an unrewarded preview for an active member of its advertiser team', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'feed-own-team-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $member = readyFeedIntegrityCandidate('feed-own-team-member@example.com');
    activeFeedIntegrityMembership($organizationId, $member->id, 'manager');

    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');

    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->assertJsonPath('delivery.campaign_id', $campaignId)
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_REPLAY)
        ->assertJsonPath('delivery.gain_minor', 0)
        ->assertJsonPath('delivery.quota_units', 0)
        ->json('delivery');

    expect($delivery['is_replay'])->toBeTrue();
    expect(feedIntegrityQuotaConsumed($member->id))->toBe(0);
    expect(CampaignEnvelopeConsumption::query()
        ->whereHas('quote.campaignVersion', fn ($query) => $query->where('campaign_id', $campaignId))
        ->exists())->toBeFalse();
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->exists())->toBeFalse();
});

it('neutralizes a stale reserved own campaign before start with zero quota and zero budget consumption', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'feed-own-stale-start-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $member = readyFeedIntegrityCandidate('feed-own-stale-start-member@example.com');
    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->json('delivery');

    expect($delivery)->not->toBeNull();
    expect($delivery['campaign_id'])->toBe($campaignId);
    expect($delivery['status'])->toBe(FeedAdDelivery::STATUS_RESERVED);

    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($member->id);
    expect(feedIntegrityQuotaConsumed($member->id))->toBe(0);

    activeFeedIntegrityMembership($organizationId, $member->id);

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_REPLAY)
        ->assertJsonPath('delivery.gain_minor', 0);

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/complete")
        ->assertOk()
        ->assertJsonPath('gain_minor', 0);

    $neutralized = FeedAdDelivery::query()->findOrFail($delivery['id']);
    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery['campaign_envelope_consumption_id']);

    expect($neutralized->is_replay)->toBeTrue();
    expect($neutralized->ledger_transaction_id)->toBeNull();
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
    expect(feedIntegrityQuotaConsumed($member->id))->toBe(0);
    expect(app(UserWalletQueryService::class)->balanceMinor($member->id))->toBe($walletBefore);
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->exists())->toBeFalse();
});

it('blocks payment if a started delivery becomes an own-team campaign before completion and restores quota', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'feed-own-stale-complete-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $member = readyFeedIntegrityCandidate('feed-own-stale-complete-member@example.com');
    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->json('delivery');

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_STARTED);

    expect(feedIntegrityQuotaConsumed($member->id))->toBe(1);

    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($delivery['required_duration_ms'] + 500));
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/heartbeat", [
        'visible_duration_ms' => $delivery['required_duration_ms'],
    ])->assertOk()->assertJsonPath('delivery.progress_percent', 100);
    Carbon::setTestNow();

    activeFeedIntegrityMembership($organizationId, $member->id);
    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($member->id);

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/complete")
        ->assertOk()
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_REPLAY)
        ->assertJsonPath('delivery.gain_minor', 0)
        ->assertJsonPath('gain_minor', 0);

    $neutralized = FeedAdDelivery::query()->findOrFail($delivery['id']);
    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery['campaign_envelope_consumption_id']);

    expect($neutralized->is_replay)->toBeTrue();
    expect($neutralized->ledger_transaction_id)->toBeNull();
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
    expect(feedIntegrityQuotaConsumed($member->id))->toBe(0);
    expect(app(UserWalletQueryService::class)->balanceMinor($member->id))->toBe($walletBefore);
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->exists())->toBeFalse();
});

it('refuses an admin hold release when the viewer now belongs to the advertiser organization', function (): void {
    ['campaign_id' => $campaignId, 'organization_id' => $organizationId] = approvedCampaignForMatchingTests(
        'feed-own-held-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $member = readyFeedIntegrityCandidate('feed-own-held-member@example.com');
    $sessionId = test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
    $delivery = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->json('delivery');

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")->assertOk();
    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($delivery['required_duration_ms'] + 500));
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/heartbeat", [
        'visible_duration_ms' => $delivery['required_duration_ms'],
    ])->assertOk()->assertJsonPath('delivery.progress_percent', 100);
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/ended", [
        'visible_duration_ms' => $delivery['required_duration_ms'],
    ])->assertOk();
    Carbon::setTestNow();

    config(['feed.risk_hold_threshold' => 0]);
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/complete")
        ->assertOk()
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_HELD)
        ->assertJsonPath('gain_minor', 0);

    $hold = FeedAdDeliveryHold::query()
        ->where('feed_ad_delivery_id', $delivery['id'])
        ->firstOrFail();

    activeFeedIntegrityMembership($organizationId, $member->id);
    $walletBefore = app(UserWalletQueryService::class)->balanceMinor($member->id);
    $admin = accountForFeedIntegrityTests('feed-own-held-advertiser@example.com');

    $resolved = app(FeedRiskReviewService::class)
        ->release($hold->id, $admin->id, 'Tentative de libération manuelle.');

    expect($resolved->status)->toBe(FeedAdDeliveryHold::STATUS_REJECTED);

    $neutralized = FeedAdDelivery::query()->findOrFail($delivery['id']);
    $consumption = CampaignEnvelopeConsumption::query()->findOrFail($delivery['campaign_envelope_consumption_id']);

    expect($neutralized->status)->toBe(FeedAdDelivery::STATUS_REJECTED);
    expect($neutralized->gain_minor)->toBe(0);
    expect($neutralized->ledger_transaction_id)->toBeNull();
    expect($consumption->status)->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
    expect(feedIntegrityQuotaConsumed($member->id))->toBe(0);
    expect(app(UserWalletQueryService::class)->balanceMinor($member->id))->toBe($walletBefore);
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->exists())->toBeFalse();
});
