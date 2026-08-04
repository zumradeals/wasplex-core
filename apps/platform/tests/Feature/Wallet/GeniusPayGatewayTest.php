<?php

use App\Modules\Wallet\Application\Data\CreateGatewayPayment;
use App\Modules\Wallet\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Wallet\Infrastructure\Payments\GeniusPayGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.geniuspay.mode', 'sandbox');
    config()->set('services.geniuspay.base_url', 'https://geniuspay.ci/api/v1/merchant');
    config()->set('services.geniuspay.checkout_hosts', ['geniuspay.ci', 'pay.genius.ci']);
    config()->set('services.geniuspay.api_key', 'pk_sandbox_test');
    config()->set('services.geniuspay.api_secret', 'sk_sandbox_test');
    config()->set('services.geniuspay.webhook_secret', 'whsec_sandbox_test');
    config()->set('services.geniuspay.webhook_tolerance_seconds', 300);
});

it('refuses live credentials even when the application is misconfigured', function (): void {
    config()->set('services.geniuspay.mode', 'live');
    config()->set('services.geniuspay.api_key', 'pk_live_forbidden');
    config()->set('services.geniuspay.api_secret', 'sk_live_forbidden');
    Http::preventStrayRequests();

    expect(fn () => app(GeniusPayGateway::class)->createPayment(gatewayPaymentRequest()))
        ->toThrow(PaymentGatewayException::class, 'interdit toute activation GeniusPay en production');

    Http::assertNothingSent();
});

it('refuses the obsolete GeniusPay API base URL', function (): void {
    config()->set('services.geniuspay.base_url', 'https://pay.genius.ci/api/v1/merchant');
    Http::preventStrayRequests();

    expect(fn () => app(GeniusPayGateway::class)->createPayment(gatewayPaymentRequest()))
        ->toThrow(PaymentGatewayException::class, 'base API GeniusPay');

    Http::assertNothingSent();
});

it('parses the documented merchant checkout response', function (): void {
    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'id' => 123,
                'reference' => 'MTX-A1B2C3D4E5',
                'amount' => 100000,
                'fees' => 3000,
                'currency' => 'XOF',
                'status' => 'pending',
                'checkout_url' => 'https://geniuspay.ci/checkout/MTX-A1B2C3D4E5',
                'payment_url' => 'https://geniuspay.ci/checkout/MTX-A1B2C3D4E5',
                'metadata' => ['deposit_id' => '01TESTDEPOSIT'],
                'environment' => 'sandbox',
                'expires_at' => now()->addDay()->toIso8601String(),
            ],
        ], 201),
    ]);

    $payment = app(GeniusPayGateway::class)->createPayment(gatewayPaymentRequest());

    expect($payment->id)->toBe('123')
        ->and($payment->reference)->toBe('MTX-A1B2C3D4E5')
        ->and($payment->amountMinor)->toBe(100000)
        ->and($payment->feeMinor)->toBe(3000)
        ->and($payment->checkoutUrl)->toBe('https://geniuspay.ci/checkout/MTX-A1B2C3D4E5')
        ->and($payment->environment)->toBe('sandbox')
        ->and($payment->expiresAt)->not->toBeNull();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://geniuspay.ci/api/v1/merchant/payments'
        && $request->hasHeader('X-API-Key', 'pk_sandbox_test')
        && $request->hasHeader('X-API-Secret', 'sk_sandbox_test')
        && $request['amount'] === 100000
        && $request['metadata']['deposit_id'] === '01TESTDEPOSIT');
});

it('parses the documented signed webhook without retaining customer data', function (): void {
    $timestamp = (string) now()->timestamp;
    $payload = json_encode([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'event' => 'payment.success',
        'timestamp' => (int) $timestamp,
        'created_at' => now()->toIso8601String(),
        'data' => [
            'object' => 'transaction',
            'id' => 12345,
            'reference' => 'MTX-WEBHOOK-OK',
            'amount' => 100000.0,
            'currency' => 'XOF',
            'fees' => 3000.0,
            'status' => 'completed',
            'customer_name' => 'Ne doit pas être stocké',
            'customer_phone' => '+2250000000000',
            'metadata' => [
                'deposit_id' => '01TESTDEPOSIT',
                'wallet_id' => '01TESTWALLET',
                'customer_email' => 'secret@example.com',
            ],
        ],
        'environment' => 'sandbox',
        'api_version' => '2024-01-01',
    ], JSON_THROW_ON_ERROR);

    $webhook = app(GeniusPayGateway::class)->parseWebhook($payload, [
        'signature' => hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_sandbox_test'),
        'timestamp' => $timestamp,
        'event' => 'payment.success',
        'delivery' => 'delivery-001',
        'environment' => 'sandbox',
    ]);

    expect($webhook->paymentReference)->toBe('MTX-WEBHOOK-OK')
        ->and($webhook->paymentStatus)->toBe('completed')
        ->and($webhook->amountMinor)->toBe(100000)
        ->and($webhook->environment)->toBe('sandbox')
        ->and($webhook->safePayload)->toHaveKey('deposit_id', '01TESTDEPOSIT')
        ->and($webhook->safePayload)->not->toHaveKey('customer_name')
        ->and($webhook->safePayload)->not->toHaveKey('customer_phone')
        ->and($webhook->safePayload)->not->toHaveKey('customer_email');
});

it('rejects an expired signed webhook', function (): void {
    $timestamp = (string) now()->subMinutes(10)->timestamp;
    $payload = json_encode([
        'id' => 'expired-event',
        'event' => 'payment.success',
        'timestamp' => (int) $timestamp,
        'data' => [
            'id' => 123,
            'reference' => 'MTX-OLD',
            'amount' => 100000,
            'status' => 'completed',
        ],
        'environment' => 'sandbox',
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(GeniusPayGateway::class)->parseWebhook($payload, [
        'signature' => hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_sandbox_test'),
        'timestamp' => $timestamp,
        'event' => 'payment.success',
        'environment' => 'sandbox',
    ]))->toThrow(PaymentGatewayException::class, 'expiré');
});

it('refuses a checkout URL outside the GeniusPay hosted domains', function (): void {
    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'id' => 999,
                'reference' => 'MTX-BAD-URL',
                'amount' => 100000,
                'fees' => 0,
                'currency' => 'XOF',
                'status' => 'pending',
                'checkout_url' => 'https://example.org/faux-checkout',
                'environment' => 'sandbox',
            ],
        ], 201),
    ]);

    expect(fn () => app(GeniusPayGateway::class)->createPayment(gatewayPaymentRequest()))
        ->toThrow(PaymentGatewayException::class, 'URL de checkout GeniusPay n’est pas sécurisée');
});

function gatewayPaymentRequest(): CreateGatewayPayment
{
    return new CreateGatewayPayment(
        depositId: '01TESTDEPOSIT',
        amountMinor: 100000,
        currency: 'XOF',
        description: 'Dépôt de test',
        successUrl: 'https://wasplex.com/studio/wallet?paiement=retour',
        errorUrl: 'https://wasplex.com/studio/wallet?paiement=annule',
        metadata: ['deposit_id' => '01TESTDEPOSIT'],
    );
}
