<?php

use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Support\Facades\Artisan;

it('seeds the four official economic classes with exact quotas and weights', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    expect(EconomicClass::query()->count())->toBe(4);

    $expected = [
        'FREE' => ['quota' => 120, 'weight' => '10.00'],
        'PREMIUM' => ['quota' => 300, 'weight' => '20.00'],
        'GOLD' => ['quota' => 600, 'weight' => '35.00'],
        'PLATINUM' => ['quota' => 900, 'weight' => '35.00'],
    ];

    foreach ($expected as $code => $values) {
        $class = EconomicClass::query()->where('code', $code)->firstOrFail();
        $version = EconomicClassVersion::query()->where('economic_class_id', $class->id)->whereNull('effective_to')->firstOrFail();

        expect($version->quota_monthly)->toBe($values['quota']);
        expect((string) $version->weight_percent)->toBe($values['weight']);
    }
});

it('sums the four official weights to exactly 100 percent', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    $total = EconomicClassVersion::query()->whereNull('effective_to')->sum('weight_percent');

    expect((float) $total)->toBe(100.0);
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
