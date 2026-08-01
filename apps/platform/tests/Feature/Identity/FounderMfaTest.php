<?php

use App\Modules\Identity\Application\Services\TotpService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\AccessAuditEvent;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires a confirmed mfa challenge before the founder console', function (): void {
    registerAccount($this, 'founder@wasplex.com');
    $this->artisan('identity:bootstrap-founder', ['identifier' => 'founder@wasplex.com'])
        ->assertSuccessful();

    $adminSpace = UserSpace::query()->where('kind', SpaceKind::Administration->value)->firstOrFail();

    $this->postJson("/api/me/spaces/{$adminSpace->id}/switch")->assertForbidden();
    $this->post("/espaces/{$adminSpace->id}/activer")
        ->assertRedirectToRoute('security.mfa.setup');

    $secret = $this->postJson('/api/me/mfa/setup')
        ->assertOk()
        ->json('data.secret');

    $code = totpCode($secret, time());
    $this->postJson('/api/me/mfa/confirm', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.confirmed', true);

    $this->postJson("/api/me/spaces/{$adminSpace->id}/switch")->assertOk();
    $this->getJson('/api/admin/accounts')->assertOk()->assertJsonCount(1, 'data.data');
    $this->getJson('/api/admin/organizations')->assertOk()->assertJsonCount(0, 'data.data');

    expect(AccessAuditEvent::query()->where('action', 'mfa.enrollment_confirmed')->where('outcome', 'success')->exists())
        ->toBeTrue();
});

it('redirects the browser to an mfa challenge when enrollment is already confirmed', function (): void {
    registerAccount($this, 'founder-confirmed@wasplex.com');
    $this->artisan('identity:bootstrap-founder', ['identifier' => 'founder-confirmed@wasplex.com'])
        ->assertSuccessful();

    $adminSpace = UserSpace::query()->where('kind', SpaceKind::Administration->value)->firstOrFail();
    $secret = $this->postJson('/api/me/mfa/setup')->assertOk()->json('data.secret');
    $this->postJson('/api/me/mfa/confirm', ['code' => totpCode($secret, time())])->assertOk();

    $this->post('/deconnexion')->assertRedirect();
    $this->post('/connexion', [
        'identifier' => 'founder-confirmed@wasplex.com',
        'password' => 'Wasplex-P001-Secure7',
    ])->assertRedirect();

    $this->post("/espaces/{$adminSpace->id}/activer")
        ->assertRedirectToRoute('security.mfa.challenge');
});

it('verifies the totp algorithm with a stable timestamp', function (): void {
    $service = app(TotpService::class);
    $secret = 'JBSWY3DPEHPK3PXP';
    $timestamp = 1_234_567_890;

    expect($service->verify($secret, totpCode($secret, $timestamp), $timestamp))->toBeTrue()
        ->and($service->verify($secret, '000000', $timestamp))->toBeFalse();
});

function totpCode(string $secret, int $timestamp): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';

    foreach (str_split(strtoupper($secret)) as $character) {
        $position = strpos($alphabet, $character);
        $bits .= str_pad(decbin($position === false ? 0 : $position), 5, '0', STR_PAD_LEFT);
    }

    $decoded = '';

    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $decoded .= chr(bindec($chunk));
        }
    }

    $counter = intdiv($timestamp, 30);
    $hash = hash_hmac('sha1', pack('N2', 0, $counter), $decoded, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = (
        ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF)
    ) % 1_000_000;

    return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
}
