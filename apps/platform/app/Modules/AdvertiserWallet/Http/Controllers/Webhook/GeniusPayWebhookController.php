<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Webhook;

use App\Modules\AdvertiserWallet\Application\Services\GeniusPayWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class GeniusPayWebhookController extends Controller
{
    public function __construct(private readonly GeniusPayWebhookHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->handler->handle($request->getContent(), [
            'X-GeniusPay-Signature' => $request->header('X-GeniusPay-Signature'),
            'X-GeniusPay-Timestamp' => $request->header('X-GeniusPay-Timestamp'),
            'X-Webhook-Signature' => $request->header('X-Webhook-Signature'),
            'X-Webhook-Timestamp' => $request->header('X-Webhook-Timestamp'),
        ]);

        // docs/19-integrations-externes-wasplex.md §23: "la réponse HTTP au
        // webhook doit être séparée du traitement métier lourd" — a plain,
        // fast acknowledgement, no business detail leaked back to the caller.
        return response()->json(['received' => true]);
    }
}
