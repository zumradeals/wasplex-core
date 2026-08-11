<?php

declare(strict_types=1);

use App\Modules\Feed\Application\Services\FeedCompositionService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('matching:seed-configuration');
});

it('provisions the free baseline before composing the feed', function (): void {
    $email = 'feed-auto-free@example.com';
    registerAndLogin($email, country: 'CI');

    $account = Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $email))
        ->firstOrFail();

    expect(UserSubscription::query()->where('account_id', $account->id)->count())->toBe(0);

    // No approved campaign is required for this regression test: the Feed
    // must establish the member baseline before it starts candidate lookup.
    expect(app(FeedCompositionService::class)->nextCandidate($account->id))->toBeNull();

    $subscription = UserSubscription::query()
        ->where('account_id', $account->id)
        ->where('status', UserSubscription::STATUS_ACTIVE)
        ->with('economicClass')
        ->firstOrFail();

    expect($subscription->economicClass?->code)->toBe(EconomicClass::CODE_FREE);
    expect(app(SubscriptionQuotaContract::class)->currentCounter($account->id)->quota_allocated)->toBe(120);
});
