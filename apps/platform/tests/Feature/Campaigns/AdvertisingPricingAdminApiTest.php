<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use PragmaRX\Google2FA\Google2FA;

function accountForPricingTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForPricingTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

it('refuses a request without the pricing management capability', function (): void {
    registerAndLogin('pricing-admin-nocap@wasplex.test');
    grantFounderAccessForTests(accountForPricingTests('pricing-admin-nocap@wasplex.test'), []);
    verifyRecentMfaForPricingTests();

    test()->getJson('/api/admin/advertising/pricing')->assertStatus(403);
});

it('lets an admin create, edit and publish a price catalog version', function (): void {
    registerAndLogin('pricing-admin-1@wasplex.test');
    grantFounderAccessForTests(accountForPricingTests('pricing-admin-1@wasplex.test'), ['admin.advertising.pricing.manage']);
    verifyRecentMfaForPricingTests();

    $versionId = test()->postJson('/api/admin/advertising/pricing', [
        'catalog_code' => 'standard',
        'currency' => 'XOF',
        'minimum_budget_minor' => 5000,
        'duration_days' => 7,
    ])->assertCreated()->assertJsonPath('price_version.status', 'draft')->json('price_version.id');

    test()->patchJson("/api/admin/advertising/pricing/{$versionId}", ['minimum_budget_minor' => 10000, 'duration_days' => 15])
        ->assertOk()
        ->assertJsonPath('price_version.minimum_budget_minor', 10000)
        ->assertJsonPath('price_version.duration_days', 15);

    test()->postJson("/api/admin/advertising/pricing/{$versionId}/publish")
        ->assertOk()
        ->assertJsonPath('price_version.status', 'published');
});

it('refuses to edit a price version that is no longer draft', function (): void {
    registerAndLogin('pricing-admin-2@wasplex.test');
    grantFounderAccessForTests(accountForPricingTests('pricing-admin-2@wasplex.test'), ['admin.advertising.pricing.manage']);
    verifyRecentMfaForPricingTests();

    $versionId = test()->postJson('/api/admin/advertising/pricing', [
        'catalog_code' => 'standard',
        'currency' => 'XOF',
        'minimum_budget_minor' => 5000,
        'duration_days' => 7,
    ])->assertCreated()->json('price_version.id');

    test()->postJson("/api/admin/advertising/pricing/{$versionId}/publish")->assertOk();

    test()->patchJson("/api/admin/advertising/pricing/{$versionId}", ['minimum_budget_minor' => 9999])
        ->assertStatus(409);
});
