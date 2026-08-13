<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\User;

use App\Modules\Funds\Application\Services\FundWarrantyService;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class FundWarrantyController
{
    public function __construct(private readonly FundWarrantyService $warranties) {}

    public function claim(Request $request, FundOrder $order): JsonResponse
    {
        $data = $request->validate([
            'issue' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        try {
            return response()->json(
                $this->warranties->openClaim($order, (string) $request->user()->id, $data['issue']),
                201,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }
}
