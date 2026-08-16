<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Live\Application\Services\LiveRealtimeService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveStageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LiveRealtimeController
{
    public function __construct(private readonly LiveRealtimeService $realtime) {}

    public function viewerCredentials(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'media' => $this->realtime->viewerCredentials(
                $live,
                $request->user(),
                $this->connectionId($request),
            ),
        ]);
    }

    public function requestStage(Request $request, LiveEvent $live): JsonResponse
    {
        $stageRequest = $this->realtime->requestStage(
            $live,
            $request->user(),
            $this->connectionId($request),
        );

        return response()->json(['stage_request' => $this->presentStageRequest($stageRequest)], 201);
    }

    public function leaveStage(Request $request, LiveEvent $live): JsonResponse
    {
        $this->realtime->leaveStage($live, $request->user());

        return response()->json(['left_stage' => true]);
    }

    public function withdrawStage(Request $request, LiveEvent $live): JsonResponse
    {
        $this->realtime->withdrawStage($live, $request->user());

        return response()->json(['withdrawn' => true]);
    }

    public function hostCredentials(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'media' => $this->realtime->hostCredentials(
                $live,
                $request->user(),
                $this->advertiserOrganizationId($request),
                $this->connectionId($request),
            ),
        ]);
    }

    public function stageRequests(Request $request, LiveEvent $live): JsonResponse
    {
        $requests = $this->realtime->requestsForHost(
            $live,
            $request->user(),
            $this->advertiserOrganizationId($request),
        )->map(fn (LiveStageRequest $stageRequest): array => $this->presentStageRequest($stageRequest));

        return response()->json(['stage_requests' => $requests->values()]);
    }

    public function approve(
        Request $request,
        LiveEvent $live,
        LiveStageRequest $stageRequest,
    ): JsonResponse {
        $stageRequest = $this->realtime->approve(
            $live,
            $stageRequest,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['stage_request' => $this->presentStageRequest($stageRequest)]);
    }

    public function reject(
        Request $request,
        LiveEvent $live,
        LiveStageRequest $stageRequest,
    ): JsonResponse {
        $stageRequest = $this->realtime->reject(
            $live,
            $stageRequest,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['stage_request' => $this->presentStageRequest($stageRequest)]);
    }

    public function lower(
        Request $request,
        LiveEvent $live,
        LiveStageRequest $stageRequest,
    ): JsonResponse {
        $stageRequest = $this->realtime->lower(
            $live,
            $stageRequest,
            $request->user(),
            $this->advertiserOrganizationId($request),
        );

        return response()->json(['stage_request' => $this->presentStageRequest($stageRequest)]);
    }

    private function advertiserOrganizationId(Request $request): string
    {
        return (string) $request->attributes->get('advertiser_organization_id');
    }

    private function connectionId(Request $request): string
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'uuid'],
        ]);

        return (string) $validated['connection_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentStageRequest(LiveStageRequest $stageRequest): array
    {
        $stageRequest->loadMissing('account.profile');

        return [
            'id' => $stageRequest->id,
            'status' => $stageRequest->status,
            'requested_at' => $stageRequest->requested_at?->toIso8601String(),
            'resolved_at' => $stageRequest->resolved_at?->toIso8601String(),
            'participant' => [
                'account_id' => $stageRequest->account_id,
                'display_name' => $stageRequest->account->profile?->resolvedDisplayName() ?? 'Membre Wasplex',
            ],
        ];
    }
}
