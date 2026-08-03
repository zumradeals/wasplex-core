<?php

use App\Modules\Wallet\Application\Data\CreateGatewayPayment;
use App\Modules\Wallet\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Wallet\Infrastructure\Payments\GeniusPayGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.geniuspay.mode', 'sandbox');
    config()->set('services.geniuspay.base_url', 'https://pay.genius.ci/api/v1/merchant');
    config()->set('services.geniuspay.api_key', 'pk_sandbox_test');
    config()->set('services.geniuspay.api_secret', 'sk_sandbox_test');
    config()->set('services.geniuspay.webhook_secret', 'whsec_test');
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

it('rejects an expired signed webhook', function (): void {
    $payload = json_encode([
        'event' => 'payment.success',
        'timestamp' => now()->subMinutes(10)->toIso8601String(),
        'data' => [
            'transaction' => ['reference' => 'MTX-OLD', 'amount' => 100000, 'status' => 'completed'],
            'environment' => 'sandbox',
        ],
    ], JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->subMinutes(10)->timestamp;

    expect(fn () => app(GeniusPayGateway::class)->parseWebhook($payload, [
        'signature' => hash_hmac('sha256', $payload, 'whsec_test'),
        'timestamp' => $timestamp,
        'event' => 'payment.success',
    ]))->toThrow(PaymentGatewayException::class, 'expiré');
});

it('refuses a checkout URL outside the GeniusPay hosted domain', function (): void {
    Http::fake([
        'https://pay.genius.ci/api/v1/merchant/payments' => Http::response([
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
