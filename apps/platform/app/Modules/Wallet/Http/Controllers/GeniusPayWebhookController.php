<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Domain\Enums\ProviderEventStatus;
use App\Modules\Wallet\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Wallet\Infrastructure\Models\WalletProviderEvent;
use App\Modules\Wallet\Jobs\ProcessGeniusPayWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class GeniusPayWebhookController
{
    public function __construct(private PaymentGatewayContract $gateway) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $webhook = $this->gateway->parseWebhook($request->getContent(), [
                'signature' => $request->header('X-Webhook-Signature'),
                'timestamp' => $request->header('X-Webhook-Timestamp'),
                'event' => $request->header('X-Webhook-Event'),
                'delivery' => $request->header('X-Webhook-Delivery'),
                'environment' => $request->header('X-Webhook-Environment'),
            ]);
        } catch (PaymentGatewayException $exception) {
            Log::warning('wallet.webhook.rejected', [
                'provider' => 'geniuspay',
                'reason' => $exception::class,
            ]);

            return response()->json(['message' => 'Webhook refusé.'], 401);
        }

        $event = DB::transaction(function () use ($webhook): WalletProviderEvent {
            return WalletProviderEvent::query()->firstOrCreate(
                ['provider' => 'geniuspay', 'event_key' => $webhook->eventKey],
                [
                    'event_name' => $webhook->eventName,
                    'payment_reference' => $webhook->paymentReference,
                    'payload_hash' => $webhook->payloadHash,
                    'status' => ProviderEventStatus::Received,
                    'payload' => $webhook->safePayload,
                    'occurred_at' => $webhook->occurredAt,
                ],
            );
        });

        if ($event->wasRecentlyCreated) {
            ProcessGeniusPayWebhook::dispatch($event->id)->afterCommit();
        }

        return response()->json([
            'data' => [
                'accepted' => true,
                'duplicate' => ! $event->wasRecentlyCreated,
            ],
        ], 202);
    }
}
