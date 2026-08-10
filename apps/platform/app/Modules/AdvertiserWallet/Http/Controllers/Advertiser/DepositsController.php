<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Advertiser;

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\AdvertiserWallet\Application\Services\DepositNotFoundException;
use App\Modules\AdvertiserWallet\Application\Services\DepositService;
use App\Shared\Payments\PaymentProviderConfigurationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DepositsController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly AdvertiserWalletQueryService $query,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $page = $this->query->listDeposits($organizationId);

        return response()->json([
            'deposits' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:200'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        try {
            $deposit = $this->deposits->createDeposit(
                $organizationId,
                (string) $request->user()->id,
                $data['amount_minor'],
                strtoupper($data['currency']),
            );
        } catch (PaymentProviderConfigurationException $exception) {
            Log::error('geniuspay.configuration_error', ['message' => $exception->getMessage()]);

            return response()->json([
                'code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED',
                'message' => 'Le paiement sandbox GeniusPay doit encore être configuré par l’administration.',
            ], 503);
        } catch (ConnectionException|RequestException $exception) {
            Log::error('geniuspay.deposit_initialization_failed', ['message' => $exception->getMessage()]);

            return response()->json([
                'code' => 'PAYMENT_PROVIDER_UNAVAILABLE',
                'message' => 'GeniusPay sandbox ne répond pas actuellement. Réessaie dans quelques instants.',
            ], 503);
        } catch (Throwable $exception) {
            Log::error('geniuspay.deposit_unexpected_error', ['message' => $exception->getMessage()]);

            return response()->json([
                'code' => 'PAYMENT_INITIALIZATION_FAILED',
                'message' => 'Le paiement sandbox n’a pas pu être démarré. Vérifie la configuration GeniusPay.',
            ], 502);
        }

        return response()->json(['deposit' => $deposit], 201);
    }

    public function show(Request $request, string $deposit): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');
        $found = $this->query->findDeposit($organizationId, $deposit);

        if ($found === null) {
            throw new DepositNotFoundException($deposit);
        }

        return response()->json(['deposit' => $found]);
    }
}
