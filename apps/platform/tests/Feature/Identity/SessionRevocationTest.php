<?php

use App\Modules\Identity\Infrastructure\Models\AccountSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses a request after its named session is revoked', function (): void {
    registerAccount($this);
    $session = AccountSession::query()->firstOrFail();
    $session->forceFill(['revoked_at' => now()])->save();

    $this->getJson('/api/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Session révoquée ou inconnue.');
});
