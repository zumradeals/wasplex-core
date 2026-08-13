<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Funds\Application\Services\FundPilotageService;
use App\Modules\Funds\Infrastructure\Models\FundRehabilitationCase;
use App\Modules\Funds\Infrastructure\Models\FundWishQueueEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FundPilotageController extends Controller
{
    public function __construct(private readonly FundPilotageService $pilotage) {}

    public function index(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $profile = $this->pilotage->refreshReciprocity($accountId);

        $queue = FundWishQueueEntry::query()
            ->with(['wish:id,title,account_id,status'])
            ->whereHas('wish', fn ($query) => $query->where('account_id', $accountId))
            ->orderByDesc('queued_at')
            ->get()
            ->map(fn (FundWishQueueEntry $entry): array => [
                'id' => $entry->id,
                'wish_id' => $entry->fund_wish_id,
                'wish_title' => $entry->wish?->title,
                'lane' => $entry->lane,
                'status' => $entry->status,
                'queued_at' => $entry->queued_at,
                'released_at' => $entry->released_at,
                'blocked_reason' => $entry->blocked_reason,
            ]);

        $rehabilitation = FundRehabilitationCase::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [FundRehabilitationCase::STATUS_REQUIRED, FundRehabilitationCase::STATUS_IN_PROGRESS])
            ->latest('opened_at')
            ->first();

        return response()->json([
            'data' => [
                'reciprocity' => [
                    'score' => (int) $profile->score,
                    'level' => $profile->level,
                    'factors' => $profile->factors,
                    'calculated_at' => $profile->calculated_at,
                ],
                'queue' => $queue,
                'rehabilitation' => $rehabilitation === null ? null : [
                    'id' => $rehabilitation->id,
                    'status' => $rehabilitation->status,
                    'incident_count' => (int) $rehabilitation->incident_count,
                    'required_actions' => $rehabilitation->required_actions,
                    'opened_at' => $rehabilitation->opened_at,
                ],
                'transparency' => $this->pilotage->transparency(),
            ],
        ]);
    }

    public function startRehabilitation(Request $request, FundRehabilitationCase $case): JsonResponse
    {
        $case = $this->pilotage->startRehabilitation($case, (string) $request->user()->id);

        return response()->json(['data' => $case]);
    }
}
