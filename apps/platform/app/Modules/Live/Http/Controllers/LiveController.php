<?php

declare(strict_types=1);

namespace App\Modules\Live\Http\Controllers;

use App\Modules\Live\Application\Services\LiveLifecycleService;
use App\Modules\Live\Application\Services\LivePresenter;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LiveController
{
    public function __construct(private readonly LiveLifecycleService $lifecycle) {}

    public function index(Request $request): JsonResponse
    {
        $accountId = (string) $request->user()->id;
        $lives = LiveEvent::query()
            ->with('owner.profile')
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
        $accountId = (string) $request->user()->id;
        if ($live->status === LiveEvent::STATUS_DRAFT && $live->owner_account_id !== $accountId) {
            throw new NotFoundHttpException('Live Wasplex introuvable.');
        }

        return response()->json(['live' => LivePresenter::live($live, $accountId)]);
    }

    public function join(Request $request, LiveEvent $live): JsonResponse
    {
        $session = $this->lifecycle->join($live, $request->user());

        return response()->json([
            'live' => LivePresenter::live($live->refresh(), (string) $request->user()->id),
            'viewer_session' => [
                'id' => $session->id,
                'status' => $session->status,
                'joined_at' => $session->joined_at?->toIso8601String(),
            ],
        ]);
    }

    public function leave(Request $request, LiveEvent $live): JsonResponse
    {
        $this->lifecycle->leave($live, $request->user());

        return response()->json(['left' => true]);
    }
}
