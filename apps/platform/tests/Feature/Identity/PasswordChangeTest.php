<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Support\Facades\Hash;

test('a member can change password and revoke all other active sessions', function (): void {
    registerAndLogin('password-change@wasplex.test', 'Password123!');

    $account = Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', 'password-change@wasplex.test'))
        ->firstOrFail();

    AccountSession::create([
        'account_id' => $account->id,
        'laravel_session_id' => 'other-session-id',
        'ip_address' => '10.0.0.2',
        'user_agent' => 'Other browser',
        'last_active_at' => now(),
    ]);

    test()->putJson('/api/me/password', [
        'current_password' => 'Password123!',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ])->assertOk()->assertJsonPath('revoked_sessions', 1);

    expect(Hash::check('NewPassword456!', $account->fresh()->password))->toBeTrue();
    expect(AccountSession::query()->where('laravel_session_id', 'other-session-id')->value('revoked_at'))->not->toBeNull();

    test()->getJson('/api/me')->assertOk();
});

test('password change rejects a wrong current password', function (): void {
    registerAndLogin('password-wrong@wasplex.test', 'Password123!');

    test()->putJson('/api/me/password', [
        'current_password' => 'WrongPassword999!',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ])->assertStatus(422)->assertJsonPath('code', 'CURRENT_PASSWORD_INVALID');
});
