<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Admin;

use App\Modules\AdvertiserWallet\Application\Services\GeniusPayConfigurationService;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

final class GeniusPayConfigurationController extends Controller
{
    public function __construct(private readonly GeniusPayConfigurationService $configuration) {}

    public function show(): JsonResponse
    {
        return response()->json(['geniuspay' => $this->configuration->status()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'starts_with:pk_sandbox_'],
            'api_secret' => ['nullable', 'string', 'starts_with:sk_sandbox_'],
            'webhook_secret' => ['nullable', 'string', 'starts_with:whsec_'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'geniuspay' => $this->configuration->save($data, $account->id),
            'message' => 'Configuration GeniusPay sandbox enregistrée.',
        ]);
    }

    public function test(): JsonResponse
    {
        try {
            $account = $this->configuration->testConnection();
        } catch (ConnectionException|RequestException) {
            return response()->json([
                'connected' => false,
                'message' => 'Connexion refusée. Vérifie les clés sandbox saisies.',
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'connected' => false,
                'message' => 'Le test GeniusPay n’a pas pu être effectué.',
            ], 422);
        }

        return response()->json([
            'connected' => true,
            'message' => 'Connexion GeniusPay sandbox réussie.',
            'account' => $account,
        ]);
    }
}
