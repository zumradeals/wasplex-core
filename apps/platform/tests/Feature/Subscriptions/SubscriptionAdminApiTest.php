<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Artisan::call('subscriptions:seed-catalog');
});

function verifyRecentMfaForSubscriptionTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function accountForSubscriptionTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

it('refuses an unauthenticated admin plans request', function (): void {
    test()->getJson('/api/admin/subscriptions/plans')->assertStatus(401);
});

it('refuses a request without the plans management capability', function (): void {
    registerAndLogin('sub-admin-nocap@wasplex.test');
    grantFounderAccessForTests(accountForSubscriptionTests('sub-admin-nocap@wasplex.test'), []);
    verifyRecentMfaForSubscriptionTests();

    test()->getJson('/api/admin/subscriptions/plans')->assertStatus(403);
});

it('lets an admin create, publish and suspend a plan version', function (): void {
    registerAndLogin('sub-admin-1@wasplex.test');
    grantFounderAccessForTests(accountForSubscriptionTests('sub-admin-1@wasplex.test'), ['admin.subscriptions.plans.manage']);
    verifyRecentMfaForSubscriptionTests();

    $goldClass = EconomicClass::query()->where('code', 'GOLD')->firstOrFail();

    $created = test()->postJson('/api/admin/subscriptions/plans', [
        'plan_code' => 'GOLD_ANNUAL',
        'plan_name' => 'Gold annuel',
        'economic_class_id' => $goldClass->id,
        'price_minor' => 60000,
        'currency' => 'XOF',
        'duration_days' => 365,
    ])->assertCreated()->json('plan_version');

    expect($created['status'])->toBe(SubscriptionPlanVersion::STATUS_DRAFT);

    test()->postJson("/api/admin/subscriptions/plans/{$created['id']}/publish")
        ->assertOk()
        ->assertJsonPath('plan_version.status', SubscriptionPlanVersion::STATUS_PUBLISHED);

    test()->postJson("/api/admin/subscriptions/plans/{$created['id']}/suspend")
        ->assertOk()
        ->assertJsonPath('plan_version.status', SubscriptionPlanVersion::STATUS_SUSPENDED);
});

it('refuses to edit a plan version once it is no longer draft', function (): void {
    registerAndLogin('sub-admin-2@wasplex.test');
    grantFounderAccessForTests(accountForSubscriptionTests('sub-admin-2@wasplex.test'), ['admin.subscriptions.plans.manage']);
    verifyRecentMfaForSubscriptionTests();

    $free = SubscriptionPlanVersion::query()->whereHas('plan', fn ($q) => $q->where('code', 'FREE'))->firstOrFail();

    test()->patchJson("/api/admin/subscriptions/plans/{$free->id}", ['price_minor' => 999])
        ->assertStatus(409);
});

it('lets an admin set a direct reward per complete view', function (): void {
    registerAndLogin('sub-admin-4@wasplex.test');
    grantFounderAccessForTests(accountForSubscriptionTests('sub-admin-4@wasplex.test'), ['admin.subscriptions.classes.manage']);
    verifyRecentMfaForSubscriptionTests();

    $free = EconomicClass::query()->where('code', 'FREE')->firstOrFail();

    test()->patchJson("/api/admin/economic-classes/{$free->id}", [
        'quota_monthly' => 120,
        'reward_per_complete_view_minor' => 35,
        'currency' => 'XOF',
    ])->assertOk()->assertJsonPath('economic_class_version.reward_per_complete_view_minor', 35);
});

it('versions an economic class instead of overwriting its history', function (): void {
    registerAndLogin('sub-admin-5@wasplex.test');
    grantFounderAccessForTests(accountForSubscriptionTests('sub-admin-5@wasplex.test'), ['admin.subscriptions.classes.manage']);
    verifyRecentMfaForSubscriptionTests();

    $free = EconomicClass::query()->where('code', 'FREE')->firstOrFail();

    test()->patchJson("/api/admin/economic-classes/{$free->id}", [
        'quota_monthly' => 150,
        'reward_per_complete_view_minor' => 35,
        'currency' => 'XOF',
    ])->assertOk();

    expect($free->versions()->count())->toBe(2);
    expect($free->versions()->whereNull('effective_to')->firstOrFail()->quota_monthly)->toBe(150);
});
