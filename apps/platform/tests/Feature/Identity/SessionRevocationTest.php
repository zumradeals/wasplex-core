<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;

test('revoking the current session rejects the very next request', function (): void {
    registerAndLogin('session@wasplex.test');

    test()->getJson('/api/me')->assertOk();

    /** @var Account $account */
    $account = Account::query()->firstOrFail();
    $session = $account->sessions()->firstOrFail();

    test()->deleteJson("/api/me/sessions/{$session->id}")->assertNoContent();

    test()->getJson('/api/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'SESSION_REVOKED');
});

test('the sessions list only shows sessions belonging to the authenticated account', function (): void {
    registerAndLogin('owner-session@wasplex.test');
    $ownSessions = test()->getJson('/api/me/sessions')->assertOk()->json('sessions');
    expect($ownSessions)->toHaveCount(1);

    registerAndLogin('other-session@wasplex.test');
    $otherSessions = test()->getJson('/api/me/sessions')->assertOk()->json('sessions');
    expect($otherSessions)->toHaveCount(1);
    expect($otherSessions[0]['id'])->not->toBe($ownSessions[0]['id']);
});
