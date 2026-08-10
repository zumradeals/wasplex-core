<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use PragmaRX\Google2FA\Google2FA;

/**
 * Bug: an account granted admin.* capabilities through the "invite a team
 * member" console screen (POST /admin/capabilities) never got the
 * space_memberships row that makes the "Administration" link appear in
 * the user shell (SpaceSwitcher reads auth.spaces, which only reflects
 * space_memberships) — the account could reach /admin by URL but the
 * console never advertised it. CapabilityGrantsController::store must
 * provision that membership whenever it grants an admin.* capability.
 */
function verifyRecentMfaForGrantTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

test('granting an admin capability provisions the admin-space membership for the target account', function (): void {
    registerAndLogin('grant-target@wasplex.test');
    $target = accountFor('grant-target@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('grantor@wasplex.test');
    grantFounderAccessForTests(accountFor('grantor@wasplex.test'), ['admin.capabilities.grant']);
    verifyRecentMfaForGrantTests();

    $adminMembershipQuery = fn () => SpaceMembership::query()
        ->where('account_id', $target->id)
        ->whereHas('space', fn ($q) => $q->where('space_type', UserSpace::TYPE_ADMIN));

    expect($adminMembershipQuery()->exists())->toBeFalse();

    test()->postJson('/api/admin/capabilities', [
        'account_id' => $target->id,
        'capability_code' => 'admin.dashboard.view',
    ])->assertCreated();

    $membership = $adminMembershipQuery()->first();

    expect($membership)->not->toBeNull();
    expect($membership->status)->toBe('active');
});

test('granting a second admin capability does not duplicate the admin-space membership', function (): void {
    registerAndLogin('grant-target-2@wasplex.test');
    $target = accountFor('grant-target-2@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('grantor-2@wasplex.test');
    grantFounderAccessForTests(accountFor('grantor-2@wasplex.test'), ['admin.capabilities.grant']);
    verifyRecentMfaForGrantTests();

    test()->postJson('/api/admin/capabilities', [
        'account_id' => $target->id,
        'capability_code' => 'admin.dashboard.view',
    ])->assertCreated();

    test()->postJson('/api/admin/capabilities', [
        'account_id' => $target->id,
        'capability_code' => 'admin.audit.view',
    ])->assertCreated();

    expect(
        SpaceMembership::query()
            ->where('account_id', $target->id)
            ->whereHas('space', fn ($q) => $q->where('space_type', UserSpace::TYPE_ADMIN))
            ->count()
    )->toBe(1);
});

test('granting a non-admin capability does not provision an admin-space membership', function (): void {
    registerAndLogin('grant-target-3@wasplex.test');
    $target = accountFor('grant-target-3@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('grantor-3@wasplex.test');
    grantFounderAccessForTests(accountFor('grantor-3@wasplex.test'), ['admin.capabilities.grant']);
    verifyRecentMfaForGrantTests();

    test()->postJson('/api/admin/capabilities', [
        'account_id' => $target->id,
        'capability_code' => 'wallet.ledger.view',
    ])->assertCreated();

    expect(
        SpaceMembership::query()
            ->where('account_id', $target->id)
            ->whereHas('space', fn ($q) => $q->where('space_type', UserSpace::TYPE_ADMIN))
            ->exists()
    )->toBeFalse();
});

test('granting an admin capability reactivates a previously inactive admin-space membership instead of duplicating it', function (): void {
    registerAndLogin('grant-target-4@wasplex.test');
    $target = accountFor('grant-target-4@wasplex.test');

    $adminSpace = UserSpace::create(['space_type' => UserSpace::TYPE_ADMIN, 'status' => 'active']);
    $membership = SpaceMembership::create([
        'user_space_id' => $adminSpace->id,
        'account_id' => $target->id,
        'status' => 'inactive',
        'is_default' => false,
        'joined_at' => now()->subDay(),
    ]);

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('grantor-4@wasplex.test');
    grantFounderAccessForTests(accountFor('grantor-4@wasplex.test'), ['admin.capabilities.grant']);
    verifyRecentMfaForGrantTests();

    test()->postJson('/api/admin/capabilities', [
        'account_id' => $target->id,
        'capability_code' => 'admin.dashboard.view',
    ])->assertCreated();

    expect(
        SpaceMembership::query()
            ->where('account_id', $target->id)
            ->whereHas('space', fn ($q) => $q->where('space_type', UserSpace::TYPE_ADMIN))
            ->count()
    )->toBe(1);
    expect($membership->fresh()->status)->toBe('active');
});
