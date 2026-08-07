<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDepositEvent;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Reconciliation\Application\Services\ReconciliationService;
use App\Modules\Reconciliation\Infrastructure\Models\ReconciliationEntry;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;

/**
 * docs/06-wallet-et-grand-livre-wasplex.md §56 : les 8 scénarios de test de
 * rapprochement. Le rapprochement ne modifie jamais un solde ni une
 * écriture — chaque cas ici vérifie uniquement la classification et la file
 * de revue (docs/chantiers/P011-B-RAPPROCHEMENT.md §3).
 */
function reconciliationAccountFor(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForReconciliationTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function fakeGeniusPayStatusResponse(string $reference, ?string $status, ?int $amountMinor = null, ?string $currency = null): void
{
    Http::fake([
        "geniuspay.ci/api/v1/merchant/payments/{$reference}" => Http::response([
            'reference' => $reference,
            'checkout_url' => "https://geniuspay.ci/checkout/{$reference}",
            'status' => $status,
            'environment' => 'sandbox',
            'amount' => $amountMinor,
            'currency' => $currency,
        ], 200),
    ]);
}

function makeReconciliationDeposit(array $overrides = []): AdvertiserWalletDeposit
{
    return AdvertiserWalletDeposit::query()->create(array_merge([
        'organization_id' => 'org-'.uniqid('', true),
        'initiated_by' => 'account-'.uniqid('', true),
        'amount_minor' => 1000,
        'currency' => 'XOF',
        'status' => AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT,
        'provider_reference' => 'REF_'.uniqid('', true),
        'idempotency_key' => 'idem-'.uniqid('', true),
    ], $overrides));
}

it('classifies a matching payment as matched', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREDITED, 'ledger_transaction_id' => 'txn-1']);
    fakeGeniusPayStatusResponse($deposit->provider_reference, 'success', 1000, 'XOF');

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_MATCHED);
});

it('classifies a different amount as amount_mismatch', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREDITED, 'amount_minor' => 1000]);
    fakeGeniusPayStatusResponse($deposit->provider_reference, 'success', 900, 'XOF');

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_AMOUNT_MISMATCH);
});

it('classifies multiple valid webhook deliveries for the same reference as duplicate', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREDITED]);
    AdvertiserWalletDepositEvent::query()->create(['deposit_id' => $deposit->id, 'provider_code' => 'geniuspay', 'status' => AdvertiserWalletDepositEvent::STATUS_PROCESSED, 'signature_valid' => true]);
    AdvertiserWalletDepositEvent::query()->create(['deposit_id' => $deposit->id, 'provider_code' => 'geniuspay', 'status' => AdvertiserWalletDepositEvent::STATUS_DUPLICATED, 'signature_valid' => true]);

    Http::fake();

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_DUPLICATE);
    Http::assertNothingSent();
});

it('classifies an incompatible status as status_mismatch', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT]);
    fakeGeniusPayStatusResponse($deposit->provider_reference, 'success', $deposit->amount_minor, $deposit->currency);

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_STATUS_MISMATCH);
});

it('classifies a reference unknown to the provider as unmatched', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT]);

    Http::fake([
        "geniuspay.ci/api/v1/merchant/payments/{$deposit->provider_reference}" => Http::response(['message' => 'not found'], 404),
    ]);

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_UNMATCHED);
});

it('flags a deposit with no provider reference for manual review', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREATED, 'provider_reference' => null]);

    Http::fake();

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_MANUAL_REVIEW);
    Http::assertNothingSent();
});

it('flags a provider reversal after an internal credit as manual review', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREDITED]);
    fakeGeniusPayStatusResponse($deposit->provider_reference, 'failed');

    app(ReconciliationService::class)->run();

    $entry = ReconciliationEntry::query()->where('source_record_id', $deposit->id)->firstOrFail();
    expect($entry->result_code)->toBe(ReconciliationEntry::RESULT_MANUAL_REVIEW);
    expect($entry->notes)->toContain('Inversion prestataire');
});

it('exports reconciliation entries as CSV', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREDITED]);
    fakeGeniusPayStatusResponse($deposit->provider_reference, 'success', $deposit->amount_minor, $deposit->currency);
    app(ReconciliationService::class)->run();

    registerAndLogin('reconciliation-export-admin@wasplex.test');
    grantFounderAccessForTests(reconciliationAccountFor('reconciliation-export-admin@wasplex.test'), ['admin.reconciliation.review']);
    verifyRecentMfaForReconciliationTests();

    $response = test()->get('/api/admin/reconciliation/entries/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->getContent())->toContain($deposit->provider_reference);
});

it('refuses the reconciliation queue without the dedicated capability', function (): void {
    registerAndLogin('reconciliation-nocap-admin@wasplex.test');
    grantFounderAccessForTests(reconciliationAccountFor('reconciliation-nocap-admin@wasplex.test'), []);
    verifyRecentMfaForReconciliationTests();

    test()->getJson('/api/admin/reconciliation/runs')->assertStatus(403);
});

it('lets an authorized admin trigger a reconciliation run via the API', function (): void {
    $deposit = makeReconciliationDeposit(['status' => AdvertiserWalletDeposit::STATUS_CREDITED]);
    fakeGeniusPayStatusResponse($deposit->provider_reference, 'success', $deposit->amount_minor, $deposit->currency);

    registerAndLogin('reconciliation-run-admin@wasplex.test');
    grantFounderAccessForTests(reconciliationAccountFor('reconciliation-run-admin@wasplex.test'), ['admin.reconciliation.review']);
    verifyRecentMfaForReconciliationTests();

    $response = test()->postJson('/api/admin/reconciliation/runs')->assertCreated();
    expect($response->json('run.total_checked'))->toBeGreaterThanOrEqual(1);

    test()->getJson('/api/admin/reconciliation/entries?result=matched')
        ->assertOk()
        ->assertJsonPath('entries.0.result_code', 'matched');
});

it('never submits a manual supervised deposit for GeniusPay reconciliation', function (): void {
    // docs/chantiers/P013-CHANTIER.md : trouvé en rejouant la verticale —
    // un dépôt supervisé (docs/13 §28) n'a jamais existé chez GeniusPay,
    // le rapprocher reviendrait à interroger le prestataire pour une
    // référence qu'il n'a jamais émise.
    $deposit = makeReconciliationDeposit([
        'status' => AdvertiserWalletDeposit::STATUS_CREDITED,
        'provider_code' => 'manual_supervised',
        'provider_reference' => 'PROOF-REF-123',
    ]);

    Http::fake();

    app(ReconciliationService::class)->run();

    expect(ReconciliationEntry::query()->where('source_record_id', $deposit->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});
