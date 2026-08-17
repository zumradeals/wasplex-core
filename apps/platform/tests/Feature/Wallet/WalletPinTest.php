<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Modules\Wallet\Infrastructure\Models\UserWallet;
use App\Modules\Wallet\Infrastructure\Models\UserWalletTransfer;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
});

function pinTestRecipient(string $email, string $displayName = 'Destinataire PIN'): Account
{
    $account = Account::query()->create([
        'status' => 'active',
        'password' => Hash::make('Password123!'),
        'country_code' => 'CI',
        'language' => 'fr',
        'timezone' => 'UTC',
    ]);

    AccountIdentifier::query()->create([
        'account_id' => $account->id,
        'type' => 'email',
        'value' => $email,
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    PersonalProfile::query()->create([
        'account_id' => $account->id,
        'display_name' => $displayName,
    ]);

    return $account;
}

function pinTestCreditWallet(string $accountId, int $amountMinor, string $key): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_WALLET_CREDIT',
        sourceModule: 'tests',
        idempotencyKey: $key,
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of($amountMinor, 'WP'), 'Crédit test'),
            LedgerEntryInput::credit(ledgerUserAvailable($accountId), Money::of($amountMinor, 'WP'), 'Crédit test'),
        ],
    ));
}

function pinTestAccountId(string $email): string
{
    return Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', $email))->firstOrFail()->id;
}

it('creates a 4-digit Wallet PIN and reports it as existing', function (): void {
    registerAndLogin('pin-create@example.com');

    test()->getJson('/api/me/wallet/pin')->assertOk()->assertJsonPath('exists', false);

    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    test()->getJson('/api/me/wallet/pin')->assertOk()->assertJsonPath('exists', true);
});

it('rejects a PIN that is not exactly 4 digits and a mismatched confirmation', function (): void {
    registerAndLogin('pin-format@example.com');

    test()->postJson('/api/me/wallet/pin', ['pin' => '12', 'pin_confirmation' => '12'])->assertUnprocessable();
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '4321'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'WALLET_PIN_CONFIRMATION_MISMATCH');
});

it('never stores or exposes the Wallet PIN in clear text', function (): void {
    registerAndLogin('pin-hashed@example.com');
    $accountId = pinTestAccountId('pin-hashed@example.com');

    $response = test()->postJson('/api/me/wallet/pin', ['pin' => '4269', 'pin_confirmation' => '4269'])->assertCreated();
    expect($response->json())->not->toHaveKey('pin');
    expect($response->json())->not->toHaveKey('pin_hash');

    $stored = UserWallet::query()->where('account_id', $accountId)->value('pin_hash');
    expect($stored)->not->toBeNull();
    expect($stored)->not->toBe('4269');
    expect(Hash::check('4269', $stored))->toBeTrue();

    $shown = test()->getJson('/api/me/wallet/pin')->assertOk();
    expect($shown->json())->not->toHaveKey('pin_hash');
});

it('accepts an outgoing transfer once the sender confirms a valid Wallet PIN', function (): void {
    registerAndLogin('pin-transfer-sender@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-transfer-sender@example.com'))->firstOrFail();
    $recipient = pinTestRecipient('pin-transfer-recipient@example.com', 'Awa PIN');
    pinTestCreditWallet($sender->id, 1000, 'pin-test-credit-1');

    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 300,
        'idempotency_key' => 'pin-transfer-ok-1',
        'pin' => '1234',
    ])->assertOk()->assertJsonPath('transfer.status', UserWalletTransfer::STATUS_POSTED);

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(700);
    expect(app(UserWalletQueryService::class)->balanceMinor($recipient->id))->toBe(300);
});

it('refuses a transfer with no PIN set yet and posts no Ledger movement', function (): void {
    registerAndLogin('pin-missing@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-missing@example.com'))->firstOrFail();
    $recipient = pinTestRecipient('pin-missing-recipient@example.com');
    pinTestCreditWallet($sender->id, 1000, 'pin-test-credit-2');

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 100,
        'idempotency_key' => 'pin-transfer-no-pin',
    ])->assertStatus(409)->assertJsonPath('code', 'WALLET_PIN_NOT_SET');

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(1000);
    expect(UserWalletTransfer::query()->count())->toBe(0);
});

it('refuses a transfer with a missing PIN field once a PIN exists', function (): void {
    registerAndLogin('pin-required@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-required@example.com'))->firstOrFail();
    $recipient = pinTestRecipient('pin-required-recipient@example.com');
    pinTestCreditWallet($sender->id, 1000, 'pin-test-credit-3');
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 100,
        'idempotency_key' => 'pin-transfer-required',
    ])->assertStatus(422)->assertJsonPath('code', 'WALLET_PIN_REQUIRED');

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(1000);
    expect(UserWalletTransfer::query()->count())->toBe(0);
});

it('refuses a transfer with a wrong PIN and posts no Ledger movement', function (): void {
    registerAndLogin('pin-wrong@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-wrong@example.com'))->firstOrFail();
    $recipient = pinTestRecipient('pin-wrong-recipient@example.com');
    pinTestCreditWallet($sender->id, 1000, 'pin-test-credit-4');
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 100,
        'idempotency_key' => 'pin-transfer-wrong',
        'pin' => '9999',
    ])->assertStatus(422)->assertJsonPath('code', 'WALLET_PIN_INVALID');

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(1000);
    expect(app(UserWalletQueryService::class)->balanceMinor($recipient->id))->toBe(0);
    expect(UserWalletTransfer::query()->count())->toBe(0);
});

it('locks Wallet PIN verification after repeated wrong attempts and unlocks it after the lockout window', function (): void {
    registerAndLogin('pin-lockout@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-lockout@example.com'))->firstOrFail();
    $recipient = pinTestRecipient('pin-lockout-recipient@example.com');
    pinTestCreditWallet($sender->id, 1000, 'pin-test-credit-5');
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        test()->postJson('/api/me/wallet/transfers', [
            'recipient_account_id' => $recipient->id,
            'amount_minor' => 10,
            'idempotency_key' => "pin-lockout-attempt-{$attempt}",
            'pin' => '0000',
        ])->assertStatus(422)->assertJsonPath('code', 'WALLET_PIN_INVALID');
    }

    // A 6th attempt, even with the correct PIN, must now be locked out —
    // the lock protects the PIN itself, not just wrong guesses.
    $locked = test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 10,
        'idempotency_key' => 'pin-lockout-attempt-6',
        'pin' => '1234',
    ])->assertStatus(423)->assertJsonPath('code', 'WALLET_PIN_LOCKED');
    expect($locked->json('details.retry_after_seconds'))->toBeGreaterThan(0);

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(1000);
    expect(UserWalletTransfer::query()->count())->toBe(0);

    test()->travel(6)->minutes();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 10,
        'idempotency_key' => 'pin-lockout-after-unlock',
        'pin' => '1234',
    ])->assertOk()->assertJsonPath('transfer.status', UserWalletTransfer::STATUS_POSTED);

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(990);
});

it('never lets a member read, create or change another member’s Wallet PIN', function (): void {
    registerAndLogin('pin-owner@example.com');
    $owner = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-owner@example.com'))->firstOrFail();
    test()->postJson('/api/me/wallet/pin', ['pin' => '1111', 'pin_confirmation' => '1111'])->assertCreated();
    $ownerHashBefore = UserWallet::query()->where('account_id', $owner->id)->value('pin_hash');

    registerAndLogin('pin-other-member@example.com');
    $other = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'pin-other-member@example.com'))->firstOrFail();

    test()->postJson('/api/me/wallet/pin', ['pin' => '2222', 'pin_confirmation' => '2222'])->assertCreated();
    test()->putJson('/api/me/wallet/pin', [
        'current_pin' => '2222',
        'pin' => '3333',
        'pin_confirmation' => '3333',
    ])->assertOk();

    // Every PIN endpoint always operates on request()->user() — there is
    // no route parameter to target another account's Wallet.
    expect(UserWallet::query()->where('account_id', $owner->id)->value('pin_hash'))->toBe($ownerHashBefore);
    expect(UserWallet::query()->where('account_id', $other->id)->value('pin_hash'))->not->toBe($ownerHashBefore);
});

it('requires authentication for every Wallet PIN endpoint', function (): void {
    test()->getJson('/api/me/wallet/pin')->assertUnauthorized();
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertUnauthorized();
    test()->putJson('/api/me/wallet/pin', [
        'current_pin' => '1234',
        'pin' => '5678',
        'pin_confirmation' => '5678',
    ])->assertUnauthorized();
});
