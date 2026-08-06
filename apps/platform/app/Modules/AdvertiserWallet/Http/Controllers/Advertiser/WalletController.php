<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Advertiser;

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WalletController extends Controller
{
    public function __construct(private readonly AdvertiserWalletQueryService $query) {}

    public function show(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        $wallet = $this->query->getOrCreateWallet($organizationId);

        return response()->json([
            'wallet' => [
                'id' => $wallet->id,
                'organization_id' => $wallet->organization_id,
                'currency' => $wallet->currency,
                'available_minor' => $this->query->availableBalanceMinor($organizationId),
            ],
        ]);
    }
}
