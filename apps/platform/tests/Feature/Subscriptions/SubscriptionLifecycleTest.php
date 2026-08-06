<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Application\Services\SubscriptionService;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
});

function fakeGeniusPaySubscriptionCreate(string $reference, ?string $status = null): void
{
    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => $reference,
            'checkout_url' => "https://geniuspay.ci/checkout/{$reference}",
            'status' => $status,
            'environment' => 'sandbox',
        ], 201),
    ]);
}

function fakeGeniusPaySubscriptionStatus(string $reference, ?string $status): void
{
    Http::fake([
        "geniuspay.ci/api/v1/merchant/payments/{$reference}" => Http::response([
            'reference' => $reference,
            'checkout_url' => "https://geniuspay.ci/checkout/{$reference}",
            'status' => $status,
            'environment' => 'sandbox',
        ], 200),
    ]);
}

function postSubscriptionPaymentWebhook(string $payload, array $headers)
{
    $server = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    return test()->call('POST', '/api/webhooks/geniuspay-subscriptions', server: $server, content: $payload);
}

function publishPaidPlan(string $classCode): SubscriptionPlanVersion
{
    $version = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', $classCode))->firstOrFail();
    $version->update(['status' => SubscriptionPlanVersion::STATUS_PUBLISHED, 'price_minor' => 5000, 'effective_from' => now()]);

    return $version->refresh();
}

it('activates the free plan immediately without touching the payment provider', function (): void {
    registerAndLogin('sub-free@example.com');
    $accountId = Account::query()->firstOrFail()->id;

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    $payment = app(SubscriptionService::class)->subscribe($accountId, $free->id);

    expect($payment->status)->toBe(SubscriptionPayment::STATUS_ACTIVATED);

    $subscription = UserSubscription::query()->where('account_id', $accountId)->firstOrFail();
    expect($subscription->status)->toBe(UserSubscription::STATUS_ACTIVE);
});

it('only activates a paid plan after a signed webhook is server-verified', function (): void {
    registerAndLogin('sub-paid@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    $premium = publishPaidPlan('PREMIUM');

    fakeGeniusPaySubscriptionCreate('SUB_create1');
    $payment = app(SubscriptionService::class)->subscribe($accountId, $premium->id);

    expect($payment->status)->toBe(SubscriptionPayment::STATUS_AWAITING_PAYMENT);
    expect(UserSubscription::query()->where('account_id', $accountId)->firstOrFail()->status)
        ->toBe(UserSubscription::STATUS_PENDING_PAYMENT);

    fakeGeniusPaySubscriptionStatus('SUB_create1', 'success');
    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook([
        'reference' => 'SUB_create1',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'success',
    ]);
    postSubscriptionPaymentWebhook($payload, $headers)->assertOk();

    $subscription = UserSubscription::query()->where('account_id', $accountId)->firstOrFail();
    expect($subscription->status)->toBe(UserSubscription::STATUS_ACTIVE);
    expect($subscription->economicClass->code)->toBe('PREMIUM');

    $counter = app(SubscriptionQuotaContract::class)->currentCounter($accountId);
    expect($counter->quota_allocated)->toBe(300);
});

it('never activates from an invalid webhook signature', function (): void {
    registerAndLogin('sub-badsig@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    $premium = publishPaidPlan('PREMIUM');

    fakeGeniusPaySubscriptionCreate('SUB_badsig');
    app(SubscriptionService::class)->subscribe($accountId, $premium->id);

    fakeGeniusPaySubscriptionStatus('SUB_badsig', 'success');
    postSubscriptionPaymentWebhook(json_encode(['event_type' => 'x', 'data' => ['reference' => 'SUB_badsig']]), [
        'X-Webhook-Signature' => 'not-a-real-signature',
        'X-Webhook-Timestamp' => (string) time(),
    ])->assertUnauthorized();

    expect(UserSubscription::query()->where('account_id', $accountId)->firstOrFail()->status)
        ->toBe(UserSubscription::STATUS_PENDING_PAYMENT);
});

it('does not credit twice when the same webhook is replayed', function (): void {
    registerAndLogin('sub-replay@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    $premium = publishPaidPlan('PREMIUM');

    fakeGeniusPaySubscriptionCreate('SUB_replay');
    app(SubscriptionService::class)->subscribe($accountId, $premium->id);

    fakeGeniusPaySubscriptionStatus('SUB_replay', 'success');
    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook([
        'reference' => 'SUB_replay',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'success',
    ]);

    postSubscriptionPaymentWebhook($payload, $headers)->assertOk();
    postSubscriptionPaymentWebhook($payload, $headers)->assertOk();

    expect(SubscriptionPayment::query()->where('provider_reference', 'SUB_replay')->count())->toBe(1);
    expect(UserSubscription::query()->where('account_id', $accountId)->count())->toBe(1);
});

it('upgrades immediately and preserves already-consumed quota per docs/03 §17', function (): void {
    registerAndLogin('sub-upgrade@example.com');
    $accountId = Account::query()->firstOrFail()->id;

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    app(SubscriptionService::class)->subscribe($accountId, $free->id);

    $quota = app(SubscriptionQuotaContract::class);
    $quota->consume($accountId, 80, 'pre-upgrade-1');

    $gold = publishPaidPlan('GOLD');
    $subscription = UserSubscription::query()->where('account_id', $accountId)->firstOrFail();

    fakeGeniusPaySubscriptionCreate('SUB_upgrade1');
    app(SubscriptionService::class)->upgrade($subscription->id, $gold->id);

    fakeGeniusPaySubscriptionStatus('SUB_upgrade1', 'success');
    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook([
        'reference' => 'SUB_upgrade1',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'success',
    ]);
    postSubscriptionPaymentWebhook($payload, $headers)->assertOk();

    $counter = $quota->currentCounter($accountId);
    // docs/03 §17 example: quota 600, déjà consommé 80 -> reste 520.
    expect($counter->quota_allocated)->toBe(600);
    expect($counter->quota_consumed)->toBe(80);
    expect($counter->remaining())->toBe(520);
});

it('schedules a downgrade at next cycle without any immediate change', function (): void {
    registerAndLogin('sub-downgrade@example.com');
    $accountId = Account::query()->firstOrFail()->id;

    $gold = publishPaidPlan('GOLD');
    fakeGeniusPaySubscriptionCreate('SUB_dg1');
    app(SubscriptionService::class)->subscribe($accountId, $gold->id);
    fakeGeniusPaySubscriptionStatus('SUB_dg1', 'success');
    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook(['reference' => 'SUB_dg1', 'amount' => 5000, 'currency' => 'XOF', 'status' => 'success']);
    postSubscriptionPaymentWebhook($payload, $headers)->assertOk();

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    $subscription = UserSubscription::query()->where('account_id', $accountId)->firstOrFail();

    $updated = app(SubscriptionService::class)->downgrade($subscription->id, $free->id);

    expect($updated->status)->toBe(UserSubscription::STATUS_SCHEDULED_DOWNGRADE);
    expect($updated->economicClass->code)->toBe('GOLD');

    // Not yet due: the current period hasn't ended (docs/03 §18 —
    // "aucun retrait rétroactif").
    expect(app(SubscriptionService::class)->applyScheduledDowngrades())->toBe(0);
    expect($updated->refresh()->status)->toBe(UserSubscription::STATUS_SCHEDULED_DOWNGRADE);

    $updated->update(['current_period_end' => now()->subDay()]);
    $applied = app(SubscriptionService::class)->applyScheduledDowngrades();
    expect($applied)->toBe(1);

    $updated = $updated->refresh();
    expect($updated->status)->toBe(UserSubscription::STATUS_ACTIVE);
    expect($updated->economicClass->code)->toBe('FREE');
});

it('expires an overdue subscription back to the free class while keeping it accessible', function (): void {
    registerAndLogin('sub-expire@example.com');
    $accountId = Account::query()->firstOrFail()->id;

    $gold = publishPaidPlan('GOLD');
    fakeGeniusPaySubscriptionCreate('SUB_exp1');
    app(SubscriptionService::class)->subscribe($accountId, $gold->id);
    fakeGeniusPaySubscriptionStatus('SUB_exp1', 'success');
    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook(['reference' => 'SUB_exp1', 'amount' => 5000, 'currency' => 'XOF', 'status' => 'success']);
    postSubscriptionPaymentWebhook($payload, $headers)->assertOk();

    $subscription = UserSubscription::query()->where('account_id', $accountId)->firstOrFail();
    $subscription->update(['current_period_end' => now()->subDay()]);

    $count = app(SubscriptionService::class)->expireOverdue();
    expect($count)->toBe(1);

    $subscription = $subscription->refresh();
    expect($subscription->status)->toBe(UserSubscription::STATUS_EXPIRED);
    expect($subscription->economicClass->code)->toBe('FREE');
});

it('cancelling does not remove access immediately', function (): void {
    registerAndLogin('sub-cancel@example.com');
    $accountId = Account::query()->firstOrFail()->id;

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    app(SubscriptionService::class)->subscribe($accountId, $free->id);

    $subscription = UserSubscription::query()->where('account_id', $accountId)->firstOrFail();
    $updated = app(SubscriptionService::class)->cancel($subscription->id);

    expect($updated->cancelled_at)->not->toBeNull();
    expect($updated->status)->toBe(UserSubscription::STATUS_ACTIVE);
});
