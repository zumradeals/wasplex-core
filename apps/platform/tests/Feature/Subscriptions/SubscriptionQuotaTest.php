<?php

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Application\Services\NoActiveSubscriptionException;
use App\Modules\Subscriptions\Application\Services\QuotaExhaustedException;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Application\Services\SubscriptionService;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
});

function subscribeFreeForTests(string $accountId): void
{
    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    app(SubscriptionService::class)->subscribe($accountId, $free->id);
}

it('throws when no active subscription exists for the account', function (): void {
    app(SubscriptionQuotaContract::class)->consume('no-such-account', 1, 'key-1');
})->throws(NoActiveSubscriptionException::class);

it('allocates the exact quota of the account class on first touch', function (): void {
    registerAndLogin('quota-1@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    subscribeFreeForTests($accountId);

    $counter = app(SubscriptionQuotaContract::class)->currentCounter($accountId);

    expect($counter->quota_allocated)->toBe(120);
    expect($counter->quota_consumed)->toBe(0);
});

it('consumes quota units and never lets a gain event drive the counter', function (): void {
    registerAndLogin('quota-2@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    subscribeFreeForTests($accountId);

    $quota = app(SubscriptionQuotaContract::class);
    $counter = $quota->consume($accountId, 5, 'delivered-1');

    expect($counter->quota_consumed)->toBe(5);
    expect($counter->remaining())->toBe(115);
});

it('does not double-consume when the same idempotency key is replayed', function (): void {
    registerAndLogin('quota-3@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    subscribeFreeForTests($accountId);

    $quota = app(SubscriptionQuotaContract::class);
    $quota->consume($accountId, 5, 'delivered-dup');
    $counter = $quota->consume($accountId, 5, 'delivered-dup');

    expect($counter->quota_consumed)->toBe(5);
});

it('refuses to consume beyond the remaining quota', function (): void {
    registerAndLogin('quota-4@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    subscribeFreeForTests($accountId);

    $quota = app(SubscriptionQuotaContract::class);
    $quota->consume($accountId, 120, 'delivered-full');

    $quota->consume($accountId, 1, 'delivered-over');
})->throws(QuotaExhaustedException::class);

it('restores previously consumed units without going negative', function (): void {
    registerAndLogin('quota-5@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    subscribeFreeForTests($accountId);

    $quota = app(SubscriptionQuotaContract::class);
    $quota->consume($accountId, 10, 'delivered-a');
    $counter = $quota->restore($accountId, 3, 'restored-a');

    expect($counter->quota_consumed)->toBe(7);

    $counter = $quota->restore($accountId, 100, 'restored-b');
    expect($counter->quota_consumed)->toBe(0);
});

it('does not double-restore when the same idempotency key is replayed', function (): void {
    registerAndLogin('quota-6@example.com');
    $accountId = Account::query()->firstOrFail()->id;
    subscribeFreeForTests($accountId);

    $quota = app(SubscriptionQuotaContract::class);
    $quota->consume($accountId, 10, 'delivered-b');
    $quota->restore($accountId, 4, 'restored-dup');
    $counter = $quota->restore($accountId, 4, 'restored-dup');

    expect($counter->quota_consumed)->toBe(6);
});
