<?php

namespace App\Modules\Wallet\Infrastructure\Payments;

use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Data\CreateGatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayPayment;
use App\Modules\Wallet\Application\Data\GatewayWebhook;
use App\Modules\Wallet\Application\Services\GeniusPayConfigurationService;
use App\Modules\Wallet\Domain\Exceptions\PaymentGatewayException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

final class GeniusPayGateway implements PaymentGatewayContract
{
    public function __construct(private readonly GeniusPayConfigurationService $settings) {}

    public function createPayment(CreateGatewayPayment $request): GatewayPayment
    {
        $runtime = $this->settings->runtime();
        $response = $this->client($runtime)->post($this->baseUrl($runtime).'/payments', [
            'amount' => $request->amountMinor,
            'currency' => strtoupper($request->currency),
            'description' => $request->description,
            'success_url' => $request->successUrl,
            'error_url' => $request->errorUrl,
            'metadata' => $request->metadata,
        ]);

        return $this->paymentFromResponse($response, $runtime);
    }

    public function fetchPayment(string $reference): GatewayPayment
    {
        $runtime = $this->settings->runtime();

        try {
            $response = $this->client($runtime)
                ->retry(2, 250, throw: false)
                ->get($this->baseUrl($runtime).'/payments/'.rawurlencode($reference));
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException('GeniusPay est temporairement injoignable.', previous: $exception);
        }

        return $this->paymentFromResponse($response, $runtime);
    }

    public function parseWebhook(string $payload, array $headers): GatewayWebhook
    {
        $runtime = $this->settings->runtime();
        $secret = (string) $runtime['webhook_secret'];
        $activeEnvironment = strtolower((string) $runtime['environment']);
        $signature = trim((string) ($headers['signature'] ?? ''));
        $timestamp = trim((string) ($headers['timestamp'] ?? ''));
        $headerEvent = trim((string) ($headers['event'] ?? ''));
        $headerEnvironment = strtolower(trim((string) ($headers['environment'] ?? '')));
        $deliveryId = trim((string) ($headers['delivery'] ?? ''));

        if ($secret === '' || $signature === '' || $timestamp === '') {
            throw new PaymentGatewayException('Le webhook GeniusPay ne possède pas les éléments de sécurité requis.');
        }

        if (! ctype_digit($timestamp)) {
            throw new PaymentGatewayException('Le timestamp du webhook GeniusPay est invalide.');
        }

        $signature = preg_replace('/^sha256=/i', '', $signature) ?? $signature;
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new PaymentGatewayException('La signature du webhook GeniusPay est invalide.');
        }

        $tolerance = max(30, (int) $runtime['webhook_tolerance_seconds']);
        if (abs((int) now()->timestamp - (int) $timestamp) > $tolerance) {
            throw new PaymentGatewayException('Le webhook GeniusPay est expiré.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PaymentGatewayException('Le webhook GeniusPay contient un JSON invalide.', previous: $exception);
        }

        $eventName = trim((string) Arr::get($decoded, 'event', ''));
        if ($eventName === '' || ($headerEvent !== '' && ! hash_equals($eventName, $headerEvent))) {
            throw new PaymentGatewayException('Le type du webhook GeniusPay est incohérent.');
        }

        $reference = trim((string) Arr::get($decoded, 'data.reference', ''));
        $status = strtolower(trim((string) Arr::get($decoded, 'data.status', '')));
        $amount = $this->positiveMinorAmount(Arr::get($decoded, 'data.amount'));
        $environment = strtolower(trim((string) Arr::get($decoded, 'environment', $headerEnvironment)));

        if ($headerEnvironment !== '' && $environment !== $headerEnvironment) {
            throw new PaymentGatewayException('L’environnement du webhook GeniusPay est incohérent.');
        }

        if ($reference === '' || $status === '' || $amount === null || $environment !== $activeEnvironment) {
            throw new PaymentGatewayException('Le webhook GeniusPay ne correspond pas à l’environnement actif.');
        }

        $occurred = $this->webhookOccurredAt($decoded, $timestamp);
        $payloadHash = hash('sha256', $payload);
        $providerEventId = trim((string) Arr::get($decoded, 'id', ''));
        $eventKey = $providerEventId !== ''
            ? hash('sha256', 'geniuspay|'.$providerEventId)
            : hash('sha256', implode('|', [$eventName, $reference, $timestamp, $payloadHash]));
        $safeMetadata = $this->safeMetadata((array) Arr::get($decoded, 'data.metadata', []));

        return new GatewayWebhook(
            eventKey: $eventKey,
            payloadHash: $payloadHash,
            eventName: $eventName,
            paymentReference: $reference,
            paymentStatus: $status,
            amountMinor: $amount,
            environment: $environment,
            occurredAt: $occurred,
            safePayload: [
                'event_id' => $providerEventId !== '' ? $providerEventId : null,
                'delivery_id' => $deliveryId !== '' ? $deliveryId : null,
                'event' => $eventName,
                'timestamp' => (int) $timestamp,
                'transaction_id' => (string) Arr::get($decoded, 'data.id', ''),
                'reference' => $reference,
                'amount' => $amount,
                'status' => $status,
                'environment' => $environment,
                'deposit_id' => $safeMetadata['deposit_id'] ?? null,
                'wallet_id' => $safeMetadata['wallet_id'] ?? null,
            ],
        );
    }

    /** @param array<string, mixed> $runtime */
    private function client(array $runtime): PendingRequest
    {
        $this->assertConfiguration($runtime);

        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-Key' => (string) $runtime['api_key'],
                'X-API-Secret' => (string) $runtime['api_secret'],
            ])
            ->connectTimeout(5)
            ->timeout(12);
    }

    /** @param array<string, mixed> $runtime */
    private function baseUrl(array $runtime): string
    {
        return rtrim((string) $runtime['base_url'], '/');
    }

    /** @param array<string, mixed> $runtime */
    private function assertConfiguration(array $runtime): void
    {
        $mode = strtolower((string) $runtime['environment']);
        $baseUrl = $this->baseUrl($runtime);
        $key = (string) $runtime['api_key'];
        $secret = (string) $runtime['api_secret'];
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $path = rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/');

        if (! in_array($mode, ['sandbox', 'production'], true)) {
            throw new PaymentGatewayException('L’environnement GeniusPay actif est invalide.');
        }

        if ($mode === 'production' && ! $this->settings->productionActivationAllowed()) {
            throw new PaymentGatewayException('P003 interdit toute activation GeniusPay en production tant que le verrou de gouvernance est actif.');
        }

        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new PaymentGatewayException('GeniusPay doit être contacté exclusivement en HTTPS.');
        }

        if ($host !== 'geniuspay.ci' || $path !== '/api/v1/merchant') {
            throw new PaymentGatewayException('La base API GeniusPay doit être https://geniuspay.ci/api/v1/merchant.');
        }

        if ($key === '' || $secret === '') {
            throw new PaymentGatewayException("Les identifiants GeniusPay {$mode} ne sont pas configurés.");
        }

        if ($mode === 'sandbox' && (str_contains(strtolower($key), 'live') || str_contains(strtolower($secret), 'live'))) {
            throw new PaymentGatewayException('Une clé GeniusPay live a été refusée dans l’environnement Sandbox.');
        }
    }

    /** @param array<string, mixed> $runtime */
    private function paymentFromResponse(Response $response, array $runtime): GatewayPayment
    {
        if (! $response->successful() || $response->json('success') !== true) {
            $providerMessage = trim((string) ($response->json('error.message') ?? $response->json('message') ?? ''));
            $message = 'GeniusPay n’a pas accepté la demande de paiement.';

            if ($providerMessage !== '') {
                $message .= ' '.$providerMessage;
            }

            throw new PaymentGatewayException($message);
        }

        $data = $response->json('data');
        $data = is_array($data) ? $data : [];

        $id = trim((string) ($data['id'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $amount = $this->positiveMinorAmount($data['amount'] ?? null);
        $fees = $this->nonNegativeMinorAmount($data['fees'] ?? 0) ?? 0;
        $rawStatus = $data['status'] ?? null;
        $status = is_string($rawStatus) ? strtolower(trim($rawStatus)) : '';
        $environment = strtolower(trim((string) ($data['environment'] ?? '')));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'XOF')));
        $checkoutValue = $data['checkout_url'] ?? $data['payment_url'] ?? null;
        $checkoutUrl = is_string($checkoutValue) && trim($checkoutValue) !== '' ? trim($checkoutValue) : null;

        if ($status === '' && $checkoutUrl !== null) {
            $status = 'pending';
        }

        $missing = [];
        if ($id === '') {
            $missing[] = 'data.id';
        }
        if ($reference === '') {
            $missing[] = 'data.reference';
        }
        if ($amount === null) {
            $missing[] = 'data.amount';
        }
        if ($status === '') {
            $missing[] = 'data.status';
        }
        if ($environment === '') {
            $missing[] = 'data.environment';
        }

        if ($missing !== []) {
            Log::warning('wallet.geniuspay.response_incomplete', [
                'http_status' => $response->status(),
                'missing' => $missing,
                'data_keys' => array_map('strval', array_keys($data)),
            ]);

            throw new PaymentGatewayException('La réponse GeniusPay est incompatible : '.implode(', ', $missing).' manquant(s).');
        }

        if ($amount === null) {
            throw new PaymentGatewayException('Le montant GeniusPay est invalide.');
        }

        $activeEnvironment = strtolower((string) $runtime['environment']);
        $allowedProviderEnvironments = $activeEnvironment === 'production'
            ? ['production', 'live']
            : ['sandbox'];

        if (! in_array($environment, $allowedProviderEnvironments, true)) {
            throw new PaymentGatewayException('GeniusPay a retourné un paiement hors de l’environnement actif.');
        }

        if ($checkoutUrl !== null) {
            $checkoutHost = strtolower((string) parse_url($checkoutUrl, PHP_URL_HOST));
            $allowedHosts = array_map(
                static fn (mixed $host): string => strtolower(trim((string) $host)),
                (array) $runtime['checkout_hosts'],
            );

            if (parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https' || ! in_array($checkoutHost, $allowedHosts, true)) {
                throw new PaymentGatewayException('L’URL de checkout GeniusPay n’est pas sécurisée.');
            }
        }

        /** @var array<string, scalar|null> $metadata */
        $metadata = $this->safeMetadata(is_array($data['metadata'] ?? null) ? $data['metadata'] : []);

        return new GatewayPayment(
            id: $id,
            reference: $reference,
            amountMinor: $amount,
            feeMinor: $fees,
            currency: $currency,
            status: $status,
            checkoutUrl: $checkoutUrl,
            environment: $environment,
            metadata: $metadata,
            expiresAt: $this->optionalDate($data['expires_at'] ?? null),
        );
    }

    /** @param array<string, mixed> $decoded */
    private function webhookOccurredAt(array $decoded, string $timestamp): DateTimeImmutable
    {
        $createdAt = Arr::get($decoded, 'created_at');

        if (is_string($createdAt) && trim($createdAt) !== '') {
            try {
                return new DateTimeImmutable($createdAt);
            } catch (\Exception $exception) {
                throw new PaymentGatewayException('La date du webhook GeniusPay est invalide.', previous: $exception);
            }
        }

        return (new DateTimeImmutable('@'.(int) $timestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    private function optionalDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new PaymentGatewayException('La date d’expiration GeniusPay est invalide.', previous: $exception);
        }
    }

    private function positiveMinorAmount(mixed $value): ?int
    {
        $amount = $this->wholeNumber($value);

        return $amount !== null && $amount > 0 ? $amount : null;
    }

    private function nonNegativeMinorAmount(mixed $value): ?int
    {
        $amount = $this->wholeNumber($value);

        return $amount !== null && $amount >= 0 ? $amount : null;
    }

    private function wholeNumber(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
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
