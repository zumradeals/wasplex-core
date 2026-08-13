<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Funds\Application\Services\FundPilotageService;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundRehabilitationCase;
use App\Modules\Funds\Infrastructure\Models\FundReserveAllocation;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishQueueEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminFundPilotageController extends Controller
{
    public function __construct(private readonly FundPilotageService $pilotage) {}

    public function index(): JsonResponse
    {
        $queueCandidates = FundWish::query()
            ->with(['category:id,name,icon', 'membership.program:id,name,code'])
            ->where('status', FundWish::STATUS_APPROVED)
            ->whereNotNull('selected_quote_id')
            ->whereNotNull('provider_organization_id')
            ->whereNotNull('validated_amount_minor')
            ->whereDoesntHave('collectionSnapshot')
            ->whereDoesntHave('queueEntry')
            ->oldest('reviewed_at')
            ->limit(100)
            ->get();

        $queue = FundWishQueueEntry::query()
            ->with(['wish:id,title,account_id,status', 'program:id,name,code'])
            ->orderByRaw("CASE WHEN status = 'queued' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END")
            ->orderBy('queued_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $allocations = FundReserveAllocation::query()
            ->with(['wish:id,title', 'order:id,fund_wish_id,status'])
            ->latest('authorized_at')
            ->limit(100)
            ->get();

        $rehabilitations = FundRehabilitationCase::query()
            ->with(['membership.program:id,name,code', 'account.identifiers'])
            ->whereIn('status', [FundRehabilitationCase::STATUS_REQUIRED, FundRehabilitationCase::STATUS_IN_PROGRESS])
            ->latest('opened_at')
            ->limit(100)
            ->get()
            ->map(fn (FundRehabilitationCase $case): array => [
                'id' => $case->id,
                'status' => $case->status,
                'incident_count' => (int) $case->incident_count,
                'account_label' => $case->account?->primaryIdentifierLabel() ?? 'Membre',
                'program_name' => $case->membership?->program?->name,
                'opened_at' => $case->opened_at,
            ]);

        $deficits = FundCollectionSnapshot::query()
            ->with('wish:id,title')
            ->whereIn('status', [FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED, FundCollectionSnapshot::STATUS_COLLECTING])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (FundCollectionSnapshot $snapshot): array => [
                'snapshot' => $snapshot,
                'plan' => $this->pilotage->deficitPlan($snapshot),
            ]);

        $transparency = $this->pilotage->transparency();

        return response()->json([
            'data' => [
                'queue_candidates' => $queueCandidates,
                'queue' => $queue,
                'reserve' => [
                    'balance_minor' => $transparency['reserve_balance_minor'],
                    'available_minor' => $this->pilotage->availableReserveMinor(),
                    'allocations' => $allocations,
                ],
                'rehabilitations' => $rehabilitations,
                'deficits' => $deficits,
                'transparency' => $transparency,
            ],
        ]);
    }

    public function queue(Request $request, FundWish $wish): JsonResponse
    {
        $data = $request->validate([
            'lane' => ['required', 'in:ordinary,emergency'],
            'emergency_verified' => ['sometimes', 'boolean'],
        ]);

        $entry = $this->pilotage->queueWish(
            $wish,
            (string) $data['lane'],
            (string) $request->user()->id,
            (bool) ($data['emergency_verified'] ?? false),
        );

        return response()->json(['data' => $entry], 201);
    }

    public function releaseNext(Request $request, FundProgram $program): JsonResponse
    {
        $snapshot = $this->pilotage->releaseNext((string) $program->id, (string) $request->user()->id);

        return response()->json(['data' => $snapshot->load('participants')]);
    }

    public function reserve(Request $request, FundWish $wish): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:190'],
            'justification' => ['nullable', 'string', 'max:5000'],
        ]);

        $allocation = $this->pilotage->authorizeReserve(
            $wish,
            (int) $data['amount_minor'],
            (string) $data['reason'],
            isset($data['justification']) ? (string) $data['justification'] : null,
            (string) $request->user()->id,
        );

        return response()->json(['data' => $allocation], 201);
    }

    public function releaseReserve(FundReserveAllocation $allocation): JsonResponse
    {
        return response()->json(['data' => $this->pilotage->releaseReserveAllocation($allocation, (string) request()->user()->id)]);
    }

    public function detectRehabilitations(): JsonResponse
    {
        return response()->json(['data' => ['created' => $this->pilotage->detectRehabilitationCases((string) request()->user()->id)]]);
    }

    public function completeRehabilitation(Request $request, FundRehabilitationCase $case): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);
        $case = $this->pilotage->completeRehabilitation($case, (string) $request->user()->id, (string) $data['note']);

        return response()->json(['data' => $case]);
    }
}
