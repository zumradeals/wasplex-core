<?php

declare(strict_types=1);

namespace App\Modules\Feed\Http\Controllers\Admin;

use App\Modules\Feed\Application\Services\FeedRiskReviewService;
use App\Modules\Feed\Infrastructure\Models\FeedAdDeliveryHold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FeedRiskReviewController extends Controller
{
    public function __construct(private readonly FeedRiskReviewService $review) {}

    public function index(): JsonResponse
    {
        $holds = $this->review->listQueue()->map(fn (FeedAdDeliveryHold $hold): array => $this->format($hold));

        return response()->json(['holds' => $holds]);
    }

    public function release(Request $request, string $hold): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $resolved = $this->review->release($hold, (string) $request->user()->id, $data['note'] ?? null);

        return response()->json(['hold' => $this->format($resolved)]);
    }

    public function reject(Request $request, string $hold): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $resolved = $this->review->reject($hold, (string) $request->user()->id, $data['note'] ?? null);

        return response()->json(['hold' => $this->format($resolved)]);
    }

    private function format(FeedAdDeliveryHold $hold): array
    {
        return [
            'id' => $hold->id,
            'feed_ad_delivery_id' => $hold->feed_ad_delivery_id,
            'account_id' => $hold->delivery?->account_id,
            'campaign_id' => $hold->delivery?->campaign_id,
            'amount_minor' => $hold->amount_minor,
            'reason_code' => $hold->reason_code,
            'status' => $hold->status,
            'opened_at' => $hold->opened_at,
            'resolved_at' => $hold->resolved_at,
            'resolution_note' => $hold->resolution_note,
            'evidence' => $hold->evidence,
        ];
    }
}
