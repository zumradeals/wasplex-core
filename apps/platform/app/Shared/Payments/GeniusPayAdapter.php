<?php

declare(strict_types=1);

namespace App\Shared\Payments;

use App\Shared\Payments\ValueObjects\CreatePaymentRequest;
use App\Shared\Payments\ValueObjects\ProviderPaymentResult;
use App\Shared\Payments\ValueObjects\ProviderWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * GeniusPay Merchant API adapter (sandbox only). Authentication uses the
 * documented X-API-Key/X-API-Secret pair. It accepts the current nested
 * data response/webhook structure and the former flat sandbox structure so
 * in-flight test transactions remain reconcilable during the transition.
 */
final class GeniusPayAdapter implements PaymentProviderContract
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly string $apiSecret;

    private readonly string $webhookSecret;

    /** @var array<int, string> */
    private readonly array $checkoutHosts;

    public function __construct()
    {
        $environment = (string) config('services.geniuspay.environment', 'sandbox');

        if ($environment !== 'sandbox') {
            throw new PaymentProviderConfigurationException(
                "GeniusPay: seul l'environnement sandbox est autorisé dans ce chantier (reçu : {$environment}).",
            );
        }

        $this->baseUrl = (string) config('services.geniuspay.base_url', '');
        $this->apiKey = (string) config('services.geniuspay.api_key', '');
        $this->apiSecret = (string) config('services.geniuspay.api_secret', '');
        $this->webhookSecret = (string) config('services.geniuspay.webhook_secret', '');
        $this->checkoutHosts = (array) config('services.geniuspay.checkout_hosts', []);

        if (! str_starts_with($this->baseUrl, 'https://')) {
            throw new PaymentProviderConfigurationException('GeniusPay: la base API doit être en HTTPS.');
        }

        if ($this->apiKey === '' || $this->apiSecret === '') {
            throw new PaymentProviderConfigurationException(
                'GeniusPay sandbox n’est pas configuré : renseignez GENIUSPAY_API_KEY et GENIUSPAY_API_SECRET.',
            );
        }

        if ($this->webhookSecret === '') {
            throw new PaymentProviderConfigurationException(
                'GeniusPay sandbox n’est pas configuré : renseignez GENIUSPAY_WEBHOOK_SECRET.',
            );
        }

        if (Str::contains(strtolower($this->apiKey.' '.$this->apiSecret), 'live')) {
            throw new PaymentProviderConfigurationException('GeniusPay: les clés de production ne sont pas autorisées en mode sandbox.');
        }
    }

    public function createPayment(CreatePaymentRequest $request): ProviderPaymentResult
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->authenticationHeaders())
            ->post('/payments', [
                'amount' => $request->amountMinor,
                'currency' => $request->currency,
                'description' => "Recharge Wallet Wasplex {$request->internalReference}",
                'success_url' => rtrim((string) config('app.url'), '/').'/studio?payment=success',
                'error_url' => rtrim((string) config('app.url'), '/').'/studio?payment=failed',
                'metadata' => [
                    'deposit_id' => $request->internalReference,
                    'idempotency_key' => $request->idempotencyKey,
                ],
            ])
            ->throw();

        return $this->normalizePaymentResponse($response->json());
    }

    public function testConnection(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->authenticationHeaders())
            ->get('/account')
            ->throw();

        $payload = $response->json();

        return is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    }

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentResult
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->authenticationHeaders())
            ->get("/payments/{$providerReference}")
            ->throw();

        return $this->normalizePaymentResponse($response->json());
    }

    public function verifyWebhookSignature(string $rawPayload, array $headers): bool
    {
        $signature = $headers['X-GeniusPay-Signature'] ?? $headers['x-geniuspay-signature']
            ?? $headers['X-Webhook-Signature'] ?? $headers['x-webhook-signature'] ?? null;
        $timestamp = $headers['X-GeniusPay-Timestamp'] ?? $headers['x-geniuspay-timestamp']
            ?? $headers['X-Webhook-Timestamp'] ?? $headers['x-webhook-timestamp'] ?? null;

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        if (is_string($timestamp) && ctype_digit($timestamp) && abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->webhookSecret);
        $legacyExpected = is_string($timestamp) && $timestamp !== ''
            ? hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->webhookSecret)
            : null;

        return hash_equals($expected, $signature)
            || ($legacyExpected !== null && hash_equals($legacyExpected, $signature));
    }

    public function parseWebhookPayload(string $rawPayload): ProviderWebhookEvent
    {
        $decoded = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('GeniusPay: charge utile de webhook invalide.');
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $transaction = is_array($data['transaction'] ?? null) ? $data['transaction'] : $data;
        $environment = (string) ($data['environment'] ?? $decoded['environment'] ?? 'unknown');
        $providerStatusRaw = isset($transaction['status']) ? (string) $transaction['status'] : null;

        return new ProviderWebhookEvent(
            eventType: (string) ($decoded['event'] ?? $decoded['event_type'] ?? 'unknown'),
            providerReference: isset($transaction['reference']) ? (string) $transaction['reference'] : null,
            amountMinor: isset($transaction['amount']) ? (int) $transaction['amount'] : null,
            currency: isset($transaction['currency']) ? (string) $transaction['currency'] : null,
            providerStatusRaw: $providerStatusRaw,
            normalizedStatus: $this->normalizeStatus($providerStatusRaw, hasCheckoutUrl: false),
            environment: $environment,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizePaymentResponse(array $payload): ProviderPaymentResult
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $checkoutUrl = isset($data['checkout_url'])
            ? (string) $data['checkout_url']
            : (isset($data['payment_url']) ? (string) $data['payment_url'] : null);

        if ($checkoutUrl !== null && ! $this->isAllowedCheckoutUrl($checkoutUrl)) {
            throw new RuntimeException("GeniusPay: domaine de checkout non autorisé ({$checkoutUrl}).");
        }

        // The real sandbox API can return `status: null` on a successful
        // creation as long as a checkout URL is present (docs/chantiers/
        // HOTFIX-P003-GENIUSPAY-SANDBOX.md §5) — never treat that as failure.
        $providerStatusRaw = isset($data['status']) ? (string) $data['status'] : null;

        return new ProviderPaymentResult(
            providerReference: isset($data['reference']) ? (string) $data['reference'] : null,
            checkoutUrl: $checkoutUrl,
            providerStatusRaw: $providerStatusRaw,
            normalizedStatus: $this->normalizeStatus($providerStatusRaw, hasCheckoutUrl: $checkoutUrl !== null && $checkoutUrl !== ''),
            environment: (string) ($data['environment'] ?? $payload['environment'] ?? 'unknown'),
            amountMinor: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
        );
    }

    private function normalizeStatus(?string $providerStatusRaw, bool $hasCheckoutUrl): string
    {
        return match (true) {
            $providerStatusRaw === null && $hasCheckoutUrl => 'awaiting_payment',
            $providerStatusRaw === null => 'unknown',
            in_array($providerStatusRaw, ['success', 'completed', 'confirmed', 'paid'], true) => 'confirmed',
            in_array($providerStatusRaw, ['pending', 'processing'], true) => 'awaiting_payment',
            in_array($providerStatusRaw, ['failed', 'declined', 'cancelled', 'refunded'], true) => 'rejected',
            $providerStatusRaw === 'expired' => 'expired',
            default => 'unknown',
        };
    }

    /** @return array<string, string> */
    private function authenticationHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Accept' => 'application/json',
        ];
    }

    private function isAllowedCheckoutUrl(string $checkoutUrl): bool
    {
        $host = parse_url($checkoutUrl, PHP_URL_HOST);

        return is_string($host) && in_array($host, $this->checkoutHosts, true) && str_starts_with($checkoutUrl, 'https://');
    }
}
