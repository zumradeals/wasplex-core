<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers\Admin;

use App\Modules\Live\Application\Services\LiveRewardHoldService;
use App\Modules\Live\Application\Services\LiveRewardOperationsService;
use App\Modules\Live\Infrastructure\Models\LiveRewardHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LiveRewardOperationsController
{
    public function __construct(
        private readonly LiveRewardOperationsService $operations,
        private readonly LiveRewardHoldService $holds,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['dashboard' => $this->operations->dashboard()]);
    }

    public function holds(): JsonResponse
    {
        return response()->json([
            'holds' => $this->holds->listQueue()->map(fn (LiveRewardHold $hold): array => $this->format($hold))->values(),
        ]);
    }

    public function release(Request $request, string $hold): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $resolved = $this->holds->release($hold, (string) $request->user()->id, $data['note'] ?? null);

        return response()->json(['hold' => $this->format($resolved)]);
    }

    public function reject(Request $request, string $hold): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $resolved = $this->holds->reject($hold, (string) $request->user()->id, $data['note'] ?? null);

        return response()->json(['hold' => $this->format($resolved)]);
    }

    private function format(LiveRewardHold $hold): array
    {
        return [
            'id' => $hold->id,
            'live_id' => $hold->live_id,
            'live_title' => $hold->live?->title,
            'account_id' => $hold->account_id,
            'block_index' => $hold->block_index,
            'reward_minor' => $hold->reward_minor,
            'gross_amount_minor' => $hold->gross_amount_minor,
            'status' => $hold->status,
            'risk_codes' => $hold->risk_codes ?? [],
            'evidence' => $hold->evidence ?? [],
            'opened_at' => $hold->opened_at,
            'reviewed_at' => $hold->reviewed_at,
            'resolution_note' => $hold->resolution_note,
        ];
    }
}
