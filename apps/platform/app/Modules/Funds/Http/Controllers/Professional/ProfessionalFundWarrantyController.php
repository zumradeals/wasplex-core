<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Professional;

use App\Modules\Funds\Application\Services\FundWarrantyService;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ProfessionalFundWarrantyController
{
    public function __construct(private readonly FundWarrantyService $warranties) {}

    public function register(Request $request, FundOrder $order): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:190'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
        ]);

        try {
            return response()->json($this->warranties->register(
                $order,
                (string) $request->attributes->get('professional_organization_id'),
                (string) $request->user()->id,
                $data['reference'] ?? null,
                $data['terms'] ?? null,
                $data['starts_at'],
                $data['ends_at'],
            ), 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }

    public function respond(Request $request, FundOrder $order, string $claimId): JsonResponse
    {
        $data = $request->validate([
            'response' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        try {
            return response()->json($this->warranties->respond(
                $order,
                (string) $request->attributes->get('professional_organization_id'),
                (string) $request->user()->id,
                $claimId,
                $data['response'],
            ));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }
}
