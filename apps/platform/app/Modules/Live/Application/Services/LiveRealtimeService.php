<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveStageRequest;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use App\Shared\Http\AppException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LiveRealtimeService
{
    public function __construct(private readonly LiveKitService $liveKit) {}

    /**
     * @return array{url: string, token: string, room: string, identity: string, role: string, can_publish: bool}
     */
    public function viewerCredentials(LiveEvent $live, Account $viewer): array
    {
        $this->assertViewerPresent($live, $viewer);
        $isSpeaker = LiveStageRequest::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->where('status', LiveStageRequest::STATUS_APPROVED)
            ->exists();

        return $this->liveKit->joinCredentials($live, $viewer, $isSpeaker, $isSpeaker ? 'speaker' : 'viewer');
    }

    /**
     * @return array{url: string, token: string, room: string, identity: string, role: string, can_publish: bool}
     */
    public function hostCredentials(LiveEvent $live, Account $host, string $organizationId): array
    {
        $this->assertHost($live, $host, $organizationId);

        return $this->liveKit->joinCredentials($live, $host, true, 'host');
    }

    public function requestStage(LiveEvent $live, Account $viewer): LiveStageRequest
    {
        if ($live->status !== LiveEvent::STATUS_LIVE) {
            throw new AppException('LIVE_STAGE_NOT_OPEN', 'Les demandes pour monter sont ouvertes uniquement pendant le direct.', [], 409);
        }

        if ($live->owner_account_id === $viewer->id) {
            throw new AppException('LIVE_STAGE_HOST_ALREADY_ON_STAGE', 'L’hôte est déjà intervenant dans ce Live.', [], 409);
        }

        $this->assertViewerPresent($live, $viewer);

        return DB::transaction(function () use ($live, $viewer): LiveStageRequest {
            $existing = LiveStageRequest::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->whereIn('status', [LiveStageRequest::STATUS_PENDING, LiveStageRequest::STATUS_APPROVED])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $stageRequest = LiveStageRequest::query()->create([
                'live_id' => $live->id,
                'account_id' => $viewer->id,
                'status' => LiveStageRequest::STATUS_PENDING,
                'requested_at' => now(),
            ]);

            $this->audit($live, $viewer->id, 'LiveStageRequested', ['stage_request_id' => $stageRequest->id]);

            return $stageRequest->refresh();
        });
    }

    public function leaveStage(LiveEvent $live, Account $viewer): void
    {
        $this->assertViewerPresent($live, $viewer);

        $stageRequest = LiveStageRequest::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->where('status', LiveStageRequest::STATUS_APPROVED)
            ->latest('created_at')
            ->first();

        if ($stageRequest === null) {
            return;
        }

        $this->liveKit->updateParticipantPublishing($live, (string) $viewer->id, false);
        $stageRequest->update([
            'status' => LiveStageRequest::STATUS_LOWERED,
            'resolved_at' => now(),
            'resolved_by_account_id' => $viewer->id,
        ]);
        $this->audit($live, $viewer->id, 'LiveStageLeft', ['stage_request_id' => $stageRequest->id]);
    }

    public function withdrawStage(LiveEvent $live, Account $viewer): void
    {
        DB::transaction(function () use ($live, $viewer): void {
            $stageRequest = LiveStageRequest::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->where('status', LiveStageRequest::STATUS_PENDING)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($stageRequest === null) {
                return;
            }

            $stageRequest->update([
                'status' => LiveStageRequest::STATUS_WITHDRAWN,
                'resolved_at' => now(),
            ]);
            $this->audit($live, $viewer->id, 'LiveStageWithdrawn', ['stage_request_id' => $stageRequest->id]);
        });
    }

    /**
     * @return Collection<int, LiveStageRequest>
     */
    public function requestsForHost(LiveEvent $live, Account $host, string $organizationId): Collection
    {
        $this->assertHost($live, $host, $organizationId);

        return LiveStageRequest::query()
            ->with('account.profile')
            ->where('live_id', $live->id)
            ->whereIn('status', [LiveStageRequest::STATUS_PENDING, LiveStageRequest::STATUS_APPROVED])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('requested_at')
            ->get();
    }

    public function approve(
        LiveEvent $live,
        LiveStageRequest $stageRequest,
        Account $host,
        string $organizationId,
    ): LiveStageRequest {
        $this->assertHost($live, $host, $organizationId);
        $this->assertStageRequestBelongsToLive($live, $stageRequest);

        if ($stageRequest->status !== LiveStageRequest::STATUS_PENDING) {
            throw new AppException('LIVE_STAGE_REQUEST_NOT_PENDING', 'Cette demande n’est plus en attente.', [], 409);
        }

        $this->liveKit->updateParticipantPublishing($live, (string) $stageRequest->account_id, true);

        $stageRequest->update([
            'status' => LiveStageRequest::STATUS_APPROVED,
            'resolved_at' => now(),
            'resolved_by_account_id' => $host->id,
        ]);
        $this->audit($live, $host->id, 'LiveStageApproved', [
            'stage_request_id' => $stageRequest->id,
            'participant_account_id' => $stageRequest->account_id,
        ]);

        return $stageRequest->refresh();
    }

    public function reject(
        LiveEvent $live,
        LiveStageRequest $stageRequest,
        Account $host,
        string $organizationId,
    ): LiveStageRequest {
        $this->assertHost($live, $host, $organizationId);
        $this->assertStageRequestBelongsToLive($live, $stageRequest);

        if ($stageRequest->status !== LiveStageRequest::STATUS_PENDING) {
            throw new AppException('LIVE_STAGE_REQUEST_NOT_PENDING', 'Cette demande n’est plus en attente.', [], 409);
        }

        $stageRequest->update([
            'status' => LiveStageRequest::STATUS_REJECTED,
            'resolved_at' => now(),
            'resolved_by_account_id' => $host->id,
        ]);
        $this->audit($live, $host->id, 'LiveStageRejected', ['stage_request_id' => $stageRequest->id]);

        return $stageRequest->refresh();
    }

    public function lower(
        LiveEvent $live,
        LiveStageRequest $stageRequest,
        Account $host,
        string $organizationId,
    ): LiveStageRequest {
        $this->assertHost($live, $host, $organizationId);
        $this->assertStageRequestBelongsToLive($live, $stageRequest);

        if ($stageRequest->status !== LiveStageRequest::STATUS_APPROVED) {
            throw new AppException('LIVE_STAGE_PARTICIPANT_NOT_ACTIVE', 'Cet intervenant n’est pas actuellement monté.', [], 409);
        }

        $this->liveKit->updateParticipantPublishing($live, (string) $stageRequest->account_id, false);

        $stageRequest->update([
            'status' => LiveStageRequest::STATUS_LOWERED,
            'resolved_at' => now(),
            'resolved_by_account_id' => $host->id,
        ]);
        $this->audit($live, $host->id, 'LiveStageLowered', [
            'stage_request_id' => $stageRequest->id,
            'participant_account_id' => $stageRequest->account_id,
        ]);

        return $stageRequest->refresh();
    }

    private function assertViewerPresent(LiveEvent $live, Account $viewer): void
    {
        $present = LiveViewerSession::query()
            ->where('live_id', $live->id)
            ->where('account_id', $viewer->id)
            ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
            ->exists();

        if (! $present) {
            throw new AppException('LIVE_VIEWER_SESSION_REQUIRED', 'Entrez d’abord dans le Live.', [], 409);
        }
    }

    private function assertHost(LiveEvent $live, Account $host, string $organizationId): void
    {
        if ((string) $live->advertiser_organization_id !== $organizationId) {
            throw new AppException('LIVE_ADVERTISER_CONTEXT_MISMATCH', 'Ce Live n’appartient pas à l’espace annonceur actif.', [], 403);
        }

        if ($live->owner_account_id !== $host->id) {
            throw new AppException('LIVE_OWNER_REQUIRED', 'Seul le créateur de ce Live peut piloter la scène.', [], 403);
        }
    }

    private function assertStageRequestBelongsToLive(LiveEvent $live, LiveStageRequest $stageRequest): void
    {
        if ($stageRequest->live_id !== $live->id) {
            throw new AppException('LIVE_STAGE_REQUEST_MISMATCH', 'Cette demande ne correspond pas à ce Live.', [], 404);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
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
}
