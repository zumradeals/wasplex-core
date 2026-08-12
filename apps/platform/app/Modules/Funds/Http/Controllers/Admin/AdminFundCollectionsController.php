<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\Admin;

use App\Modules\Funds\Application\Services\FundCollectionService;
use App\Modules\Funds\Application\Services\FundContributionService;
use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundAuditEvent;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AdminFundCollectionsController
{
    public function __construct(
        private readonly FundCollectionService $collections,
        private readonly FundContributionService $contributions,
    ) {}

    public function index(): JsonResponse
    {
        $snapshots = FundCollectionSnapshot::query()
            ->with([
                'wish.category:id,name,icon',
                'wish.providerOrganization:id,name',
                'program:id,name,code',
                'participants.arrear',
            ])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (FundCollectionSnapshot $snapshot): array => $this->snapshotPayload($snapshot));

        $readyWishes = FundWish::query()
            ->with(['category:id,name,icon', 'providerOrganization:id,name', 'membership.program:id,name,code'])
            ->where('status', FundWish::STATUS_APPROVED)
            ->whereNotNull('validated_amount_minor')
            ->whereNotNull('provider_organization_id')
            ->whereDoesntHave('collectionSnapshot')
            ->latest('reviewed_at')
            ->limit(100)
            ->get()
            ->map(function (FundWish $wish): array {
                $contributed = $this->contributions->wishContributionMinor($wish);
                $required = (int) ($wish->required_personal_contribution_minor ?? 0);

                return [
                    'id' => $wish->id,
                    'reference' => 'FONDS-'.strtoupper(substr((string) $wish->id, -6)),
                    'title' => $wish->title,
                    'country_code' => $wish->country_code,
                    'currency' => $wish->currency,
                    'validated_amount_minor' => (int) $wish->validated_amount_minor,
                    'required_personal_contribution_minor' => $required,
                    'personal_contribution_minor' => $contributed,
                    'personal_contribution_complete' => $required <= 0 || $contributed >= $required,
                    'category' => $wish->category?->only(['id', 'name', 'icon']),
                    'provider' => $wish->providerOrganization?->only(['id', 'name']),
                    'program' => $wish->membership?->program?->only(['id', 'name', 'code']),
                ];
            });

        return response()->json([
            'ready_wishes' => $readyWishes,
            'snapshots' => $snapshots,
            'metrics' => [
                'scheduled' => FundCollectionSnapshot::query()->where('status', FundCollectionSnapshot::STATUS_SCHEDULED)->count(),
                'collecting' => FundCollectionSnapshot::query()->whereIn('status', [FundCollectionSnapshot::STATUS_COLLECTING, FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED])->count(),
                'funded' => FundCollectionSnapshot::query()->where('status', FundCollectionSnapshot::STATUS_FUNDED)->count(),
                'open_arrears_minor' => (int) FundArrear::query()->where('status', FundArrear::STATUS_OPEN)
                    ->selectRaw('COALESCE(SUM(due_solidarity_minor - settled_solidarity_minor), 0) AS total')
                    ->value('total'),
            ],
        ]);
    }

    public function create(Request $request, FundWish $wish): JsonResponse
    {
        try {
            $snapshot = $this->collections->createSnapshot($wish, (string) $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        FundAuditEvent::record((string) $request->user()->id, 'FundCollectionSnapshotCreated', 'fund_collection_snapshot', $snapshot->id, [
            'fund_wish_id' => $wish->id,
            'participant_count' => $snapshot->participant_count,
            'collective_amount_minor' => $snapshot->collective_amount_minor,
            'snapshot_hash' => $snapshot->snapshot_hash,
        ]);

        return response()->json($this->snapshotPayload($snapshot->load(['wish.category', 'wish.providerOrganization', 'program', 'participants.arrear'])), 201);
    }

    public function execute(Request $request, FundCollectionSnapshot $snapshot): JsonResponse
    {
        try {
            $snapshot = $this->collections->execute($snapshot, (string) $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        FundAuditEvent::record((string) $request->user()->id, 'FundCollectionExecuted', 'fund_collection_snapshot', $snapshot->id, [
            'status' => $snapshot->status,
            'solidarity_paid_minor' => (int) $snapshot->participants->sum('solidarity_paid_minor'),
        ]);

        return response()->json($this->snapshotPayload($snapshot->load(['wish.category', 'wish.providerOrganization', 'program', 'participants.arrear'])));
    }

    private function snapshotPayload(FundCollectionSnapshot $snapshot): array
    {
        $participants = $snapshot->participants;
        $paid = (int) $participants->sum('solidarity_paid_minor');
        $fees = (int) $participants->sum('fee_paid_minor');
        $arrears = (int) $participants->sum('arrears_minor');

        return [
            'id' => $snapshot->id,
            'status' => $snapshot->status,
            'snapshot_hash' => $snapshot->snapshot_hash,
            'rule_version' => $snapshot->rule_version,
            'country_code' => $snapshot->country_code,
            'currency' => $snapshot->currency,
            'validated_cost_minor' => (int) $snapshot->validated_cost_minor,
            'personal_contribution_minor' => (int) $snapshot->personal_contribution_minor,
            'collective_amount_minor' => (int) $snapshot->collective_amount_minor,
            'participant_count' => (int) $snapshot->participant_count,
            'solidarity_paid_minor' => $paid,
            'solidarity_remaining_minor' => max(0, (int) $snapshot->collective_amount_minor - $paid),
            'wasplex_fees_collected_minor' => $fees,
            'arrears_minor' => $arrears,
            'notice_at' => $snapshot->notice_at,
            'scheduled_at' => $snapshot->scheduled_at,
            'started_at' => $snapshot->started_at,
            'completed_at' => $snapshot->completed_at,
            'program' => $snapshot->program?->only(['id', 'name', 'code']),
            'wish' => $snapshot->wish === null ? null : [
                'id' => $snapshot->wish->id,
                'reference' => 'FONDS-'.strtoupper(substr((string) $snapshot->wish->id, -6)),
                'title' => $snapshot->wish->title,
                'category' => $snapshot->wish->category?->only(['id', 'name', 'icon']),
                'provider' => $snapshot->wish->providerOrganization?->only(['id', 'name']),
            ],
            'participants' => $participants->map(fn ($participant): array => [
                'id' => $participant->id,
                'account_id' => $participant->account_id,
                'status' => $participant->status,
                'solidarity_due_minor' => (int) $participant->solidarity_due_minor,
                'fee_due_minor' => (int) $participant->fee_due_minor,
                'solidarity_paid_minor' => (int) $participant->solidarity_paid_minor,
                'fee_paid_minor' => (int) $participant->fee_paid_minor,
                'arrears_minor' => (int) $participant->arrears_minor,
                'last_attempted_at' => $participant->last_attempted_at,
                'arrear_grace_ends_at' => $participant->arrear?->grace_ends_at,
            ])->values(),
        ];
    }
}
