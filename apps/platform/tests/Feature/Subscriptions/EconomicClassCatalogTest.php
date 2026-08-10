<?php

use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Support\Facades\Artisan;

it('seeds the four subscription levels with direct rewards and quotas', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    expect(EconomicClass::query()->count())->toBe(4);

    $expected = [
        'FREE' => ['quota' => 120, 'reward' => 30],
        'PREMIUM' => ['quota' => 300, 'reward' => 40],
        'GOLD' => ['quota' => 600, 'reward' => 50],
        'PLATINUM' => ['quota' => 900, 'reward' => 60],
    ];

    foreach ($expected as $code => $values) {
        $class = EconomicClass::query()->where('code', $code)->firstOrFail();
        $version = EconomicClassVersion::query()->where('economic_class_id', $class->id)->whereNull('effective_to')->firstOrFail();

        expect($version->quota_monthly)->toBe($values['quota']);
        expect($version->reward_per_complete_view_minor)->toBe($values['reward']);
    }
});

it('is idempotent when run twice', function (): void {
    Artisan::call('subscriptions:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');

    expect(EconomicClass::query()->count())->toBe(4);
    expect(EconomicClassVersion::query()->count())->toBe(4);
});

it('publishes only the free plan and leaves paid plans in draft with no invented price', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    expect($free->status)->toBe(SubscriptionPlanVersion::STATUS_PUBLISHED);
    expect($free->price_minor)->toBe(0);

    foreach (['PREMIUM', 'GOLD', 'PLATINUM'] as $code) {
        $version = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', $code))->firstOrFail();
        expect($version->status)->toBe(SubscriptionPlanVersion::STATUS_DRAFT);
    }
});
