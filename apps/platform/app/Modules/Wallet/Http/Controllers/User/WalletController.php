<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Http\Controllers\User;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Services\UserWalletDepositService;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Modules\Wallet\Application\Services\UserWalletTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WalletController extends Controller
{
    public function __construct(
        private readonly UserWalletQueryService $wallet,
        private readonly UserWalletDepositService $deposits,
        private readonly UserWalletTransferService $transfers,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $this->wallet->getOrCreate($account->id);

        return response()->json([
            'balance_minor' => $this->wallet->balanceMinor($account->id),
            'currency' => 'WP',
            'summary' => $this->wallet->summary($account->id),
            'capabilities' => [
                'deposit' => true,
                'transfer' => true,
                'withdrawal' => false,
                'withdrawal_reason' => 'Le rail de retrait externe n’est pas encore disponible chez le prestataire de paiement.',
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $entries = $this->wallet->history($account->id)->through(fn ($entry) => [
            'id' => $entry->id,
            'direction' => $entry->direction,
            'amount_minor' => $entry->amount_minor,
            'description' => $entry->description,
            'type' => $entry->transaction?->type,
            'created_at' => $entry->created_at?->toIso8601String(),
        ]);

        return response()->json(['history' => $entries]);
    }

    public function createDeposit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:200'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $deposit = $this->deposits->create($account->id, (int) $data['amount_minor']);

        return response()->json(['deposit' => $deposit], 201);
    }

    public function refreshDeposit(Request $request, string $deposit): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $updated = $this->deposits->reconcile($deposit, $account->id);

        return response()->json(['deposit' => $updated]);
    }

    public function resolveRecipient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'recipient' => $this->transfers->resolveRecipient($account->id, (string) $data['identifier']),
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $transfer = $this->transfers->transfer(
            $account->id,
            (string) $data['recipient_account_id'],
            (int) $data['amount_minor'],
            (string) $data['idempotency_key'],
        );

        return response()->json(['transfer' => $transfer]);
    }
}
