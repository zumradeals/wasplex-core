<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Http\Controllers\User;

use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Services\UserWalletPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WalletPinController extends Controller
{
    public function __construct(
        private readonly UserWalletPinService $pin,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        return response()->json(['exists' => $this->pin->hasPin($account->id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'size:4'],
            'pin_confirmation' => ['required', 'string', 'size:4'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $this->pin->create($account->id, (string) $data['pin'], (string) $data['pin_confirmation']);

        $this->auditLogger->record('WalletPinCreated', [
            'actor_account_id' => $account->id,
            'resource_type' => 'user_wallet',
            'resource_id' => $account->id,
        ], $request);

        return response()->json(['message' => 'Code PIN Wallet créé.'], 201);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'string', 'size:4'],
            'pin' => ['required', 'string', 'size:4'],
            'pin_confirmation' => ['required', 'string', 'size:4'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $this->pin->change(
            $account->id,
            (string) $data['current_pin'],
            (string) $data['pin'],
            (string) $data['pin_confirmation'],
        );

        $this->auditLogger->record('WalletPinChanged', [
            'actor_account_id' => $account->id,
            'resource_type' => 'user_wallet',
            'resource_id' => $account->id,
        ], $request);

        return response()->json(['message' => 'Code PIN Wallet modifié.']);
    }
}
