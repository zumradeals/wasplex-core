<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;

final class LivePresenter
{
    public static function live(
        LiveEvent $live,
        ?string $currentAccountId = null,
        bool $includeSponsorshipManagement = false,
    ): array {
        $relations = ['owner.profile', 'advertiserOrganization'];
        if ($includeSponsorshipManagement && $live->type === LiveEvent::TYPE_SPONSORED) {
            $relations[] = 'rewardCampaign.quotes';
            $relations[] = 'rewardCampaign.budgetReservations';
        }
        $live->loadMissing($relations);

        $viewerCount = $live->viewerSessions()
            ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
            ->count();

        $stream = $live->streamSessions()->latest('created_at')->first();
        $publisherName = $live->advertiserOrganization?->name
            ?? $live->owner->profile?->resolvedDisplayName()
            ?? 'Annonceur Wasplex';
        $liveKitConfigured = trim((string) config('services.livekit.url')) !== ''
            && trim((string) config('services.livekit.api_key')) !== ''
            && (string) config('services.livekit.api_secret') !== '';

        return [
            'id' => $live->id,
            'type' => $live->type ?? LiveEvent::TYPE_STANDARD,
            'title' => $live->title,
            'description' => $live->description,
            'category' => $live->category,
            'language' => $live->language,
            'visibility' => $live->visibility,
            'status' => $live->status,
            'scheduled_at' => $live->scheduled_at?->toIso8601String(),
            'planned_duration_minutes' => $live->planned_duration_minutes,
            'started_at' => $live->started_at?->toIso8601String(),
            'ended_at' => $live->ended_at?->toIso8601String(),
            'replay_policy' => $live->replay_policy,
            'owner' => [
                'display_name' => $publisherName,
            ],
            'viewer_count' => $viewerCount,
            'is_owner' => $currentAccountId !== null && $live->owner_account_id === $currentAccountId,
            'can_join' => in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true),
            'stream' => [
                'status' => $stream?->status,
                'provider' => $stream?->provider,
                'room' => $stream?->provider_session_reference,
                'media_ready' => $liveKitConfigured && $stream?->provider === 'livekit',
            ],
            'reward_plan' => LiveRewardSeatPresenter::publicPlan($live),
            'sponsorship' => $includeSponsorshipManagement ? LiveSponsorshipPresenter::forLive($live) : null,
        ];
    }
}
