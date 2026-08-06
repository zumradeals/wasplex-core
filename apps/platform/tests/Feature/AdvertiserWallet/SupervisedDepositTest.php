<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\Identity\Infrastructure\Models\Account;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    test()->artisan('ledger:seed-catalog')->assertSuccessful();
});

function accountForSupervisedDepositTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForSupervisedDepositTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

it('refuses a supervised deposit request without the dedicated capability', function (): void {
    registerAndLogin('supervised-nocap@wasplex.test');
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-nocap@wasplex.test'), []);
    verifyRecentMfaForSupervisedDepositTests();

    test()->postJson('/api/admin/advertiser-wallet/deposits', [
        'organization_id' => 'does-not-matter',
        'amount_minor' => 5000,
        'currency' => 'XOF',
        'proof_reference' => 'REF-1',
        'reason' => 'Virement bancaire reçu',
    ])->assertStatus(403);
});

it('credits the advertiser wallet through the Grand Livre once a second admin approves', function (): void {
    registerAndLogin('supervised-owner@wasplex.test');
    $organizationId = createAdvertiserOrganization();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('supervised-proposer@wasplex.test');
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-proposer@wasplex.test'), ['admin.advertiser-wallet.supervised-deposit']);
    verifyRecentMfaForSupervisedDepositTests();

    $depositId = test()->postJson('/api/admin/advertiser-wallet/deposits', [
        'organization_id' => $organizationId,
        'amount_minor' => 50000,
        'currency' => 'XOF',
        'proof_reference' => 'BANK-REF-42',
        'reason' => 'Virement bancaire reçu',
    ])->assertCreated()->json('deposit.id');

    expect(AdvertiserWalletDeposit::query()->findOrFail($depositId)->status)->toBe(AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT);

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('supervised-approver@wasplex.test');
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-approver@wasplex.test'), ['admin.advertiser-wallet.supervised-deposit']);
    verifyRecentMfaForSupervisedDepositTests();

    test()->postJson("/api/admin/advertiser-wallet/deposits/{$depositId}/approve")
        ->assertOk()
        ->assertJsonPath('deposit.status', AdvertiserWalletDeposit::STATUS_CREDITED);

    expect(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(50000);
});

it('refuses self-approval by the same admin who proposed the deposit', function (): void {
    registerAndLogin('supervised-self@wasplex.test');
    $organizationId = createAdvertiserOrganization();
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-self@wasplex.test'), ['admin.advertiser-wallet.supervised-deposit']);
    verifyRecentMfaForSupervisedDepositTests();

    $depositId = test()->postJson('/api/admin/advertiser-wallet/deposits', [
        'organization_id' => $organizationId,
        'amount_minor' => 5000,
        'currency' => 'XOF',
        'proof_reference' => 'BANK-REF-SELF',
        'reason' => 'Test auto-approbation',
    ])->assertCreated()->json('deposit.id');

    test()->postJson("/api/admin/advertiser-wallet/deposits/{$depositId}/approve")->assertStatus(403);

    expect(AdvertiserWalletDeposit::query()->findOrFail($depositId)->status)->toBe(AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT);
});

it('rejects a supervised deposit with a reason', function (): void {
    registerAndLogin('supervised-reject-owner@wasplex.test');
    $organizationId = createAdvertiserOrganization();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('supervised-reject-admin@wasplex.test');
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-reject-admin@wasplex.test'), ['admin.advertiser-wallet.supervised-deposit']);
    verifyRecentMfaForSupervisedDepositTests();

    $depositId = test()->postJson('/api/admin/advertiser-wallet/deposits', [
        'organization_id' => $organizationId,
        'amount_minor' => 5000,
        'currency' => 'XOF',
        'proof_reference' => 'BANK-REF-REJECT',
        'reason' => 'Preuve incomplète',
    ])->assertCreated()->json('deposit.id');

    test()->postJson("/api/admin/advertiser-wallet/deposits/{$depositId}/reject", ['reason' => 'Référence introuvable'])
        ->assertOk()
        ->assertJsonPath('deposit.status', AdvertiserWalletDeposit::STATUS_REJECTED);
});

it('never decides an already-terminal supervised deposit twice', function (): void {
    registerAndLogin('supervised-terminal-owner@wasplex.test');
    $organizationId = createAdvertiserOrganization();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('supervised-terminal-proposer@wasplex.test');
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-terminal-proposer@wasplex.test'), ['admin.advertiser-wallet.supervised-deposit']);
    verifyRecentMfaForSupervisedDepositTests();

    $depositId = test()->postJson('/api/admin/advertiser-wallet/deposits', [
        'organization_id' => $organizationId,
        'amount_minor' => 5000,
        'currency' => 'XOF',
        'proof_reference' => 'BANK-REF-DUP',
        'reason' => 'Test idempotence',
    ])->assertCreated()->json('deposit.id');

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('supervised-terminal-approver@wasplex.test');
    grantFounderAccessForTests(accountForSupervisedDepositTests('supervised-terminal-approver@wasplex.test'), ['admin.advertiser-wallet.supervised-deposit']);
    verifyRecentMfaForSupervisedDepositTests();

    test()->postJson("/api/admin/advertiser-wallet/deposits/{$depositId}/approve")->assertOk();
    test()->postJson("/api/admin/advertiser-wallet/deposits/{$depositId}/approve")->assertStatus(409);
});
