<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;

test('registering an account creates a default personal space', function (): void {
    registerAndLogin('user@wasplex.test');

    $account = Account::query()->firstOrFail();

    expect($account->status)->toBe('active');

    $response = test()->getJson('/api/me/spaces')->assertOk();

    $spaces = $response->json('spaces');
    expect($spaces)->toHaveCount(1);
    expect($spaces[0]['space_type'])->toBe(UserSpace::TYPE_USER);
    expect($response->json('active_space_id'))->toBe($spaces[0]['user_space_id']);
});

test('logging in with the wrong password is refused', function (): void {
    registerAndLogin('user2@wasplex.test');

    test()->postJson('/api/login', [
        'identifier_value' => 'user2@wasplex.test',
        'password' => 'wrong-password',
    ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

test('an unauthenticated request to a protected endpoint is rejected', function (): void {
    test()->getJson('/api/me')->assertStatus(401)->assertJsonStructure(['code', 'message', 'details', 'trace_id']);
});
