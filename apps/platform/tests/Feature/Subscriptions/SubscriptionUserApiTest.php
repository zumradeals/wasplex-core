<?php

declare(strict_types=1);

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
});

it('refuses an unauthenticated request', function (): void {
    test()->getJson('/api/subscriptions/current')->assertStatus(401);
});

it('automatically provisions the free plan and quota once', function (): void {
    registerAndLogin('sub-user-auto-free@example.com');

    $subscription = test()->getJson('/api/subscriptions/current')
        ->assertOk()
        ->assertJsonPath('subscription.status', 'active')
        ->json('subscription');

    $free = SubscriptionPlanVersion::query()
        ->where('status', SubscriptionPlanVersion::STATUS_PUBLISHED)
        ->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))
        ->firstOrFail();

    expect($subscription['plan_version_id'])->toBe($free->id);

    test()->getJson('/api/subscriptions/quota')
        ->assertOk()
        ->assertJsonPath('quota.allocated', 120);

    // Replaying both reads must not create a second active FREE record.
    test()->getJson('/api/subscriptions/current')->assertOk();
    test()->getJson('/api/subscriptions/quota')->assertOk();

    expect(UserSubscription::query()
        ->where('account_id', $subscription['account_id'])
        ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
        ->count())->toBe(1);
});

it('cleans historical duplicate active subscriptions', function (): void {
    registerAndLogin('sub-user-dedupe@example.com');

    $first = test()->getJson('/api/subscriptions/current')->assertOk()->json('subscription');

    UserSubscription::query()->create([
        'account_id' => $first['account_id'],
        'plan_version_id' => $first['plan_version_id'],
        'economic_class_id' => $first['economic_class_id'],
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now()->addSecond(),
    ]);

    test()->getJson('/api/subscriptions/current')->assertOk();

    expect(UserSubscription::query()
        ->where('account_id', $first['account_id'])
        ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_SCHEDULED_DOWNGRADE])
        ->count())->toBe(1);
    expect(UserSubscription::query()
        ->where('account_id', $first['account_id'])
        ->where('status', UserSubscription::STATUS_CANCELLED)
        ->count())->toBe(1);
});

it('lets an authenticated user subscribe to the free plan and read it back', function (): void {
    registerAndLogin('sub-user-1@example.com');

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();

    test()->postJson('/api/subscriptions', ['plan_version_id' => $free->id])
        ->assertCreated()
        ->assertJsonPath('payment.status', 'activated');

    test()->getJson('/api/subscriptions/current')
        ->assertOk()
        ->assertJsonPath('subscription.status', 'active');

    test()->getJson('/api/subscriptions/quota')
        ->assertOk()
        ->assertJsonPath('quota.allocated', 120);

    test()->getJson('/api/subscriptions/history')
        ->assertOk()
        // Selected, PaymentReceived, Activated, AdQuotaReset (cycle
        // initialization is eager at activation, docs/03 §15).
        ->assertJsonCount(4, 'events');
});

it('never lets one account act on another account\'s subscription', function (): void {
    registerAndLogin('sub-user-owner@example.com');
    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    $subscriptionId = test()->postJson('/api/subscriptions', ['plan_version_id' => $free->id])
        ->assertCreated()
        ->json('payment.user_subscription_id');

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('sub-user-intruder@example.com');
    test()->postJson("/api/subscriptions/{$subscriptionId}/cancel")->assertStatus(404);
});

it('lists only published plans for comparison', function (): void {
    registerAndLogin('sub-user-2@example.com');

    $plans = test()->getJson('/api/subscriptions/plans')->assertOk()->json('plans');

    expect(collect($plans)->pluck('code')->all())->toBe(['FREE']);
});
