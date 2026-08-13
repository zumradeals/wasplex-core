<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Professional;

use App\Modules\Funds\Application\Services\FundRealizationService;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProfessionalFundOrdersController
{
    public function __construct(private readonly FundRealizationService $realizations) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        $orders = FundOrder::query()
            ->with(['wish.category:id,name,icon'])
            ->where('provider_organization_id', $organizationId)
            ->latest('ordered_at')
            ->get()
            ->map(fn (FundOrder $order): array => [
                'id' => $order->id,
                'status' => $order->status,
                'total_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
                'ledger_allocated_minor' => (int) $order->ledger_allocated_minor,
                'externally_settled_minor' => (int) $order->externally_settled_minor,
                'ordered_at' => $order->ordered_at,
                'delivered_at' => $order->delivered_at,
                'wish' => [
                    'reference' => 'FONDS-'.strtoupper(substr((string) $order->fund_wish_id, -6)),
                    'title' => $order->wish->title,
                    'category' => $order->wish->category?->only(['name', 'icon']),
                ],
                'milestones' => DB::table('fund_order_milestones')->where('fund_order_id', $order->id)->orderBy('ordinal')->get(),
                'proofs' => DB::table('fund_order_proofs')->where('fund_order_id', $order->id)->orderByDesc('submitted_at')->get(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function submitProof(Request $request, FundOrder $order): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        $data = $request->validate([
            'milestone_id' => ['nullable', 'string'],
            'proof_type' => ['required', 'string', 'max:48'],
            'reference' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            return response()->json($this->realizations->submitProof(
                $order,
                $organizationId,
                (string) $request->user()->id,
                $data['milestone_id'] ?? null,
                $data['proof_type'],
                $data['reference'] ?? null,
                $data['description'] ?? null,
            ), 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }

    public function markDelivered(Request $request, FundOrder $order): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            $order = $this->realizations->markDelivered($order, $organizationId, (string) $request->user()->id, $data['reference'], $data['description'] ?? null);

            return response()->json(['id' => $order->id, 'status' => $order->status, 'delivered_at' => $order->delivered_at]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }
}
