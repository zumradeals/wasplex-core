<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\UserWalletTransfer;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
});

function walletV2Credit(string $accountId, int $amountMinor, string $key): void
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

function walletV2Recipient(string $email, string $displayName = 'Destinataire Test'): Account
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

function walletV2PublishPaidPlan(string $code = 'PREMIUM'): SubscriptionPlanVersion
{
    $version = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($query) => $query->where('code', $code))
        ->firstOrFail();
    $version->update([
        'status' => SubscriptionPlanVersion::STATUS_PUBLISHED,
        'price_minor' => 5000,
        'effective_from' => now(),
    ]);

    return $version->refresh();
}

it('creates a user Wallet deposit with a Wallet return URL and credits it only after server verification', function (): void {
    registerAndLogin('wallet-deposit-v2@example.com');
    $accountId = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'wallet-deposit-v2@example.com'))->firstOrFail()->id;

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'WALLET_DEP_1',
            'checkout_url' => 'https://geniuspay.ci/checkout/WALLET_DEP_1',
            'status' => 'pending',
            'environment' => 'sandbox',
        ], 201),
    ]);

    $response = test()->postJson('/api/me/wallet/deposits', ['amount_minor' => 5000])->assertCreated();
    $depositId = $response->json('deposit.id');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_ends_with($request->url(), '/payments')
            && str_contains((string) ($payload['success_url'] ?? ''), '/app?tab=wallet&payment=wallet-deposit-success')
            && ($payload['metadata']['payment_context'] ?? null) === 'user_wallet_deposit';
    });

    expect(app(UserWalletQueryService::class)->balanceMinor($accountId))->toBe(0);

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments/WALLET_DEP_1' => Http::response([
            'reference' => 'WALLET_DEP_1',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'completed',
            'environment' => 'sandbox',
        ]),
    ]);

    test()->postJson("/api/me/wallet/deposits/{$depositId}/refresh")
        ->assertOk()
        ->assertJsonPath('deposit.status', UserWalletDeposit::STATUS_CREDITED);

    test()->postJson("/api/me/wallet/deposits/{$depositId}/refresh")->assertOk();

    expect(app(UserWalletQueryService::class)->balanceMinor($accountId))->toBe(5000);
    expect(UserWalletDeposit::query()->whereKey($depositId)->value('ledger_transaction_id'))->not->toBeNull();
});

it('transfers WP atomically, resolves a minimal recipient identity and is idempotent', function (): void {
    registerAndLogin('wallet-sender-v2@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'wallet-sender-v2@example.com'))->firstOrFail();
    $recipient = walletV2Recipient('wallet-recipient-v2@example.com', 'Awa Test');
    walletV2Credit($sender->id, 1000, 'wallet-v2-credit-1');
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    test()->postJson('/api/me/wallet/transfers/recipient', ['identifier' => 'wallet-recipient-v2@example.com'])
        ->assertOk()
        ->assertJsonPath('recipient.account_id', $recipient->id)
        ->assertJsonPath('recipient.display_name', 'Awa Test');

    $payload = [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 350,
        'idempotency_key' => 'client-transfer-unique-1',
        'pin' => '1234',
    ];

    test()->postJson('/api/me/wallet/transfers', $payload)
        ->assertOk()
        ->assertJsonPath('transfer.status', UserWalletTransfer::STATUS_POSTED);
    test()->postJson('/api/me/wallet/transfers', $payload)->assertOk();

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(650);
    expect(app(UserWalletQueryService::class)->balanceMinor($recipient->id))->toBe(350);
    expect(UserWalletTransfer::query()->count())->toBe(1);
});

it('refuses self transfers and transfers above the available balance', function (): void {
    registerAndLogin('wallet-limits-v2@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'wallet-limits-v2@example.com'))->firstOrFail();
    $recipient = walletV2Recipient('wallet-limits-recipient@example.com');
    walletV2Credit($sender->id, 100, 'wallet-v2-credit-2');
    test()->postJson('/api/me/wallet/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])->assertCreated();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $sender->id,
        'amount_minor' => 10,
        'idempotency_key' => 'self-transfer',
        'pin' => '1234',
    ])->assertUnprocessable();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 101,
        'idempotency_key' => 'too-much-transfer',
        'pin' => '1234',
    ])->assertConflict();

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(100);
    expect(app(UserWalletQueryService::class)->balanceMinor($recipient->id))->toBe(0);
});

it('returns subscription payments to Mon Espace and activates an upgrade from a server-side refresh', function (): void {
    registerAndLogin('subscription-return-v2@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'subscription-return-v2@example.com'))->firstOrFail();

    test()->getJson('/api/subscriptions/current')->assertOk();
    $premium = walletV2PublishPaidPlan();
    $subscription = UserSubscription::query()->where('account_id', $account->id)->where('status', 'active')->firstOrFail();

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'SUB_RETURN_V2',
            'checkout_url' => 'https://geniuspay.ci/checkout/SUB_RETURN_V2',
            'status' => 'pending',
            'environment' => 'sandbox',
        ], 201),
    ]);

    $response = test()->postJson("/api/subscriptions/{$subscription->id}/upgrade", [
        'plan_version_id' => $premium->id,
    ])->assertOk();
    $paymentId = $response->json('payment.id');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_ends_with($request->url(), '/payments')
            && str_contains((string) ($payload['success_url'] ?? ''), '/app?tab=espace&section=subscription')
            && ($payload['metadata']['payment_context'] ?? null) === 'subscription';
    });

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments/SUB_RETURN_V2' => Http::response([
            'reference' => 'SUB_RETURN_V2',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'completed',
            'environment' => 'sandbox',
        ]),
    ]);

    test()->postJson("/api/subscriptions/payments/{$paymentId}/refresh")
        ->assertOk()
        ->assertJsonPath('payment.status', SubscriptionPayment::STATUS_ACTIVATED);

    expect($subscription->refresh()->plan_version_id)->toBe($premium->id);
});

it('routes the historic GeniusPay webhook URL to subscription payments too', function (): void {
    registerAndLogin('subscription-unified-hook@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'subscription-unified-hook@example.com'))->firstOrFail();
    test()->getJson('/api/subscriptions/current')->assertOk();
    $premium = walletV2PublishPaidPlan('GOLD');
    $subscription = UserSubscription::query()->where('account_id', $account->id)->where('status', 'active')->firstOrFail();

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'SUB_UNIFIED_HOOK',
            'checkout_url' => 'https://geniuspay.ci/checkout/SUB_UNIFIED_HOOK',
            'status' => 'pending',
            'environment' => 'sandbox',
        ], 201),
    ]);
    test()->postJson("/api/subscriptions/{$subscription->id}/upgrade", ['plan_version_id' => $premium->id])->assertOk();

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments/SUB_UNIFIED_HOOK' => Http::response([
            'reference' => 'SUB_UNIFIED_HOOK',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'completed',
            'environment' => 'sandbox',
        ]),
    ]);

    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook([
        'reference' => 'SUB_UNIFIED_HOOK',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'completed',
    ]);

    $server = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    test()->call('POST', '/api/webhooks/geniuspay', server: $server, content: $payload)->assertOk();

    expect($subscription->refresh()->plan_version_id)->toBe($premium->id);
});
