<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Admin;

use App\Modules\AdvertiserWallet\Application\Services\SupervisedDepositService;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SupervisedDepositsController extends Controller
{
    public function __construct(private readonly SupervisedDepositService $supervised) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:100'],
            'currency' => ['required', 'string', 'size:3'],
            'proof_reference' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $deposit = $this->supervised->create(
            $data['organization_id'],
            $data['amount_minor'],
            strtoupper($data['currency']),
            $data['proof_reference'],
            $data['reason'],
            (string) $request->user()->id,
        );

        return response()->json(['deposit' => $deposit], 201);
    }

    public function approve(Request $request, string $deposit): JsonResponse
    {
        $record = AdvertiserWalletDeposit::query()->findOrFail($deposit);
        $updated = $this->supervised->approve($record, (string) $request->user()->id);

        return response()->json(['deposit' => $updated]);
    }

    public function reject(Request $request, string $deposit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $record = AdvertiserWalletDeposit::query()->findOrFail($deposit);
        $updated = $this->supervised->reject($record, (string) $request->user()->id, $data['reason']);

        return response()->json(['deposit' => $updated]);
    }
}
