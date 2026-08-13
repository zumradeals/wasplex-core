<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;

/**
 * Le webhook n'est qu'un déclencheur. La confirmation économique passe
 * toujours par SubscriptionService::reconcilePayment(), qui relit le
 * paiement directement chez GeniusPay avant activation.
 */
final class SubscriptionPaymentWebhookHandler
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(string $rawPayload, array $headers): void
    {
        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            Log::channel('structured')->warning('subscriptions.webhook.invalid_signature');

            throw new InvalidWebhookSignatureException;
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);

        $payment = $event->providerReference !== null
            ? SubscriptionPayment::query()->where('provider_reference', $event->providerReference)->first()
            : null;

        if ($payment === null) {
            Log::channel('structured')->info('subscriptions.webhook.unknown_reference', [
                'provider_reference' => $event->providerReference,
            ]);

            return;
        }

        if ($payment->isTerminal()) {
            Log::channel('structured')->info('subscriptions.webhook.duplicated', ['payment_id' => $payment->id]);

            return;
        }

        $updated = $this->subscriptions->reconcilePayment($payment->id, $payment->account_id);

        Log::channel('structured')->info('subscriptions.webhook.reconciled', [
            'payment_id' => $payment->id,
            'status' => $updated->status,
        ]);
    }
}
