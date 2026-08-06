<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Http\Controllers\User;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * docs/08-feed-principal-wasplex.md : le Wallet est central dans la
 * navigation. Self-service, aucune capacité spéciale requise.
 */
final class WalletController extends Controller
{
    public function __construct(private readonly UserWalletQueryService $wallet) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $this->wallet->getOrCreate($account->id);

        return response()->json(['balance_minor' => $this->wallet->balanceMinor($account->id), 'currency' => 'WP']);
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
}
