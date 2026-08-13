<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Modules\Funds\Application\Services\FundRealizationService;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final class AdminFundRealizationsController
{
    public function __construct(private readonly FundRealizationService $realizations) {}

    public function index(): JsonResponse
    {
        $orders = FundOrder::query()
            ->with(['wish.category:id,name,icon', 'providerOrganization:id,name', 'quote:id,amount_minor,currency'])
            ->latest('ordered_at')
            ->limit(100)
            ->get()
            ->map(fn (FundOrder $order): array => $this->payload($order));

        $ready = FundWish::query()
            ->with(['providerOrganization:id,name', 'collectionSnapshot'])
            ->whereNotNull('selected_quote_id')
            ->whereNotNull('provider_organization_id')
            ->whereDoesntHave('order')
            ->whereHas('collectionSnapshot', fn ($q) => $q->where('status', 'funded'))
            ->latest('cost_validated_at')
            ->get()
            ->map(fn (FundWish $wish): array => [
                'id' => $wish->id,
                'reference' => 'FONDS-'.strtoupper(substr((string) $wish->id, -6)),
                'title' => $wish->title,
                'validated_amount_minor' => (int) $wish->validated_amount_minor,
                'currency' => $wish->currency,
                'provider' => $wish->providerOrganization?->only(['id', 'name']),
            ]);

        return response()->json([
            'ready_wishes' => $ready,
            'orders' => $orders,
            'reserve_balance_minor' => $this->realizations->reserveBalanceMinor(),
        ]);
    }

    public function create(Request $request, FundWish $wish): JsonResponse
    {
        try {
            return response()->json($this->payload($this->realizations->createOrder($wish, (string) $request->user()->id)), 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function addMilestone(Request $request, FundOrder $order): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'proof_required' => ['nullable', 'boolean'],
            'due_at' => ['nullable', 'date'],
        ]);
        try {
            return response()->json($this->realizations->addMilestone($order, $data['label'], $data['amount_minor'], $data['proof_required'] ?? true, $data['due_at'] ?? null), 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approveMilestone(Request $request, FundOrder $order, string $milestoneId): JsonResponse
    {
        try {
            return response()->json($this->realizations->approveMilestone($order, $milestoneId, (string) $request->user()->id));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function postMilestone(Request $request, FundOrder $order, string $milestoneId): JsonResponse
    {
        try {
            return response()->json($this->realizations->postMilestoneToLedger($order, $milestoneId, (string) $request->user()->id));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function confirmSettlement(Request $request, FundOrder $order, string $milestoneId): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:190']]);
        try {
            return response()->json($this->realizations->confirmExternalSettlement($order, $milestoneId, $data['reference']));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function authorizeReserve(Request $request, FundOrder $order): JsonResponse
    {
        $data = $request->validate(['amount_minor' => ['required', 'integer', 'min:0']]);
        try {
            return response()->json($this->payload($this->realizations->authorizeReserve($order, $data['amount_minor'])));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function resolveDispute(Request $request, FundOrder $order, string $disputeId): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', Rule::in(['accept_delivery', 'resume', 'cancel'])],
            'note' => ['required', 'string', 'max:5000'],
        ]);
        try {
            return response()->json($this->payload($this->realizations->resolveDispute($order, $disputeId, (string) $request->user()->id, $data['resolution'], $data['note'])));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function payload(FundOrder $order): array
    {
        $order->loadMissing(['wish.category', 'providerOrganization', 'quote']);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'total_minor' => (int) $order->total_minor,
            'currency' => $order->currency,
            'authorized_reserve_minor' => (int) $order->authorized_reserve_minor,
            'ledger_allocated_minor' => (int) $order->ledger_allocated_minor,
            'externally_settled_minor' => (int) $order->externally_settled_minor,
            'ordered_at' => $order->ordered_at,
            'delivered_at' => $order->delivered_at,
            'beneficiary_confirmed_at' => $order->beneficiary_confirmed_at,
            'completed_at' => $order->completed_at,
            'wish' => [
                'id' => $order->wish->id,
                'reference' => 'FONDS-'.strtoupper(substr((string) $order->wish->id, -6)),
                'title' => $order->wish->title,
                'category' => $order->wish->category?->only(['id', 'name', 'icon']),
            ],
            'provider' => $order->providerOrganization?->only(['id', 'name']),
            'milestones' => DB::table('fund_order_milestones')->where('fund_order_id', $order->id)->orderBy('ordinal')->get(),
            'proofs' => DB::table('fund_order_proofs')->where('fund_order_id', $order->id)->orderByDesc('submitted_at')->get(),
            'disputes' => DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->orderByDesc('opened_at')->get(),
        ];
    }
}
