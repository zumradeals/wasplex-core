<?php

declare(strict_types=1);

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Application\Services\CardPaymentService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardPaymentController
{
    public function __construct(
        private readonly CardPaymentService $payments,
        private readonly UserWalletQueryService $wallet,
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string', 'max:200'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'payment' => $this->payments->preview($account->id, (string) $data['payload']),
        ]);
    }

    public function pay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['required', 'string', 'max:200'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $operation = $this->payments->pay(
            $account->id,
            (string) $data['payload'],
            isset($data['amount_minor']) ? (int) $data['amount_minor'] : null,
            (string) $data['idempotency_key'],
        );

        return response()->json([
            'receipt' => $this->payments->receipt($operation, $account->id),
            'balance_minor' => $this->wallet->balanceMinor($account->id),
        ]);
    }

    public function operations(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'operations' => $this->payments->history($account->id),
        ]);
    }
}
