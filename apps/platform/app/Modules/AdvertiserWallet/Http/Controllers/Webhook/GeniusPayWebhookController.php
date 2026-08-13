<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Webhook;

use App\Modules\AdvertiserWallet\Application\Services\GeniusPayWebhookHandler as AdvertiserGeniusPayWebhookHandler;
use App\Modules\AdvertiserWallet\Application\Services\InvalidWebhookSignatureException;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\Subscriptions\Application\Services\SubscriptionPaymentWebhookHandler;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Wallet\Application\Services\UserWalletGeniusPayWebhookHandler;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Point d'entrée GeniusPay unifié. Le webhook configuré dans GeniusPay peut
 * rester /api/webhooks/geniuspay pour les dépôts annonceurs, abonnements et
 * dépôts Wallet utilisateur. Chaque module conserve son propre handler.
 */
final class GeniusPayWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly AdvertiserGeniusPayWebhookHandler $advertiserHandler,
        private readonly SubscriptionPaymentWebhookHandler $subscriptionHandler,
        private readonly UserWalletGeniusPayWebhookHandler $userWalletHandler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $headers = [
            'X-GeniusPay-Signature' => $request->header('X-GeniusPay-Signature'),
            'X-GeniusPay-Timestamp' => $request->header('X-GeniusPay-Timestamp'),
            'X-Webhook-Signature' => $request->header('X-Webhook-Signature'),
            'X-Webhook-Timestamp' => $request->header('X-Webhook-Timestamp'),
        ];

        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            // Preserve P003's immutable invalid-signature audit event. The
            // historical advertiser handler records signature_valid=false
            // before throwing the same security exception.
            $this->advertiserHandler->handle($rawPayload, $headers);

            throw new InvalidWebhookSignatureException;
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);
        $reference = $event->providerReference;

        if ($reference !== null && SubscriptionPayment::query()->where('provider_reference', $reference)->exists()) {
            $this->subscriptionHandler->handle($rawPayload, $headers);
        } elseif ($reference !== null && UserWalletDeposit::query()->where('provider_reference', $reference)->exists()) {
            $this->userWalletHandler->handle($rawPayload, $headers);
        } elseif ($reference !== null && AdvertiserWalletDeposit::query()->where('provider_reference', $reference)->exists()) {
            $this->advertiserHandler->handle($rawPayload, $headers);
        } else {
            // Conserve l'audit historique des références inconnues côté
            // AdvertiserWallet sans polluer cet audit pour les références
            // appartenant à un autre module connu.
            $this->advertiserHandler->handle($rawPayload, $headers);
        }

        return response()->json(['received' => true]);
    }
}
