<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Http\Controllers\Webhook;

use App\Modules\Subscriptions\Application\Services\SubscriptionPaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SubscriptionPaymentWebhookController extends Controller
{
    public function __construct(private readonly SubscriptionPaymentWebhookHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->handler->handle($request->getContent(), [
            'X-Webhook-Signature' => $request->header('X-Webhook-Signature'),
            'X-Webhook-Timestamp' => $request->header('X-Webhook-Timestamp'),
        ]);

        return response()->json(['received' => true]);
    }
}
