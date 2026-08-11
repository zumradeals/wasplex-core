<?php

declare(strict_types=1);

use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Campaigns\Infrastructure\Models\CampaignEnvelopeConsumption;
use App\Modules\Feed\Infrastructure\Models\FeedAdDelivery;
use App\Modules\Feed\Infrastructure\Models\FeedCampaignReward;
use App\Modules\Identity\Infrastructure\Models\Account;
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

function accountForFeedRewardTests(string $email): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $email))
        ->firstOrFail();
}

function readyFeedRewardCandidate(string $email, string $classCode = 'GOLD'): Account
{
    registerAndLogin($email, country: 'CI');
    $account = accountForFeedRewardTests($email);
    subscribeAccountToClassForMatchingTests($account->id, $classCode);
    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    return $account;
}

function startFeedRewardSession(): string
{
    return test()->postJson('/api/feed/sessions')->assertCreated()->json('feed_session.id');
}

function watchFeedRewardDelivery(array $delivery): void
{
    test()->postJson("/api/feed/deliveries/{$delivery['id']}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', 'started');

    Carbon::setTestNow(Carbon::now('UTC')->addMilliseconds($delivery['required_duration_ms'] + 500));

    test()->postJson("/api/feed/deliveries/{$delivery['id']}/heartbeat", [
        'visible_duration_ms' => $delivery['required_duration_ms'],
    ])->assertOk()->assertJsonPath('delivery.progress_percent', 100);

    Carbon::setTestNow();
}

function quotaConsumedForFeedRewardTests(string $accountId): int
{
    $subscriptionId = UserSubscription::query()->where('account_id', $accountId)->value('id');

    return (int) SubscriptionQuotaCounter::query()
        ->whereHas('cycle', fn ($query) => $query->where('user_subscription_id', $subscriptionId))
        ->value('quota_consumed');
}

it('rewards a member only once per campaign and keeps the active campaign available as a free replay', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-single-reward-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        200000,
    );

    $member = readyFeedRewardCandidate('feed-single-reward-member@example.com');
    $sessionId = startFeedRewardSession();

    $first = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->json('delivery');

    expect($first)->not->toBeNull();
    expect($first['campaign_id'])->toBe($campaignId);
    expect($first['status'])->toBe(FeedAdDelivery::STATUS_RESERVED);
    expect($first['is_replay'])->toBeFalse();

    watchFeedRewardDelivery($first);

    $walletBeforeReward = app(UserWalletQueryService::class)->balanceMinor($member->id);
    $completed = test()->postJson("/api/feed/deliveries/{$first['id']}/complete")->assertOk();
    $gain = (int) $completed->json('gain_minor');
    $walletAfterReward = app(UserWalletQueryService::class)->balanceMinor($member->id);

    expect($gain)->toBeGreaterThan(0);
    expect($walletAfterReward)->toBe($walletBeforeReward + $gain);
    expect(quotaConsumedForFeedRewardTests($member->id))->toBe(1);
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->count())->toBe(1);

    $replay = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")
        ->assertOk()
        ->json('delivery');

    expect($replay)->not->toBeNull();
    expect($replay['campaign_id'])->toBe($campaignId);
    expect($replay['status'])->toBe(FeedAdDelivery::STATUS_REPLAY);
    expect($replay['is_replay'])->toBeTrue();
    expect($replay['gain_minor'])->toBe(0);

    test()->postJson("/api/feed/deliveries/{$replay['id']}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_REPLAY);

    test()->postJson("/api/feed/deliveries/{$replay['id']}/complete")
        ->assertOk()
        ->assertJsonPath('gain_minor', 0);

    expect(app(UserWalletQueryService::class)->balanceMinor($member->id))->toBe($walletAfterReward);
    expect(quotaConsumedForFeedRewardTests($member->id))->toBe(1);
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->count())->toBe(1);
});

it('uses the database reward lock as a final barrier and restores quota for a late duplicate delivery', function (): void {
    ['campaign_id' => $campaignId] = approvedCampaignForMatchingTests(
        'feed-race-advertiser@example.com',
        ['economic_classes' => ['GOLD'], 'territory' => ['country_code' => 'CI']],
        300000,
    );

    $member = readyFeedRewardCandidate('feed-race-member@example.com');
    $sessionId = startFeedRewardSession();

    $first = test()->getJson("/api/feed/next?feed_session_id={$sessionId}")->assertOk()->json('delivery');
    watchFeedRewardDelivery($first);
    test()->postJson("/api/feed/deliveries/{$first['id']}/complete")->assertOk();

    $walletAfterFirst = app(UserWalletQueryService::class)->balanceMinor($member->id);
    expect(quotaConsumedForFeedRewardTests($member->id))->toBe(1);

    // Simule une livraison déjà réservée par une requête concurrente avant
    // que la première transaction de récompense ne soit visible.
    $slot = app(CampaignEnvelopeContract::class)->reserveSlot(
        $campaignId,
        'GOLD',
        'feed-race-test:'.uniqid('', true),
    );

    $late = FeedAdDelivery::query()->create([
        'feed_session_id' => $sessionId,
        'account_id' => $member->id,
        'campaign_id' => $campaignId,
        'organization_id' => $slot->organizationId,
        'campaign_envelope_consumption_id' => $slot->consumptionId,
        'economic_class' => 'GOLD',
        'is_replay' => false,
        'gain_minor' => $slot->gainMinor,
        'required_duration_ms' => $slot->requiredDurationMs,
        'status' => FeedAdDelivery::STATUS_RESERVED,
        'reserved_at' => Carbon::now('UTC'),
    ])->toArray();

    watchFeedRewardDelivery($late);
    expect(quotaConsumedForFeedRewardTests($member->id))->toBe(2);

    test()->postJson("/api/feed/deliveries/{$late['id']}/complete")
        ->assertOk()
        ->assertJsonPath('gain_minor', 0)
        ->assertJsonPath('delivery.is_replay', true)
        ->assertJsonPath('delivery.status', FeedAdDelivery::STATUS_COMPLETED);

    expect(app(UserWalletQueryService::class)->balanceMinor($member->id))->toBe($walletAfterFirst);
    expect(quotaConsumedForFeedRewardTests($member->id))->toBe(1);
    expect(FeedCampaignReward::query()->where('account_id', $member->id)->where('campaign_id', $campaignId)->count())->toBe(1);
    expect(CampaignEnvelopeConsumption::query()->findOrFail($slot->consumptionId)->status)
        ->toBe(CampaignEnvelopeConsumption::STATUS_RELEASED);
});
