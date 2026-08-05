<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Shared\Money\Money;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();

    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'admin-api-seed-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(175, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable(), Money::of(175, 'WP')),
        ],
    ));
});

function verifyRecentMfaForTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function accountByIdentifier(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

test('an unauthenticated request to the ledger admin API is refused', function (): void {
    test()->getJson('/api/admin/ledger/transactions')->assertStatus(401);
});

test('a request without recent MFA is refused even with the right capability', function (): void {
    registerAndLogin('ledger-admin-1@wasplex.test');
    grantFounderAccessForTests(accountByIdentifier('ledger-admin-1@wasplex.test'), ['wallet.ledger.view']);

    test()->getJson('/api/admin/ledger/transactions')
        ->assertStatus(401)
        ->assertJsonPath('code', 'MFA_REQUIRED');
});

test('a request with recent MFA but without the capability is denied', function (): void {
    registerAndLogin('ledger-admin-2@wasplex.test');
    grantFounderAccessForTests(accountByIdentifier('ledger-admin-2@wasplex.test'), []);
    verifyRecentMfaForTests();

    test()->getJson('/api/admin/ledger/transactions')
        ->assertStatus(403)
        ->assertJsonPath('code', 'CAPABILITY_DENIED');
});

test('an authorized admin can list and view ledger transactions', function (): void {
    registerAndLogin('ledger-admin-3@wasplex.test');
    grantFounderAccessForTests(accountByIdentifier('ledger-admin-3@wasplex.test'), ['wallet.ledger.view', 'wallet.audit.view']);
    verifyRecentMfaForTests();

    $list = test()->getJson('/api/admin/ledger/transactions')->assertOk();
    expect($list->json('transactions'))->toHaveCount(1);

    $transactionId = $list->json('transactions.0.id');

    test()->getJson("/api/admin/ledger/transactions/{$transactionId}")
        ->assertOk()
        ->assertJsonCount(2, 'transaction.entries');
});

test('the full propose, approve correction flow works through the API with separate admins', function (): void {
    $posting = app(LedgerPostingContract::class);
    $original = $posting->post(new PostLedgerTransaction(
        type: LedgerTransaction::TYPE_INTERNAL_TEST,
        sourceModule: 'tests',
        idempotencyKey: 'admin-api-correction-1',
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of(50, 'WP')),
            LedgerEntryInput::credit(ledgerUserAvailable('user-2'), Money::of(50, 'WP')),
        ],
    ));

    registerAndLogin('ledger-proposer@wasplex.test');
    grantFounderAccessForTests(accountByIdentifier('ledger-proposer@wasplex.test'), ['wallet.correction.propose']);
    verifyRecentMfaForTests();

    $propose = test()->postJson('/api/admin/ledger/corrections', [
        'original_transaction_id' => $original->id,
        'reason' => 'montant erroné',
    ])->assertCreated();

    $pendingId = $propose->json('transaction.id');
    expect($propose->json('transaction.status'))->toBe('pending_approval');

    registerAndLogin('ledger-approver@wasplex.test');
    grantFounderAccessForTests(accountByIdentifier('ledger-approver@wasplex.test'), ['wallet.correction.approve']);
    verifyRecentMfaForTests();

    test()->postJson("/api/admin/ledger/corrections/{$pendingId}/approve")
        ->assertOk()
        ->assertJsonPath('transaction.status', 'posted');
});
