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
        $version = EconomicClassVersion::query()
            ->where('economic_class_id', $class->id)
            ->whereNull('effective_to')
            ->firstOrFail();

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

it('publishes only free and seeds approved paid launch prices as drafts', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();
    expect($free->status)->toBe(SubscriptionPlanVersion::STATUS_PUBLISHED);
    expect($free->price_minor)->toBe(0);

    $expectedPrices = [
        'PREMIUM' => 1500,
        'GOLD' => 4000,
        'PLATINUM' => 7500,
    ];

    foreach ($expectedPrices as $code => $price) {
        $version = SubscriptionPlanVersion::query()
            ->whereHas('plan', fn ($q) => $q->where('code', $code))
            ->firstOrFail();

        expect($version->status)->toBe(SubscriptionPlanVersion::STATUS_DRAFT);
        expect($version->price_minor)->toBe($price);
        expect($version->duration_days)->toBe(30);
    }
});

it('upgrades a legacy zero-price paid draft without publishing it', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    $premium = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($q) => $q->where('code', 'PREMIUM'))
        ->firstOrFail();

    $premium->update(['price_minor' => 0]);

    Artisan::call('subscriptions:seed-catalog');

    $premium->refresh();

    expect($premium->price_minor)->toBe(1500);
    expect($premium->status)->toBe(SubscriptionPlanVersion::STATUS_DRAFT);
});

it('never overwrites a paid price already chosen by an administrator', function (): void {
    Artisan::call('subscriptions:seed-catalog');

    $gold = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($q) => $q->where('code', 'GOLD'))
        ->firstOrFail();

    $gold->update(['price_minor' => 4500]);

    Artisan::call('subscriptions:seed-catalog');

    expect($gold->fresh()->price_minor)->toBe(4500);
});
