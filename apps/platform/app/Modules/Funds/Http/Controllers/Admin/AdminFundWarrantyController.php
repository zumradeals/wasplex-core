<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Modules\Funds\Application\Services\FundWarrantyService;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

final class AdminFundWarrantyController
{
    public function __construct(private readonly FundWarrantyService $warranties) {}

    public function resolve(Request $request, FundOrder $order, string $claimId): JsonResponse
    {
        $data = $request->validate([
            'resolution_code' => ['required', Rule::in(['provider_fix', 'replacement', 'accepted_as_is', 'rejected', 'closed'])],
            'resolution_note' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        try {
            return response()->json($this->warranties->resolve(
                $order,
                $claimId,
                (string) $request->user()->id,
                $data['resolution_code'],
                $data['resolution_note'],
            ));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }
}
