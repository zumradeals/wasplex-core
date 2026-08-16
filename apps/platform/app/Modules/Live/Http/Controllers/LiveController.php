<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Live\Application\Services\LiveInteractionService;
use App\Modules\Live\Application\Services\LiveLifecycleService;
use App\Modules\Live\Application\Services\LivePresenter;
use App\Modules\Live\Application\Services\LiveRewardAttentionService;
use App\Modules\Live\Application\Services\LiveRewardSeatService;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LiveController
{
    public function __construct(
        private readonly LiveLifecycleService $lifecycle,
        private readonly LiveInteractionService $interactions,
        private readonly LiveRewardSeatService $rewardSeats,
        private readonly LiveRewardAttentionService $rewardAttention,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $lives = LiveEvent::query()
            ->with(['owner.profile', 'advertiserOrganization'])
            ->whereNotNull('advertiser_organization_id')
            ->where('visibility', 'public')
            ->whereIn('status', [LiveEvent::STATUS_SCHEDULED, LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED])
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 WHEN status = 'paused' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->map(fn (LiveEvent $live): array => LivePresenter::live($live, $accountId))
            ->values();

        return response()->json(['lives' => $lives]);
    }

    public function show(Request $request, LiveEvent $live): JsonResponse
    {
        if ($live->advertiser_organization_id === null || $live->status === LiveEvent::STATUS_DRAFT) {
            throw new NotFoundHttpException('Live Wasplex introuvable.');
        }

        return response()->json(['live' => LivePresenter::live($live, (string) $request->user()->id)]);
    }

    public function rewardSeat(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'reward_seat' => $this->rewardSeats->stateForAccount($live, $request->user()),
            'reward_attention' => $this->rewardAttention->stateForAccount($live, $request->user()),
        ]);
    }

    public function rewardAttention(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'reward_attention' => $this->rewardAttention->stateForAccount($live, $request->user()),
        ]);
    }

    public function rewardAttentionHeartbeat(Request $request, LiveEvent $live): JsonResponse
    {
        $data = $request->validate([
            'visible' => ['required', 'boolean'],
            'media_connected' => ['required', 'boolean'],
        ]);

        return response()->json([
            'reward_attention' => $this->rewardAttention->heartbeat(
                $live,
                $request->user(),
                (bool) $data['visible'],
                (bool) $data['media_connected'],
            ),
        ]);
    }

    public function join(Request $request, LiveEvent $live): JsonResponse
    {
        $session = $this->lifecycle->join($live, $request->user());
        $rewardSeat = $this->rewardSeats->admit($live, $request->user(), $session);
        $live->refresh();
        $this->interactions->broadcastViewerCount($live);

        return response()->json([
            'live' => LivePresenter::live($live, (string) $request->user()->id),
            'viewer_session' => [
                'id' => $session->id,
                'status' => $session->status,
                'joined_at' => $session->joined_at?->toIso8601String(),
            ],
            'reward_seat' => $rewardSeat,
            'reward_attention' => $this->rewardAttention->stateForAccount($live, $request->user()),
        ]);
    }

    public function leave(Request $request, LiveEvent $live): JsonResponse
    {
        $this->rewardAttention->interrupt($live, $request->user(), 'viewer_left');
        $this->rewardSeats->leaveWaitlist($live, $request->user());
        $this->rewardSeats->releaseForViewer($live, $request->user());
        $this->lifecycle->leave($live, $request->user());
        $this->interactions->broadcastViewerCount($live);

        return response()->json(['left' => true]);
    }

    public function joinRewardWaitlist(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'reward_seat' => $this->rewardSeats->joinWaitlist($live, $request->user()),
        ]);
    }

    public function leaveRewardWaitlist(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'reward_seat' => $this->rewardSeats->leaveWaitlist($live, $request->user()),
        ]);
    }

    public function acceptRewardSeat(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'reward_seat' => $this->rewardSeats->acceptOffer($live, $request->user()),
        ]);
    }

    public function declineRewardSeat(Request $request, LiveEvent $live): JsonResponse
    {
        return response()->json([
            'reward_seat' => $this->rewardSeats->declineOffer($live, $request->user()),
        ]);
    }
}
