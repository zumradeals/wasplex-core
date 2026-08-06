<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;

/**
 * Same discipline as P003's GeniusPayWebhookHandler: a subscription is
 * never activated from the webhook body alone or from a browser redirect,
 * only after a signed webhook triggers a server-side re-verification of
 * the payment status directly with the provider.
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

        // Server-side re-verification: never trust the webhook body alone.
        $verified = $this->provider->fetchPaymentStatus($payment->provider_reference);

        Log::channel('structured')->info('subscriptions.webhook.verified', [
            'payment_id' => $payment->id,
            'provider_status_raw' => $verified->providerStatusRaw,
        ]);

        match ($verified->normalizedStatus) {
            'confirmed' => $this->confirmAndActivate($payment),
            'rejected' => $payment->update(['status' => SubscriptionPayment::STATUS_REJECTED]),
            'expired' => $payment->update(['status' => SubscriptionPayment::STATUS_EXPIRED]),
            default => null,
        };
    }

    private function confirmAndActivate(SubscriptionPayment $payment): void
    {
        $payment->update(['status' => SubscriptionPayment::STATUS_CONFIRMED]);
        $this->subscriptions->activateFromConfirmedPayment($payment->refresh());
    }
}
