<?php

declare(strict_types=1);

use App\Modules\Card\Infrastructure\Models\CardOperation;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
});

function cardPaymentCredit(string $accountId, int $amountMinor, string $key): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_CARD_WALLET_CREDIT',
        sourceModule: 'tests',
        idempotencyKey: $key,
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of($amountMinor, 'WP'), 'Crédit test Carte'),
            LedgerEntryInput::credit(ledgerUserAvailable($accountId), Money::of($amountMinor, 'WP'), 'Crédit test Carte'),
        ],
    ));
}

it('previews without debiting then pays a fixed receive QR once through the Ledger', function (): void {
    registerAndLogin('card-payee@example.com');
    $beneficiary = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-payee@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $beneficiary->id)->update(['display_name' => 'Awa Bénéficiaire']);
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/receive-qr", [
        'amount_minor' => 350,
        'note' => 'Participation test',
    ])->assertOk()->json('qr.payload');

    $token = CardQrToken::query()->where('purpose', CardQrToken::PURPOSE_RECEIVE_PAYMENT)->firstOrFail();
    expect($token->status)->toBe(CardQrToken::STATUS_ACTIVE);

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-payer@example.com');
    $payer = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-payer@example.com'))->firstOrFail();
    cardPaymentCredit($payer->id, 1000, 'card-payment-credit-1');

    test()->postJson('/api/card-scan/resolve', ['payload' => $payload])
        ->assertOk()
        ->assertJsonPath('payment.recipient.display_name', 'Awa Bénéficiaire')
        ->assertJsonPath('payment.amount_minor', 350)
        ->assertJsonPath('payment.amount_locked', true)
        ->assertJsonPath('payment.available_minor', 1000)
        ->assertJsonMissingPath('payment.recipient.account_id')
        ->assertJsonMissingPath('payment.recipient.email')
        ->assertJsonMissingPath('payment.recipient.phone');

    expect($token->refresh()->status)->toBe(CardQrToken::STATUS_ACTIVE);
    expect(app(UserWalletQueryService::class)->balanceMinor($payer->id))->toBe(1000);
    expect(app(UserWalletQueryService::class)->balanceMinor($beneficiary->id))->toBe(0);

    $payment = [
        'payload' => $payload,
        'amount_minor' => 350,
        'idempotency_key' => 'card-payment-client-1',
    ];

    $response = test()->postJson('/api/card-scan/payment', $payment)
        ->assertOk()
        ->assertJsonPath('receipt.status', CardOperation::STATUS_POSTED)
        ->assertJsonPath('receipt.direction', 'sent')
        ->assertJsonPath('receipt.amount_minor', 350)
        ->assertJsonPath('balance_minor', 650);
    $receiptReference = $response->json('receipt.receipt_reference');

    test()->postJson('/api/card-scan/payment', $payment)
        ->assertOk()
        ->assertJsonPath('receipt.receipt_reference', $receiptReference)
        ->assertJsonPath('balance_minor', 650);

    expect(app(UserWalletQueryService::class)->balanceMinor($payer->id))->toBe(650);
    expect(app(UserWalletQueryService::class)->balanceMinor($beneficiary->id))->toBe(350);
    expect(CardOperation::query()->count())->toBe(1);
    expect($token->refresh()->status)->toBe(CardQrToken::STATUS_USED);
    expect($token->used_at)->not->toBeNull();

    $ledger = LedgerTransaction::query()->where('type', 'CARD_PAYMENT')->firstOrFail();
    expect($ledger->source_module)->toBe('card');

    test()->getJson('/api/me/wallet/history?summary=1')
        ->assertOk()
        ->assertJsonFragment(['key' => 'card', 'count' => 1]);

    test()->postJson('/api/card-scan/payment', [
        'payload' => $payload,
        'amount_minor' => 350,
        'idempotency_key' => 'card-payment-client-2',
    ])->assertGone();
});

it('keeps a receive QR usable when the payer has insufficient Wallet balance', function (): void {
    registerAndLogin('card-flex-payee@example.com');
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/receive-qr")
        ->assertOk()
        ->json('qr.payload');
    $token = CardQrToken::query()->where('purpose', CardQrToken::PURPOSE_RECEIVE_PAYMENT)->firstOrFail();

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-low-balance@example.com');
    $payer = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-low-balance@example.com'))->firstOrFail();
    cardPaymentCredit($payer->id, 50, 'card-payment-credit-2');

    test()->postJson('/api/card-scan/resolve', ['payload' => $payload])
        ->assertOk()
        ->assertJsonPath('payment.amount_locked', false)
        ->assertJsonPath('payment.available_minor', 50);

    test()->postJson('/api/card-scan/payment', [
        'payload' => $payload,
        'amount_minor' => 100,
        'idempotency_key' => 'card-payment-low-balance',
    ])->assertConflict();

    expect($token->refresh()->status)->toBe(CardQrToken::STATUS_ACTIVE);
    expect(CardOperation::query()->count())->toBe(0);
    expect(app(UserWalletQueryService::class)->balanceMinor($payer->id))->toBe(50);
});

it('refuses self payment before any financial operation', function (): void {
    registerAndLogin('card-self-payment@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-self-payment@example.com'))->firstOrFail();
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/receive-qr", ['amount_minor' => 10])
        ->assertOk()
        ->json('qr.payload');
    cardPaymentCredit($account->id, 100, 'card-payment-credit-self');

    test()->postJson('/api/card-scan/resolve', ['payload' => $payload])->assertConflict();

    expect(CardOperation::query()->count())->toBe(0);
    expect(app(UserWalletQueryService::class)->balanceMinor($account->id))->toBe(100);
});

it('lists card receipts for both sent and received operations without exposing private identifiers', function (): void {
    registerAndLogin('card-history-payee@example.com');
    $beneficiary = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-history-payee@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $beneficiary->id)->update(['display_name' => 'Bénéficiaire Historique']);
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/receive-qr", ['amount_minor' => 25])
        ->assertOk()
        ->json('qr.payload');

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-history-payer@example.com');
    $payer = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-history-payer@example.com'))->firstOrFail();
    cardPaymentCredit($payer->id, 100, 'card-payment-credit-history');
    test()->postJson('/api/card-scan/payment', [
        'payload' => $payload,
        'amount_minor' => 25,
        'idempotency_key' => 'card-history-payment',
    ])->assertOk();

    test()->getJson('/api/cards/operations')
        ->assertOk()
        ->assertJsonPath('operations.0.direction', 'sent')
        ->assertJsonPath('operations.0.amount_minor', 25)
        ->assertJsonMissingPath('operations.0.counterparty.email')
        ->assertJsonMissingPath('operations.0.counterparty.phone');

    test()->postJson('/api/logout')->assertSuccessful();
    test()->withCredentials();
    $login = test()->postJson('/api/login', [
        'identifier_value' => 'card-history-payee@example.com',
        'password' => 'Password123!',
    ])->assertOk();
    $sessionCookieName = config('session.cookie');
    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }

    test()->getJson('/api/cards/operations')
        ->assertOk()
        ->assertJsonPath('operations.0.direction', 'received')
        ->assertJsonPath('operations.0.amount_minor', 25);
});
