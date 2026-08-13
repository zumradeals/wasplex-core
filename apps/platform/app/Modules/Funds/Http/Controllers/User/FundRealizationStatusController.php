<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\User;

use App\Modules\Funds\Application\Services\FundRealizationService;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundRealizationStatusController
{
    public function __construct(private readonly FundRealizationService $realizations) {}

    public function index(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $orders = FundOrder::query()
            ->with(['wish.category:id,name,icon', 'providerOrganization:id,name'])
            ->whereHas('wish', fn ($q) => $q->where('account_id', $accountId))
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
                'beneficiary_confirmed_at' => $order->beneficiary_confirmed_at,
                'completed_at' => $order->completed_at,
                'wish' => [
                    'reference' => 'FONDS-'.strtoupper(substr((string) $order->fund_wish_id, -6)),
                    'title' => $order->wish->title,
                    'category' => $order->wish->category?->only(['name', 'icon']),
                ],
                'provider' => $order->providerOrganization?->only(['id', 'name']),
                'milestones' => DB::table('fund_order_milestones')->where('fund_order_id', $order->id)->orderBy('ordinal')->get()->map(fn ($m) => [
                    'id' => $m->id,
                    'ordinal' => $m->ordinal,
                    'label' => $m->label,
                    'amount_minor' => (int) $m->amount_minor,
                    'status' => $m->status,
                    'external_settlement_status' => $m->external_settlement_status,
                ]),
                'open_dispute' => DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->where('status', 'open')->exists(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function confirmDelivery(Request $request, FundOrder $order): JsonResponse
    {
        try {
            $order = $this->realizations->confirmBeneficiary($order, (string) $request->user()->id);
            return response()->json(['id' => $order->id, 'status' => $order->status, 'beneficiary_confirmed_at' => $order->beneficiary_confirmed_at, 'completed_at' => $order->completed_at]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }

    public function dispute(Request $request, FundOrder $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:5000']]);
        try {
            return response()->json($this->realizations->openDispute($order, (string) $request->user()->id, $data['reason']), 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains($e->getMessage(), 'introuvable') ? 404 : 422);
        }
    }
}
