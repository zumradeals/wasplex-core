<?php

namespace App\Modules\Wallet\Infrastructure\Payments;

use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Data\CreateGatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayWebhook;
use App\Modules\Wallet\Domain\Exceptions\PaymentGatewayException;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use JsonException;

final class GeniusPayGateway implements PaymentGatewayContract
{
    public function createPayment(CreateGatewayPayment $request): GatewayPayment
    {
        $response = $this->client()->post($this->baseUrl().'/payments', [
            'amount' => $request->amountMinor,
            'currency' => strtoupper($request->currency),
            'description' => $request->description,
            'success_url' => $request->successUrl,
            'error_url' => $request->errorUrl,
            'metadata' => $request->metadata,
        ]);

        return $this->paymentFromResponse($response);
    }

    public function fetchPayment(string $reference): GatewayPayment
    {
        try {
            $response = $this->client()
                ->retry(2, 250, throw: false)
                ->get($this->baseUrl().'/payments/'.rawurlencode($reference));
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('GeniusPay est temporairement injoignable.', previous: $exception);
        }

        return $this->paymentFromResponse($response);
    }

    public function parseWebhook(string $payload, array $headers): GatewayWebhook
    {
        $secret = (string) config('services.geniuspay.webhook_secret', '');
        $signature = (string) ($headers['signature'] ?? '');
        $timestamp = (string) ($headers['timestamp'] ?? '');
        $headerEvent = (string) ($headers['event'] ?? '');

        if ($secret === '' || $signature === '' || $timestamp === '') {
            throw new PaymentGatewayException('Le webhook GeniusPay ne possède pas les éléments de sécurité requis.');
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new PaymentGatewayException('La signature du webhook GeniusPay est invalide.');
        }

        if (! ctype_digit($timestamp)) {
            throw new PaymentGatewayException('Le timestamp du webhook GeniusPay est invalide.');
        }

        $tolerance = max(30, (int) config('services.geniuspay.webhook_tolerance_seconds', 300));
        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            throw new PaymentGatewayException('Le webhook GeniusPay est expiré.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PaymentGatewayException('Le webhook GeniusPay contient un JSON invalide.', previous: $exception);
        }

        $eventName = (string) Arr::get($decoded, 'event', '');
        if ($eventName === '' || ($headerEvent !== '' && ! hash_equals($eventName, $headerEvent))) {
            throw new PaymentGatewayException('Le type du webhook GeniusPay est incohérent.');
        }

        $reference = (string) Arr::get($decoded, 'data.transaction.reference', '');
        $status = strtolower((string) Arr::get($decoded, 'data.transaction.status', ''));
        $amount = filter_var(Arr::get($decoded, 'data.transaction.amount'), FILTER_VALIDATE_INT);
        $environment = strtolower((string) Arr::get($decoded, 'data.environment', ''));
        $occurredAt = (string) Arr::get($decoded, 'timestamp', '');

        if ($reference === '' || $status === '' || $amount === false || $amount <= 0 || $environment !== 'sandbox' || $occurredAt === '') {
            throw new PaymentGatewayException('Le webhook GeniusPay ne correspond pas à un paiement sandbox exploitable.');
        }

        try {
            $occurred = new DateTimeImmutable($occurredAt);
        } catch (\Exception $exception) {
            throw new PaymentGatewayException('La date du webhook GeniusPay est invalide.', previous: $exception);
        }

        $payloadHash = hash('sha256', $payload);
        $eventKey = hash('sha256', implode('|', [$eventName, $reference, $timestamp, $payloadHash]));
        $safeMetadata = $this->safeMetadata((array) Arr::get($decoded, 'data.transaction.metadata', []));

        return new GatewayWebhook(
            eventKey: $eventKey,
            payloadHash: $payloadHash,
            eventName: $eventName,
            paymentReference: $reference,
            paymentStatus: $status,
            amountMinor: (int) $amount,
            environment: $environment,
            occurredAt: $occurred,
            safePayload: [
                'event' => $eventName,
                'timestamp' => $occurredAt,
                'transaction_id' => (string) Arr::get($decoded, 'data.transaction.id', ''),
                'reference' => $reference,
                'amount' => (int) $amount,
                'status' => $status,
                'environment' => $environment,
                'metadata' => $safeMetadata,
            ],
        );
    }

    private function client(): PendingRequest
    {
        $this->assertSandboxConfiguration();

        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-Key' => (string) config('services.geniuspay.api_key'),
                'X-API-Secret' => (string) config('services.geniuspay.api_secret'),
            ])
            ->connectTimeout(5)
            ->timeout(12);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.geniuspay.base_url'), '/');
    }

    private function assertSandboxConfiguration(): void
    {
        $mode = strtolower((string) config('services.geniuspay.mode', 'sandbox'));
        $baseUrl = $this->baseUrl();
        $key = (string) config('services.geniuspay.api_key', '');
        $secret = (string) config('services.geniuspay.api_secret', '');

        if ($mode !== 'sandbox') {
            throw new PaymentGatewayException('P003 interdit toute activation GeniusPay en production.');
        }

        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new PaymentGatewayException('GeniusPay doit être contacté exclusivement en HTTPS.');
        }

        if ($key === '' || $secret === '') {
            throw new PaymentGatewayException('Les identifiants GeniusPay sandbox ne sont pas configurés sur le serveur.');
        }

        if (str_contains(strtolower($key), 'live') || str_contains(strtolower($secret), 'live')) {
            throw new PaymentGatewayException('Une clé GeniusPay live a été refusée par le verrou P003.');
        }
    }

    private function paymentFromResponse(Response $response): GatewayPayment
    {
        if (! $response->successful() || $response->json('success') !== true) {
            throw new PaymentGatewayException('GeniusPay n’a pas accepté la demande de paiement sandbox.');
        }

        $id = (string) $response->json('data.id', '');
        $reference = (string) $response->json('data.reference', '');
        $amount = filter_var($response->json('data.amount'), FILTER_VALIDATE_INT);
        $fees = filter_var($response->json('data.fees', 0), FILTER_VALIDATE_INT);
        $status = strtolower((string) $response->json('data.status', ''));
        $environment = strtolower((string) $response->json('data.environment', 'sandbox'));
        $currency = strtoupper((string) $response->json('data.currency', 'XOF'));
        $checkoutUrl = $response->json('data.checkout_url') ?? $response->json('data.payment_url');

        if ($id === '' || $reference === '' || $amount === false || $amount <= 0 || $status === '') {
            throw new PaymentGatewayException('La réponse GeniusPay est incomplète.');
        }

        if ($environment !== 'sandbox') {
            throw new PaymentGatewayException('GeniusPay a retourné un paiement hors sandbox.');
        }

        if ($checkoutUrl !== null) {
            $checkoutHost = strtolower((string) parse_url((string) $checkoutUrl, PHP_URL_HOST));
            if (parse_url((string) $checkoutUrl, PHP_URL_SCHEME) !== 'https' || $checkoutHost !== 'pay.genius.ci') {
                throw new PaymentGatewayException('L’URL de checkout GeniusPay n’est pas sécurisée.');
            }
        }

        /** @var array<string, scalar|null> $metadata */
        $metadata = $this->safeMetadata((array) $response->json('data.metadata', []));

        return new GatewayPayment(
            id: $id,
            reference: $reference,
            amountMinor: (int) $amount,
            feeMinor: $fees === false ? 0 : (int) $fees,
            currency: $currency,
            status: $status,
            checkoutUrl: $checkoutUrl === null ? null : (string) $checkoutUrl,
            environment: $environment,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, scalar|null>
     */
    private function safeMetadata(array $metadata): array
    {
        $safe = [];
        foreach (['deposit_id', 'wallet_id'] as $key) {
            $value = $metadata[$key] ?? null;
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
