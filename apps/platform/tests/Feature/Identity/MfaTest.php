<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use PragmaRX\Google2FA\Google2FA;

function grantFounderAccessForTests(Account $account): void
{
    $adminSpace = UserSpace::create(['space_type' => UserSpace::TYPE_ADMIN, 'status' => 'active']);

    SpaceMembership::create([
        'user_space_id' => $adminSpace->id,
        'account_id' => $account->id,
        'status' => 'active',
        'is_default' => false,
        'joined_at' => now(),
    ]);

    foreach (['admin.dashboard.view', 'admin.audit.view'] as $capability) {
        CapabilityGrant::create([
            'account_id' => $account->id,
            'capability_code' => $capability,
            'status' => 'active',
            'starts_at' => now(),
            'granted_by' => $account->id,
        ]);
    }
}

test('the admin space is unreachable without a recent MFA verification', function (): void {
    registerAndLogin('founder@wasplex.test');
    grantFounderAccessForTests(Account::query()->firstOrFail());

    test()->getJson('/api/admin/capabilities')
        ->assertStatus(401)
        ->assertJsonPath('code', 'MFA_REQUIRED');
});

test('a founder can enroll TOTP, verify it and then reach the admin space', function (): void {
    registerAndLogin('founder2@wasplex.test');
    grantFounderAccessForTests(Account::query()->firstOrFail());

    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    test()->putJson('/api/me/mfa', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('mfa_enabled', true);

    test()->postJson('/api/me/mfa/verify', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('mfa_verified', true);

    test()->getJson('/api/admin/capabilities')->assertOk();
});

test('an invalid TOTP code is rejected', function (): void {
    registerAndLogin('founder3@wasplex.test');

    test()->postJson('/api/me/mfa')->assertOk();

    test()->putJson('/api/me/mfa', ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_CODE_INVALID');
});

test('the totp secret is never exposed once enrollment is confirmed', function (): void {
    registerAndLogin('founder4@wasplex.test');

    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();

    test()->getJson('/api/me')->assertOk()->assertJsonMissingPath('totp_secret');
});
