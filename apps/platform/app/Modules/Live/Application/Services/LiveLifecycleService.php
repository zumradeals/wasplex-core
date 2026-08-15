<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveStageRequest;
use App\Modules\Live\Infrastructure\Models\LiveStreamSession;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use App\Shared\Http\AppException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class LiveLifecycleService
{
    public function __construct(private readonly LiveKitService $liveKit) {}

    public function create(Account $owner, string $advertiserOrganizationId, array $attributes): LiveEvent
    {
        return DB::transaction(function () use ($owner, $advertiserOrganizationId, $attributes): LiveEvent {
            $scheduledAt = $attributes['scheduled_at'] ?? null;
            $live = LiveEvent::query()->create([
                'owner_account_id' => $owner->id,
                'advertiser_organization_id' => $advertiserOrganizationId,
                'title' => trim((string) $attributes['title']),
                'description' => $this->nullableText($attributes['description'] ?? null),
                'category' => $attributes['category'] ?? 'general',
                'language' => $attributes['language'] ?? ($owner->language ?: 'fr'),
                'visibility' => $attributes['visibility'] ?? 'public',
                'status' => $scheduledAt !== null ? LiveEvent::STATUS_SCHEDULED : LiveEvent::STATUS_DRAFT,
                'scheduled_at' => $scheduledAt,
                'planned_duration_minutes' => $attributes['planned_duration_minutes'] ?? null,
                'replay_policy' => $attributes['replay_policy'] ?? 'disabled',
            ]);

            $this->audit($live, $owner->id, 'LiveCreated', [
                'advertiser_organization_id' => $advertiserOrganizationId,
                'status' => $live->status,
                'scheduled_at' => $live->scheduled_at?->toIso8601String(),
            ]);

            if ($live->status === LiveEvent::STATUS_SCHEDULED) {
                $this->audit($live, $owner->id, 'LiveScheduled', [
                    'scheduled_at' => $live->scheduled_at?->toIso8601String(),
                ]);
            }

            return $live->refresh();
        });
    }

    public function update(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
        array $attributes,
    ): LiveEvent {
        $this->assertAdvertiserOwner($live, $actor, $advertiserOrganizationId);
        if (! in_array($live->status, [LiveEvent::STATUS_DRAFT, LiveEvent::STATUS_SCHEDULED], true)) {
            throw new AppException('LIVE_NOT_EDITABLE', 'Ce Live ne peut plus être modifié dans son état actuel.', [], 409);
        }

        return DB::transaction(function () use ($live, $actor, $attributes): LiveEvent {
            foreach (['title', 'category', 'language', 'visibility', 'planned_duration_minutes', 'replay_policy'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $live->{$field} = $attributes[$field];
                }
            }
            if (array_key_exists('description', $attributes)) {
                $live->description = $this->nullableText($attributes['description']);
            }
            if (array_key_exists('scheduled_at', $attributes)) {
                $live->scheduled_at = $attributes['scheduled_at'];
                $live->status = $attributes['scheduled_at'] === null
                    ? LiveEvent::STATUS_DRAFT
                    : LiveEvent::STATUS_SCHEDULED;
            }
            $live->save();

            $this->audit($live, $actor->id, 'LiveUpdated');

            return $live->refresh();
        });
    }

    public function schedule(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
        CarbonInterface $scheduledAt,
    ): LiveEvent {
        $this->assertAdvertiserOwner($live, $actor, $advertiserOrganizationId);
        if (! in_array($live->status, [LiveEvent::STATUS_DRAFT, LiveEvent::STATUS_SCHEDULED], true)) {
            throw new AppException('LIVE_NOT_SCHEDULABLE', 'Ce Live ne peut pas être reprogrammé dans son état actuel.', [], 409);
        }

        $live->update([
            'scheduled_at' => $scheduledAt,
            'status' => LiveEvent::STATUS_SCHEDULED,
        ]);
        $this->audit($live, $actor->id, 'LiveScheduled', ['scheduled_at' => $scheduledAt->toIso8601String()]);

        return $live->refresh();
    }

    public function start(LiveEvent $live, Account $actor, string $advertiserOrganizationId): LiveEvent
    {
        $this->assertAdvertiserOwner($live, $actor, $advertiserOrganizationId);
        if (! in_array($live->status, [LiveEvent::STATUS_DRAFT, LiveEvent::STATUS_SCHEDULED], true)) {
            throw new AppException('LIVE_NOT_STARTABLE', 'Ce Live ne peut pas démarrer dans son état actuel.', [], 409);
        }
        $this->liveKit->ensureConfigured();

        return DB::transaction(function () use ($live, $actor): LiveEvent {
            $live->update([
                'status' => LiveEvent::STATUS_LIVE,
                'started_at' => $live->started_at ?? now(),
                'ended_at' => null,
            ]);

            LiveStreamSession::query()->create([
                'live_id' => $live->id,
                'status' => LiveStreamSession::STATUS_ACTIVE,
                'provider' => 'livekit',
                'provider_session_reference' => 'wasplex-live-'.$live->id,
                'started_at' => now(),
            ]);

            $this->audit($live, $actor->id, 'LiveStarted', [
                'transport' => 'livekit',
                'room' => 'wasplex-live-'.$live->id,
            ]);

            return $live->refresh();
        });
    }

    public function pause(LiveEvent $live, Account $actor, string $advertiserOrganizationId): LiveEvent
    {
        $this->assertAdvertiserOwner($live, $actor, $advertiserOrganizationId);
        if ($live->status !== LiveEvent::STATUS_LIVE) {
            throw new AppException('LIVE_NOT_PAUSABLE', 'Seul un Live en cours peut être mis en pause.', [], 409);
        }

        return DB::transaction(function () use ($live, $actor): LiveEvent {
            $live->update(['status' => LiveEvent::STATUS_PAUSED]);
            $live->streamSessions()->where('status', LiveStreamSession::STATUS_ACTIVE)->update([
                'status' => LiveStreamSession::STATUS_PAUSED,
                'paused_at' => now(),
            ]);
            $live->viewerSessions()->where('status', LiveViewerSession::STATUS_WATCHING)->update([
                'status' => LiveViewerSession::STATUS_PAUSED,
                'last_seen_at' => now(),
            ]);
            $this->audit($live, $actor->id, 'LivePaused');

            return $live->refresh();
        });
    }

    public function resume(LiveEvent $live, Account $actor, string $advertiserOrganizationId): LiveEvent
    {
        $this->assertAdvertiserOwner($live, $actor, $advertiserOrganizationId);
        if ($live->status !== LiveEvent::STATUS_PAUSED) {
            throw new AppException('LIVE_NOT_RESUMABLE', 'Seul un Live en pause peut reprendre.', [], 409);
        }

        return DB::transaction(function () use ($live, $actor): LiveEvent {
            $live->update(['status' => LiveEvent::STATUS_LIVE]);
            $live->streamSessions()->where('status', LiveStreamSession::STATUS_PAUSED)->update([
                'status' => LiveStreamSession::STATUS_ACTIVE,
                'paused_at' => null,
            ]);
            $live->viewerSessions()->where('status', LiveViewerSession::STATUS_PAUSED)->update([
                'status' => LiveViewerSession::STATUS_WATCHING,
                'last_seen_at' => now(),
            ]);
            $this->audit($live, $actor->id, 'LiveResumed');

            return $live->refresh();
        });
    }

    public function end(LiveEvent $live, Account $actor, string $advertiserOrganizationId): LiveEvent
    {
        $this->assertAdvertiserOwner($live, $actor, $advertiserOrganizationId);
        if (! in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            throw new AppException('LIVE_NOT_ENDABLE', 'Ce Live n’est pas en cours.', [], 409);
        }

        return DB::transaction(function () use ($live, $actor): LiveEvent {
            $endedAt = now();
            $live->update([
                'status' => LiveEvent::STATUS_ENDED,
                'ended_at' => $endedAt,
            ]);
            $live->streamSessions()->whereIn('status', [
                LiveStreamSession::STATUS_ACTIVE,
                LiveStreamSession::STATUS_PAUSED,
            ])->update([
                'status' => LiveStreamSession::STATUS_ENDED,
                'ended_at' => $endedAt,
            ]);
            $live->viewerSessions()->whereIn('status', [
                LiveViewerSession::STATUS_WATCHING,
                LiveViewerSession::STATUS_PAUSED,
            ])->update([
                'status' => LiveViewerSession::STATUS_COMPLETED,
                'last_seen_at' => $endedAt,
                'left_at' => $endedAt,
            ]);
            $live->stageRequests()->where('status', LiveStageRequest::STATUS_PENDING)->update([
                'status' => LiveStageRequest::STATUS_REJECTED,
                'resolved_at' => $endedAt,
                'resolved_by_account_id' => $actor->id,
            ]);
            $live->stageRequests()->where('status', LiveStageRequest::STATUS_APPROVED)->update([
                'status' => LiveStageRequest::STATUS_LOWERED,
                'resolved_at' => $endedAt,
                'resolved_by_account_id' => $actor->id,
            ]);
            $this->audit($live, $actor->id, 'LiveEnded');

            return $live->refresh();
        });
    }

    public function join(LiveEvent $live, Account $viewer): LiveViewerSession
    {
        if ($live->advertiser_organization_id === null) {
            throw new AppException('LIVE_NOT_JOINABLE', 'Ce Live n’est pas publié par un annonceur Wasplex.', [], 409);
        }
        if (! in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            throw new AppException('LIVE_NOT_JOINABLE', 'Ce Live n’est pas ouvert aux spectateurs.', [], 409);
        }
        if (! in_array($live->visibility, ['public', 'unlisted'], true)) {
            throw new AppException('LIVE_ACCESS_DENIED', 'Vous ne pouvez pas rejoindre ce Live.', [], 403);
        }

        return DB::transaction(function () use ($live, $viewer): LiveViewerSession {
            $status = $live->status === LiveEvent::STATUS_PAUSED
                ? LiveViewerSession::STATUS_PAUSED
                : LiveViewerSession::STATUS_WATCHING;

            $session = LiveViewerSession::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($session !== null) {
                $session->update(['status' => $status, 'last_seen_at' => now()]);

                return $session->refresh();
            }

            $session = LiveViewerSession::query()->create([
                'live_id' => $live->id,
                'account_id' => $viewer->id,
                'status' => $status,
                'joined_at' => now(),
                'last_seen_at' => now(),
            ]);
            $this->audit($live, $viewer->id, 'LiveViewerJoined');

            return $session;
        });
    }

    public function leave(LiveEvent $live, Account $viewer): void
    {
        DB::transaction(function () use ($live, $viewer): void {
            $session = LiveViewerSession::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return;
            }

            $now = now();
            $session->update([
                'status' => LiveViewerSession::STATUS_LEFT,
                'last_seen_at' => $now,
                'left_at' => $now,
            ]);
            $live->stageRequests()
                ->where('account_id', $viewer->id)
                ->where('status', LiveStageRequest::STATUS_PENDING)
                ->update(['status' => LiveStageRequest::STATUS_WITHDRAWN, 'resolved_at' => $now]);
            $live->stageRequests()
                ->where('account_id', $viewer->id)
                ->where('status', LiveStageRequest::STATUS_APPROVED)
                ->update([
                    'status' => LiveStageRequest::STATUS_LOWERED,
                    'resolved_at' => $now,
                    'resolved_by_account_id' => $viewer->id,
                ]);
            $this->audit($live, $viewer->id, 'LiveViewerLeft');
        });
    }

    private function assertAdvertiserOwner(
        LiveEvent $live,
        Account $actor,
        string $advertiserOrganizationId,
    ): void {
        if ((string) $live->advertiser_organization_id !== $advertiserOrganizationId) {
            throw new AppException(
                'LIVE_ADVERTISER_CONTEXT_MISMATCH',
                'Ce Live n’appartient pas à l’espace annonceur actif.',
                [],
                403,
            );
        }

        if ($live->owner_account_id !== $actor->id) {
            throw new AppException('LIVE_OWNER_REQUIRED', 'Seul le créateur de ce Live peut effectuer cette action.', [], 403);
        }
    }

    private function audit(LiveEvent $live, ?string $actorAccountId, string $eventType, array $metadata = []): void
    {
        LiveAuditEvent::query()->create([
            'live_id' => $live->id,
            'actor_account_id' => $actorAccountId,
            'event_type' => $eventType,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
