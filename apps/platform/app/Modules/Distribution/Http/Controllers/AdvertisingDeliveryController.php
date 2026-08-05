<?php

namespace App\Modules\Distribution\Http\Controllers;

use App\Modules\Distribution\Application\Services\AdvertisingDeliveryService;
use App\Modules\Distribution\Infrastructure\Models\AdvertisingDelivery;
use App\Modules\Distribution\Infrastructure\Models\FeedSession;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

final class AdvertisingDeliveryController extends Controller
{
    public function startSession(
        Request $request,
        AdvertisingDeliveryService $deliveries,
    ): JsonResponse {
        $account = $this->account($request);
        $validated = $request->validate([
            'territory' => ['nullable', 'string', 'max:120'],
            'market' => ['nullable', 'string', 'size:2'],
        ]);
        $session = $deliveries->startSession(
            account: $account,
            idempotencyKey: $this->idempotencyKey($request),
            territory: $validated['territory'] ?? null,
            market: $validated['market'] ?? null,
        );

        return response()->json([
            'data' => [
                'feed_session_id' => $session->id,
                'status' => $session->status->value,
                'market' => $session->market,
                'currency' => $session->currency,
                'territory' => $session->territory,
                'expires_at' => $session->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function next(
        Request $request,
        FeedSession $session,
        AdvertisingDeliveryService $deliveries,
    ): JsonResponse {
        $account = $this->account($request);
        $key = $this->idempotencyKey($request);
        $prepared = $deliveries->reserveNext($account, $session, $key);
        $delivered = $deliveries->deliver($account, $prepared, $key);

        return response()->json([
            'data' => $deliveries->present($account, $delivered),
        ]);
    }

    public function show(
        Request $request,
        AdvertisingDelivery $delivery,
        AdvertisingDeliveryService $deliveries,
    ): JsonResponse {
        $account = $this->account($request);

        return response()->json([
            'data' => $deliveries->present($account, $delivery),
        ]);
    }

    public function release(
        Request $request,
        AdvertisingDelivery $delivery,
        AdvertisingDeliveryService $deliveries,
    ): JsonResponse {
        $account = $this->account($request);
        $released = $deliveries->release(
            $account,
            $delivery,
            $this->idempotencyKey($request),
        );

        return response()->json([
            'data' => $deliveries->present($account, $released),
        ]);
    }

    private function account(Request $request): Account
    {
        $account = $request->user();

        if (! $account instanceof Account) {
            abort(401);
        }

        return $account;
    }

    private function idempotencyKey(Request $request): string
    {
        $header = $request->header('Idempotency-Key');
        $input = $request->input('idempotency_key');
        $key = trim(is_string($header) ? $header : (is_string($input) ? $input : ''));

        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clé Idempotency-Key est obligatoire.',
            ]);
        }

        return $key;
    }
}
