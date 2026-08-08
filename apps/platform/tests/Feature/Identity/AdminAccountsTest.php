<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use PragmaRX\Google2FA\Google2FA;

/**
 * Docs/chantiers/P017-CHANTIER.md (écran Utilisateurs) : première surface
 * admin de lecture sur un compte quelconque, plus la restriction globale
 * réelle (colonne restricted_at déjà en base, jusque-là jamais utilisée).
 */
function accountFor(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForAccountsTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

test('an admin with admin.accounts.view can search and view accounts', function (): void {
    registerAndLogin('target-user@wasplex.test');
    $target = accountFor('target-user@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('accounts-admin@wasplex.test');
    grantFounderAccessForTests(accountFor('accounts-admin@wasplex.test'), ['admin.accounts.view']);
    verifyRecentMfaForAccountsTests();

    $list = test()->getJson('/api/admin/accounts')->assertOk()->json('accounts');
    expect(collect($list)->pluck('id'))->toContain($target->id);

    $bySearch = test()->getJson('/api/admin/accounts?q=target-user@wasplex.test')->assertOk()->json('accounts');
    expect(collect($bySearch)->pluck('id')->all())->toBe([$target->id]);

    $detail = test()->getJson("/api/admin/accounts/{$target->id}")->assertOk();
    $detail->assertJsonPath('account.id', $target->id);
    $detail->assertJsonPath('wallet_balance_minor', 0);
    $detail->assertJsonPath('economic_class_code', null);
    $detail->assertJsonPath('advertiser_organization_name', null);
});

test('an account without admin.accounts.view is denied', function (): void {
    registerAndLogin('nocap-admin@wasplex.test');
    grantFounderAccessForTests(accountFor('nocap-admin@wasplex.test'), []);
    verifyRecentMfaForAccountsTests();

    test()->getJson('/api/admin/accounts')->assertForbidden();
});

test('the detail endpoint surfaces the advertiser organization by native Identity membership', function (): void {
    registerAndLogin('advertiser-owner@wasplex.test');
    $owner = accountFor('advertiser-owner@wasplex.test');
    $organization = Organization::create([
        'name' => 'GAMAD',
        'type' => 'advertiser',
        'country_code' => 'CI',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    OrganizationMembership::create([
        'organization_id' => $organization->id,
        'account_id' => $owner->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('accounts-admin-2@wasplex.test');
    grantFounderAccessForTests(accountFor('accounts-admin-2@wasplex.test'), ['admin.accounts.view']);
    verifyRecentMfaForAccountsTests();

    $detail = test()->getJson("/api/admin/accounts/{$owner->id}")->assertOk();
    $detail->assertJsonPath('advertiser_organization_name', 'GAMAD');
    $detail->assertJsonPath('organizations.0.type', 'advertiser');
});

test('restricting and unrestricting a targeted account is real and reversible', function (): void {
    registerAndLogin('restrict-target@wasplex.test');
    $target = accountFor('restrict-target@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('restrict-admin@wasplex.test');
    grantFounderAccessForTests(accountFor('restrict-admin@wasplex.test'), ['admin.accounts.view', 'admin.accounts.restrict']);
    verifyRecentMfaForAccountsTests();

    expect($target->fresh()->isRestricted())->toBeFalse();

    test()->postJson("/api/admin/accounts/{$target->id}/restrict")
        ->assertOk()
        ->assertJsonPath('account.is_restricted', true);

    expect($target->fresh()->isRestricted())->toBeTrue();

    test()->postJson("/api/admin/accounts/{$target->id}/unrestrict")
        ->assertOk()
        ->assertJsonPath('account.is_restricted', false);

    expect($target->fresh()->isRestricted())->toBeFalse();
});

test('restricting an account requires admin.accounts.restrict even with admin.accounts.view', function (): void {
    registerAndLogin('restrict-target-2@wasplex.test');
    $target = accountFor('restrict-target-2@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('viewonly-admin@wasplex.test');
    grantFounderAccessForTests(accountFor('viewonly-admin@wasplex.test'), ['admin.accounts.view']);
    verifyRecentMfaForAccountsTests();

    test()->postJson("/api/admin/accounts/{$target->id}/restrict")->assertForbidden();
});
