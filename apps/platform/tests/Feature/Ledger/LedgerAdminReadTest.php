<?php

use App\Modules\Identity\Application\Services\TotpService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\AccessAuditEvent;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Data\PostingEntry;
use App\Modules\Ledger\Application\Data\PostingRequest;
use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps ledger administration read-only capability protected and audited', function (): void {
    registerAccount($this, 'ledger-founder@wasplex.com');
    $this->artisan('identity:bootstrap-founder', ['identifier' => 'ledger-founder@wasplex.com'])
        ->assertSuccessful();

    $adminSpace = UserSpace::query()->where('kind', SpaceKind::Administration->value)->firstOrFail();
    $secret = $this->postJson('/api/me/mfa/setup')->assertOk()->json('data.secret');
    $code = ledgerTotpCode($secret, time());
    expect(app(TotpService::class)->verify($secret, $code))->toBeTrue();
    $this->postJson('/api/me/mfa/confirm', ['code' => $code])->assertOk();
    $this->postJson("/api/me/spaces/{$adminSpace->id}/switch")->assertOk();

    $catalog = app(LedgerCatalog::class);
    $catalog->createAccountType('ASSET', 'Actif', EntryDirection::Debit);
    $catalog->createAccountType('LIABILITY', 'Passif', EntryDirection::Credit);
    $catalog->createJournal('GENERAL', 'Journal général');
    $cash = $catalog->openAccount('ASSET', 'wasplex.cash.clearing', 'WP', 'XOF');
    $user = $catalog->openAccount('LIABILITY', 'user.available.wp', 'WP', 'XOF');

    $posted = app(LedgerContract::class)->post(new PostingRequest(
        journalCode: 'GENERAL',
        type: 'TEST_ADMIN_VIEW',
        sourceModule: 'tests',
        businessReference: 'test:admin:001',
        idempotencyKey: 'test-admin-view-001',
        unit: 'WP',
        currency: 'XOF',
        entries: [
            new PostingEntry($cash->id, EntryDirection::Debit, 250, 'Débit'),
            new PostingEntry($user->id, EntryDirection::Credit, 250, 'Crédit'),
        ],
    ));

    $this->getJson('/api/admin/ledger/transactions')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $posted->transactionId);
    $this->getJson("/api/admin/ledger/transactions/{$posted->transactionId}")
        ->assertOk()
        ->assertJsonCount(2, 'data.entries');
    $this->getJson('/api/admin/ledger/accounts')->assertOk()->assertJsonCount(2, 'data.data');

    expect(AccessAuditEvent::query()
        ->where('action', 'ledger.transaction.viewed')
        ->where('context->transaction_id', $posted->transactionId)
        ->exists())->toBeTrue();

    $this->postJson('/api/auth/logout')->assertOk();
    registerAccount($this, 'ledger-outsider@wasplex.com');
    $this->getJson('/api/admin/ledger/transactions')->assertForbidden();
});

function ledgerTotpCode(string $secret, int $timestamp): string
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
