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
 * GeniusPay Merchant API adapter (sandbox only). The contract implemented
 * here — base URL, checkout domain allowlist, X-Webhook-* headers, HMAC-
 * SHA256 signature over "timestamp.payload", environment at the payload
 * root, business fields under data.*, a possibly-null initial status — is
 * the *real* provider contract documented in docs/chantiers/
 * HOTFIX-P003-GENIUSPAY-SANDBOX.md after a production incident. Exact
 * endpoint paths beyond the documented base URL (/payments,
 * /payments/{reference}) are this adapter's own REST convention — the
 * hotfix note documents response shapes and headers, not a full path list.
 */
final class GeniusPayAdapter implements PaymentProviderContract
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    private readonly string $baseUrl;

    private readonly string $merchantKey;

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
        $this->merchantKey = (string) config('services.geniuspay.merchant_key', '');
        $this->webhookSecret = (string) config('services.geniuspay.webhook_secret', '');
        $this->checkoutHosts = (array) config('services.geniuspay.checkout_hosts', []);

        if (! str_starts_with($this->baseUrl, 'https://')) {
            throw new PaymentProviderConfigurationException('GeniusPay: la base API doit être en HTTPS.');
        }

        if (Str::contains(strtolower($this->merchantKey), 'live')) {
            throw new PaymentProviderConfigurationException('GeniusPay: une clé de production ("live") ne peut pas être utilisée ici.');
        }
    }

    public function createPayment(CreatePaymentRequest $request): ProviderPaymentResult
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withToken($this->merchantKey)
            ->post('/payments', [
                'amount' => $request->amountMinor,
                'currency' => $request->currency,
                'reference' => $request->internalReference,
                'idempotency_key' => $request->idempotencyKey,
            ])
            ->throw();

        return $this->normalizePaymentResponse($response->json());
    }

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentResult
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withToken($this->merchantKey)
            ->get("/payments/{$providerReference}")
            ->throw();

        return $this->normalizePaymentResponse($response->json());
    }

    public function verifyWebhookSignature(string $rawPayload, array $headers): bool
    {
        $signature = $headers['X-Webhook-Signature'] ?? $headers['x-webhook-signature'] ?? null;
        $timestamp = $headers['X-Webhook-Timestamp'] ?? $headers['x-webhook-timestamp'] ?? null;

        if (! is_string($signature) || ! is_string($timestamp) || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookPayload(string $rawPayload): ProviderWebhookEvent
    {
        $decoded = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('GeniusPay: charge utile de webhook invalide.');
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $environment = (string) ($decoded['environment'] ?? 'unknown');
        $providerStatusRaw = isset($data['status']) ? (string) $data['status'] : null;

        return new ProviderWebhookEvent(
            eventType: (string) ($decoded['event_type'] ?? 'unknown'),
            providerReference: isset($data['reference']) ? (string) $data['reference'] : null,
            amountMinor: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
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
        $checkoutUrl = isset($payload['checkout_url']) ? (string) $payload['checkout_url'] : null;

        if ($checkoutUrl !== null && ! $this->isAllowedCheckoutUrl($checkoutUrl)) {
            throw new RuntimeException("GeniusPay: domaine de checkout non autorisé ({$checkoutUrl}).");
        }

        // The real sandbox API can return `status: null` on a successful
        // creation as long as a checkout URL is present (docs/chantiers/
        // HOTFIX-P003-GENIUSPAY-SANDBOX.md §5) — never treat that as failure.
        $providerStatusRaw = isset($payload['status']) ? (string) $payload['status'] : null;

        return new ProviderPaymentResult(
            providerReference: isset($payload['reference']) ? (string) $payload['reference'] : null,
            checkoutUrl: $checkoutUrl,
            providerStatusRaw: $providerStatusRaw,
            normalizedStatus: $this->normalizeStatus($providerStatusRaw, hasCheckoutUrl: $checkoutUrl !== null && $checkoutUrl !== ''),
            environment: (string) ($payload['environment'] ?? 'unknown'),
        );
    }

    private function normalizeStatus(?string $providerStatusRaw, bool $hasCheckoutUrl): string
    {
        return match (true) {
            $providerStatusRaw === null && $hasCheckoutUrl => 'awaiting_payment',
            $providerStatusRaw === null => 'unknown',
            in_array($providerStatusRaw, ['success', 'confirmed', 'paid'], true) => 'confirmed',
            in_array($providerStatusRaw, ['pending', 'processing'], true) => 'awaiting_payment',
            in_array($providerStatusRaw, ['failed', 'declined'], true) => 'rejected',
            in_array($providerStatusRaw, ['expired'], true) => 'expired',
            default => 'unknown',
        };
    }

    private function isAllowedCheckoutUrl(string $checkoutUrl): bool
    {
        $host = parse_url($checkoutUrl, PHP_URL_HOST);

        return is_string($host) && in_array($host, $this->checkoutHosts, true) && str_starts_with($checkoutUrl, 'https://');
    }
}
