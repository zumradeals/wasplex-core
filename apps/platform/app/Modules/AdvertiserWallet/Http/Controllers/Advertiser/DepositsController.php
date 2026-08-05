<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Advertiser;

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\AdvertiserWallet\Application\Services\DepositNotFoundException;
use App\Modules\AdvertiserWallet\Application\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
            'amount_minor' => ['required', 'integer', 'min:100'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $organizationId = (string) $request->attributes->get('advertiser_organization_id');

        $deposit = $this->deposits->createDeposit(
            $organizationId,
            (string) $request->user()->id,
            $data['amount_minor'],
            strtoupper($data['currency']),
        );

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
