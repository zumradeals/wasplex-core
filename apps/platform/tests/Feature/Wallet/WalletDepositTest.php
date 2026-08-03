<?php

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Services\WalletDepositService;
use App\Modules\Wallet\Domain\Enums\DepositStatus;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\WalletProviderEvent;
use App\Modules\Wallet\Jobs\ProcessGeniusPayWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.geniuspay.mode', 'sandbox');
    config()->set('services.geniuspay.base_url', 'https://pay.genius.ci/api/v1/merchant');
    config()->set('services.geniuspay.api_key', 'pk_sandbox_test');
    config()->set('services.geniuspay.api_secret', 'sk_sandbox_test');
    config()->set('services.geniuspay.webhook_secret', 'whsec_test');
});

it('credits one advertiser wallet once after a signed webhook and server verification', function (): void {
    registerAccount($this, 'advertiser-wallet@example.com');
    $spaceId = $this->postJson('/api/me/spaces/advertiser', ['organization_name' => 'GamaDeals'])
        ->assertCreated()->json('data.space_id');
    $this->postJson("/api/me/spaces/{$spaceId}/switch")->assertOk();

    Http::fake(function ($request) {
        if ($request->method() === 'POST') {
            return Http::response([
                'success' => true,
                'data' => [
                    'id' => 456,
                    'reference' => 'MTX-P003-100K',
                    'amount' => 100000,
                    'fees' => 3000,
                    'currency' => 'XOF',
                    'status' => 'pending',
                    'checkout_url' => 'https://pay.genius.ci/checkout/P003',
                    'environment' => 'sandbox',
                ],
            ]);
        }

        return Http::response([
            'success' => true,
            'data' => [
                'id' => 456,
                'reference' => 'MTX-P003-100K',
                'amount' => 100000,
                'fees' => 3000,
                'currency' => 'XOF',
                'status' => 'completed',
                'environment' => 'sandbox',
            ],
        ]);
    });

    $this->withHeader('Idempotency-Key', 'deposit-test-advertiser-100k')
        ->postJson('/api/wallet/deposits', ['amount_minor' => 100000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'awaiting_payment')
        ->assertJsonPath('data.checkoutUrl', 'https://pay.genius.ci/checkout/P003');

    $timestamp = (string) now()->timestamp;
    $payload = [
        'event' => 'payment.success',
        'timestamp' => now()->toIso8601String(),
        'data' => [
            'transaction' => [
                'id' => 456,
                'reference' => 'MTX-P003-100K',
                'amount' => 100000,
                'status' => 'completed',
                'customer' => ['name' => 'Ne doit pas être stocké', 'phone' => '+2250000000000'],
            ],
            'environment' => 'sandbox',
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $raw, 'whsec_test');
    $headers = [
        'X-GeniusPay-Signature' => $signature,
        'X-GeniusPay-Timestamp' => $timestamp,
        'X-GeniusPay-Event' => 'payment.success',
    ];

    $this->withHeaders($headers)->postJson('/api/webhooks/geniuspay', $payload)
        ->assertStatus(202)->assertJsonPath('data.duplicate', false);
    $this->withHeaders($headers)->postJson('/api/webhooks/geniuspay', $payload)
        ->assertStatus(202)->assertJsonPath('data.duplicate', true);

    $event = WalletProviderEvent::query()->firstOrFail();
    $job = new ProcessGeniusPayWebhook($event->id);
    $job->handle(app(PaymentGatewayContract::class), app(WalletDepositService::class));
    $job->handle(app(PaymentGatewayContract::class), app(WalletDepositService::class));

    $deposit = WalletDeposit::query()->firstOrFail();
    $wallet = Wallet::query()->with('projection')->firstOrFail();

    expect($deposit->status)->toBe(DepositStatus::Credited)
        ->and($deposit->ledger_transaction_id)->not->toBeNull()
        ->and($wallet->projection?->available_minor)->toBe(100000)
        ->and($wallet->projection?->reserved_minor)->toBe(0)
        ->and(LedgerTransaction::query()->where('type', 'DEPOSIT')->count())->toBe(1)
        ->and(WalletProviderEvent::query()->count())->toBe(1)
        ->and(WalletProviderEvent::query()->firstOrFail()->payload)->not->toHaveKey('customer');
});

it('rejects a forged GeniusPay webhook without creating value', function (): void {
    $payload = [
        'event' => 'payment.success',
        'timestamp' => now()->toIso8601String(),
        'data' => ['transaction' => ['reference' => 'FORGED', 'amount' => 100000, 'status' => 'completed'], 'environment' => 'sandbox'],
    ];

    $this->withHeaders([
        'X-GeniusPay-Signature' => str_repeat('0', 64),
        'X-GeniusPay-Timestamp' => (string) now()->timestamp,
        'X-GeniusPay-Event' => 'payment.success',
    ])->postJson('/api/webhooks/geniuspay', $payload)->assertUnauthorized();

    expect(WalletProviderEvent::query()->count())->toBe(0)
        ->and(LedgerTransaction::query()->count())->toBe(0);
});

it('keeps personal and advertiser wallets isolated', function (): void {
    registerAccount($this, 'isolated-wallets@example.com');
    $this->getJson('/api/wallet')->assertOk()->assertJsonPath('data.kind', 'user');
    $spaceId = $this->postJson('/api/me/spaces/advertiser', ['organization_name' => 'Wallet séparé'])
        ->assertCreated()->json('data.space_id');
    $this->postJson("/api/me/spaces/{$spaceId}/switch")->assertOk();
    $this->getJson('/api/wallet')->assertOk()->assertJsonPath('data.kind', 'advertiser');

    expect(Wallet::query()->count())->toBe(2)
        ->and(Wallet::query()->distinct('space_id')->count('space_id'))->toBe(2);
});

it('does not create a second provider payment when an idempotency key is reused', function (): void {
    registerAccount($this, 'idempotent-deposit@example.com');

    Http::fake([
        'https://pay.genius.ci/api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'id' => 789,
                'reference' => 'MTX-P003-IDEMPOTENT',
                'amount' => 100000,
                'fees' => 3000,
                'currency' => 'XOF',
                'status' => 'pending',
                'checkout_url' => 'https://pay.genius.ci/checkout/IDEMPOTENT',
                'environment' => 'sandbox',
            ],
        ], 201),
    ]);

    $headers = ['Idempotency-Key' => 'deposit-test-same-key'];
    $this->withHeaders($headers)->postJson('/api/wallet/deposits', ['amount_minor' => 100000])
        ->assertCreated();
    $this->withHeaders($headers)->postJson('/api/wallet/deposits', ['amount_minor' => 200000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(WalletDeposit::query()->count())->toBe(1);
    Http::assertSentCount(1);
});
