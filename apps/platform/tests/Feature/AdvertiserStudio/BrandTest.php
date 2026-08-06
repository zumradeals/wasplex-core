<?php

declare(strict_types=1);
use App\Modules\Identity\Infrastructure\Models\Account;
use PragmaRX\Google2FA\Google2FA;

it('creates a brand for the active advertiser organization', function (): void {
    registerAndLogin('studio-brand-1@example.com');
    createAdvertiserOrganization();

    test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals', 'slogan' => 'Le bon plan, chaque jour'])
        ->assertCreated()
        ->assertJsonPath('brand.name', 'GamaDeals')
        ->assertJsonPath('brand.status', 'draft');
});

it('records brand colors and typographies', function (): void {
    registerAndLogin('studio-brand-2@example.com');
    createAdvertiserOrganization();

    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'GamaDeals'])->assertCreated()->json('brand.id');

    test()->putJson("/api/advertiser/brands/{$brandId}/colors", [
        'colors' => [
            ['name' => 'Orange principal', 'hex' => '#FF9A3D', 'usage' => 'principale', 'priority' => 1],
            ['name' => 'Bleu accent', 'hex' => '#4FA3FF', 'usage' => 'accent'],
        ],
    ])->assertOk()->assertJsonCount(2, 'brand.colors');

    test()->putJson("/api/advertiser/brands/{$brandId}/typographies", [
        'typographies' => [['role' => 'principale', 'family' => 'Inter']],
    ])->assertOk()->assertJsonCount(1, 'brand.typographies');

    test()->putJson("/api/advertiser/brands/{$brandId}/guidelines", [
        'tone' => 'jeune',
        'forbidden_mentions' => 'Aucune promesse de gain garanti',
    ])->assertOk()->assertJsonPath('brand.guidelines.tone', 'jeune');
});

it('never leaks a brand between two organizations', function (): void {
    registerAndLogin('studio-brand-owner@example.com');
    createAdvertiserOrganization('Org A');
    $brandId = test()->postJson('/api/advertiser/brands', ['name' => 'Secret Brand'])->assertCreated()->json('brand.id');

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('studio-brand-intruder@example.com');
    createAdvertiserOrganization('Org B');

    test()->getJson("/api/advertiser/brands/{$brandId}")->assertStatus(404);
});

it('rejects new brand creation for a restricted advertiser', function (): void {
    registerAndLogin('studio-brand-restricted@example.com');
    $organizationId = createAdvertiserOrganization();

    $account = Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', 'studio-brand-restricted@example.com'))
        ->firstOrFail();

    grantFounderAccessForTests($account, ['admin.advertisers.manage']);
    verifyRecentMfaForAdvertiserStudioTests();

    $profileId = test()->getJson('/api/advertiser/profile')->assertOk()->json('profile.id');
    test()->postJson("/api/admin/advertisers/{$profileId}/restrict")->assertOk();

    test()->postJson('/api/advertiser/brands', ['name' => 'Blocked Brand'])->assertStatus(403);
});

function verifyRecentMfaForAdvertiserStudioTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}
