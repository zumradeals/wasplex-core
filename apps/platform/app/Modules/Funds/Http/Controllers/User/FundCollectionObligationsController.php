<?php

declare(strict_types=1);

namespace App\Modules\Funds\Http\Controllers\User;

use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundCollectionParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FundCollectionObligationsController
{
    public function index(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;

        $obligations = FundCollectionParticipant::query()
            ->with(['snapshot.wish.category:id,name,icon', 'arrear'])
            ->where('account_id', $accountId)
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (FundCollectionParticipant $participant): array {
                $snapshot = $participant->snapshot;
                $wish = $snapshot?->wish;

                return [
                    'id' => $participant->id,
                    'reference' => $wish === null ? null : 'FONDS-'.strtoupper(substr((string) $wish->id, -6)),
                    'category' => $wish?->category?->only(['name', 'icon']),
                    'collection_status' => $snapshot?->status,
                    'participant_status' => $participant->status,
                    'solidarity_due_minor' => (int) $participant->solidarity_due_minor,
                    'fee_due_minor' => (int) $participant->fee_due_minor,
                    'solidarity_paid_minor' => (int) $participant->solidarity_paid_minor,
                    'fee_paid_minor' => (int) $participant->fee_paid_minor,
                    'arrears_minor' => (int) $participant->arrears_minor,
                    'notice_at' => $snapshot?->notice_at,
                    'scheduled_at' => $snapshot?->scheduled_at,
                    'last_attempted_at' => $participant->last_attempted_at,
                    'arrear_grace_ends_at' => $participant->arrear?->grace_ends_at,
                    'currency' => 'WP',
                ];
            });

        $openArrears = (int) FundArrear::query()
            ->where('account_id', $accountId)
            ->where('status', FundArrear::STATUS_OPEN)
            ->selectRaw('COALESCE(SUM(due_solidarity_minor - settled_solidarity_minor), 0) AS total')
            ->value('total');

        return response()->json([
            'obligations' => $obligations,
            'summary' => [
                'open_arrears_minor' => $openArrears,
                'pending_count' => $obligations->whereIn('participant_status', ['pending', 'partial', 'arrears'])->count(),
            ],
        ]);
    }
}
