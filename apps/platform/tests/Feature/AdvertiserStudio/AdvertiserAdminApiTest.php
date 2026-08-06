<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;

function accountForAdvertiserStudioTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

it('refuses an unauthenticated admin advertisers request', function (): void {
    test()->getJson('/api/admin/advertisers')->assertStatus(401);
});

it('refuses a request without the advertisers management capability', function (): void {
    registerAndLogin('studio-admin-nocap@wasplex.test');
    grantFounderAccessForTests(accountForAdvertiserStudioTests('studio-admin-nocap@wasplex.test'), []);
    verifyRecentMfaForAdvertiserStudioTests();

    test()->getJson('/api/admin/advertisers')->assertStatus(403);
});

it('lets an admin verify then restrict and restore an advertiser profile', function (): void {
    registerAndLogin('studio-admin-1@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    $account = accountForAdvertiserStudioTests('studio-admin-1@wasplex.test');
    grantFounderAccessForTests($account, ['admin.advertisers.manage']);
    verifyRecentMfaForAdvertiserStudioTests();

    $profileId = test()->getJson('/api/advertiser/profile')->assertOk()->json('profile.id');

    test()->postJson("/api/admin/advertisers/{$profileId}/verify")
        ->assertOk()
        ->assertJsonPath('advertiser.status', 'verified');

    test()->postJson("/api/admin/advertisers/{$profileId}/restrict")
        ->assertOk()
        ->assertJsonPath('advertiser.status', 'restricted');

    test()->postJson("/api/admin/advertisers/{$profileId}/restore")
        ->assertOk()
        ->assertJsonPath('advertiser.status', 'verified');
});

it('lets an admin verify or reject a brand', function (): void {
    registerAndLogin('studio-admin-2@wasplex.test');
    createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');

    grantFounderAccessForTests(accountForAdvertiserStudioTests('studio-admin-2@wasplex.test'), ['admin.brands.moderate']);
    verifyRecentMfaForAdvertiserStudioTests();

    test()->postJson("/api/admin/brands/{$brandId}/verify")
        ->assertOk()
        ->assertJsonPath('brand.status', 'verified');
});

it('rejects a brand with a reason', function (): void {
    registerAndLogin('studio-admin-3@wasplex.test');
    createAdvertiserOrganization();
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');

    grantFounderAccessForTests(accountForAdvertiserStudioTests('studio-admin-3@wasplex.test'), ['admin.brands.moderate']);
    verifyRecentMfaForAdvertiserStudioTests();

    test()->postJson("/api/admin/brands/{$brandId}/reject", ['reason' => 'Nom déjà utilisé par une autre marque.'])
        ->assertOk()
        ->assertJsonPath('brand.status', 'rejected');
});
