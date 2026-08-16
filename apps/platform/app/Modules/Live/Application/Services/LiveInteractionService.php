<?php

declare(strict_types=1);

namespace App\Modules\Live\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Live\Events\LiveInteractionBroadcast;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveComment;
use App\Modules\Live\Infrastructure\Models\LiveCommentReport;
use App\Modules\Live\Infrastructure\Models\LiveCommentRestriction;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveInteractionSetting;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use App\Shared\Http\AppException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LiveInteractionService
{
    private const REACTION_EMOJI = [
        'heart' => '❤️',
        'clap' => '👏',
        'fire' => '🔥',
        'smile' => '😊',
    ];

    /**
     * @return array<string, mixed>
     */
    public function viewerState(LiveEvent $live, Account $viewer): array
    {
        $this->assertReadable($live);
        $this->assertViewerPresent($live, $viewer);

        [$commentsEnabled, $slowModeSeconds] = $this->settingsValues($live);
        $restriction = $this->activeRestriction($live, $viewer);

        $comments = $this->commentsQuery($live)
            ->whereNull('hidden_at')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (LiveComment $comment): array => LiveInteractionPresenter::comment($comment));

        return [
            'comments' => $comments,
            'settings' => LiveInteractionPresenter::settings($commentsEnabled, $slowModeSeconds),
            'viewer_count' => $this->viewerCount($live),
            'viewer' => [
                'can_comment' => $restriction === null,
                'restriction_message' => $restriction === null
                    ? null
                    : ($restriction->reason ?: 'Vous pouvez continuer à regarder ce Live, mais vous ne pouvez plus commenter.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hostState(
        LiveEvent $live,
        Account $host,
        string $advertiserOrganizationId,
    ): array {
        $this->assertReadable($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        [$commentsEnabled, $slowModeSeconds] = $this->settingsValues($live);

        $comments = $this->commentsQuery($live)
            ->latest('created_at')
            ->limit(80)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (LiveComment $comment): array => LiveInteractionPresenter::comment($comment));

        $silencedAccountIds = LiveCommentRestriction::query()
            ->where('live_id', $live->id)
            ->where('is_active', true)
            ->pluck('account_id')
            ->values();

        return [
            'comments' => $comments,
            'settings' => LiveInteractionPresenter::settings($commentsEnabled, $slowModeSeconds),
            'viewer_count' => $this->viewerCount($live),
            'moderation' => [
                'silenced_account_ids' => $silencedAccountIds,
            ],
        ];
    }

    public function commentAsViewer(
        LiveEvent $live,
        Account $viewer,
        string $body,
        ?string $parentCommentId,
    ): LiveComment {
        $this->assertLive($live);
        $this->assertViewerPresent($live, $viewer);
        [$commentsEnabled, $slowModeSeconds] = $this->settingsValues($live);

        if (! $commentsEnabled) {
            throw new AppException('LIVE_COMMENTS_FROZEN', 'Les commentaires sont temporairement fermés.', [], 409);
        }

        $restriction = $this->activeRestriction($live, $viewer);
        if ($restriction !== null) {
            throw new AppException(
                'LIVE_COMMENT_RESTRICTED',
                $restriction->reason ?: 'Vous ne pouvez plus commenter dans ce Live.',
                [],
                403,
            );
        }

        if ($slowModeSeconds > 0) {
            $lastComment = LiveComment::query()
                ->where('live_id', $live->id)
                ->where('account_id', $viewer->id)
                ->latest('created_at')
                ->first(['created_at']);

            if ($lastComment?->created_at !== null && $lastComment->created_at->diffInSeconds(now(), true) < $slowModeSeconds) {
                throw new AppException(
                    'LIVE_COMMENT_SLOW_MODE',
                    "Mode lent actif : attendez {$slowModeSeconds} secondes entre deux commentaires.",
                    ['slow_mode_seconds' => $slowModeSeconds],
                    429,
                );
            }
        }

        return $this->createComment($live, $viewer, $body, $parentCommentId);
    }

    public function commentAsHost(
        LiveEvent $live,
        Account $host,
        string $advertiserOrganizationId,
        string $body,
        ?string $parentCommentId,
    ): LiveComment {
        $this->assertLive($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);

        return $this->createComment($live, $host, $body, $parentCommentId);
    }

    public function reactAsViewer(LiveEvent $live, Account $viewer, string $reaction): void
    {
        $this->assertLive($live);
        $this->assertViewerPresent($live, $viewer);
        $this->broadcastReaction($live, $viewer, $reaction);
    }

    public function reactAsHost(
        LiveEvent $live,
        Account $host,
        string $advertiserOrganizationId,
        string $reaction,
    ): void {
        $this->assertLive($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        $this->broadcastReaction($live, $host, $reaction);
    }

    public function reportComment(
        LiveEvent $live,
        LiveComment $comment,
        Account $reporter,
        string $category,
        ?string $note,
    ): LiveCommentReport {
        $this->assertReadable($live);
        $this->assertViewerPresent($live, $reporter);
        $this->assertCommentBelongsToLive($live, $comment);

        if ($comment->account_id === $reporter->id) {
            throw new AppException('LIVE_COMMENT_SELF_REPORT', 'Vous ne pouvez pas signaler votre propre commentaire.', [], 422);
        }

        $report = LiveCommentReport::query()->firstOrCreate(
            [
                'comment_id' => $comment->id,
                'reporter_account_id' => $reporter->id,
            ],
            [
                'live_id' => $live->id,
                'category' => $category,
                'note' => $this->nullableText($note),
            ],
        );

        if ($report->wasRecentlyCreated) {
            $this->audit($live, $reporter->id, 'LiveCommentReported', [
                'comment_id' => $comment->id,
                'report_id' => $report->id,
                'category' => $category,
            ]);
        }

        return $report;
    }

    public function togglePin(
        LiveEvent $live,
        LiveComment $comment,
        Account $host,
        string $advertiserOrganizationId,
    ): LiveComment {
        $this->assertReadable($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        $this->assertCommentBelongsToLive($live, $comment);

        $wasPinned = $comment->pinned_at !== null;
        if (! $wasPinned && $comment->hidden_at !== null) {
            throw new AppException('LIVE_COMMENT_HIDDEN', 'Un commentaire masqué ne peut pas être épinglé.', [], 409);
        }

        $updated = DB::transaction(function () use ($live, $comment, $host, $wasPinned): LiveComment {
            LiveComment::query()
                ->where('live_id', $live->id)
                ->whereNotNull('pinned_at')
                ->update([
                    'pinned_at' => null,
                    'pinned_by_account_id' => null,
                ]);

            if (! $wasPinned && $comment->hidden_at === null) {
                $comment->update([
                    'pinned_at' => now(),
                    'pinned_by_account_id' => $host->id,
                ]);
            } else {
                $comment->refresh();
            }

            $this->audit($live, $host->id, $wasPinned ? 'LiveCommentUnpinned' : 'LiveCommentPinned', [
                'comment_id' => $comment->id,
            ]);

            return $comment->refresh();
        });

        $this->broadcast($live, 'live.comment.updated', [
            'comment' => LiveInteractionPresenter::comment($updated),
            'clear_pinned' => true,
        ]);

        return $updated;
    }

    public function hideComment(
        LiveEvent $live,
        LiveComment $comment,
        Account $host,
        string $advertiserOrganizationId,
    ): LiveComment {
        $this->assertReadable($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        $this->assertCommentBelongsToLive($live, $comment);

        if ($comment->hidden_at === null) {
            $comment->update([
                'hidden_at' => now(),
                'hidden_by_account_id' => $host->id,
                'pinned_at' => null,
                'pinned_by_account_id' => null,
            ]);
            $this->audit($live, $host->id, 'LiveCommentHidden', [
                'comment_id' => $comment->id,
                'comment_author_account_id' => $comment->account_id,
            ]);
        }

        $comment->refresh();
        $this->broadcast($live, 'live.comment.updated', [
            'comment' => LiveInteractionPresenter::comment($comment),
            'clear_pinned' => false,
        ]);

        return $comment;
    }

    /**
     * @param  array{comments_enabled?: bool, slow_mode_seconds?: int}  $attributes
     * @return array{comments_enabled: bool, slow_mode_seconds: int}
     */
    public function updateSettings(
        LiveEvent $live,
        Account $host,
        string $advertiserOrganizationId,
        array $attributes,
    ): array {
        $this->assertReadable($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        if ($attributes === []) {
            throw new AppException('LIVE_INTERACTION_SETTINGS_EMPTY', 'Aucun réglage d’interaction à modifier.', [], 422);
        }
        [$commentsEnabled, $slowModeSeconds] = $this->settingsValues($live);

        $commentsEnabled = $attributes['comments_enabled'] ?? $commentsEnabled;
        $slowModeSeconds = $attributes['slow_mode_seconds'] ?? $slowModeSeconds;

        LiveInteractionSetting::query()->updateOrCreate(
            ['live_id' => $live->id],
            [
                'comments_enabled' => $commentsEnabled,
                'slow_mode_seconds' => $slowModeSeconds,
                'updated_by_account_id' => $host->id,
            ],
        );

        $this->audit($live, $host->id, 'LiveInteractionSettingsUpdated', [
            'comments_enabled' => $commentsEnabled,
            'slow_mode_seconds' => $slowModeSeconds,
        ]);

        $settings = LiveInteractionPresenter::settings($commentsEnabled, $slowModeSeconds);
        $this->broadcast($live, 'live.settings.updated', ['settings' => $settings]);

        return $settings;
    }

    public function silence(
        LiveEvent $live,
        Account $target,
        Account $host,
        string $advertiserOrganizationId,
        ?string $reason,
    ): LiveCommentRestriction {
        $this->assertReadable($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        $this->assertModeratableParticipant($live, $target);

        if ($target->id === $host->id) {
            throw new AppException('LIVE_HOST_NOT_RESTRICTABLE', 'L’hôte ne peut pas se mettre lui-même en silence.', [], 422);
        }

        $restriction = LiveCommentRestriction::query()->updateOrCreate(
            [
                'live_id' => $live->id,
                'account_id' => $target->id,
            ],
            [
                'is_active' => true,
                'reason' => $this->nullableText($reason),
                'created_by_account_id' => $host->id,
                'lifted_at' => null,
                'lifted_by_account_id' => null,
            ],
        );

        $this->audit($live, $host->id, 'LiveViewerSilenced', [
            'account_id' => $target->id,
            'restriction_id' => $restriction->id,
        ]);

        $this->broadcast($live, 'live.restriction.updated', [
            'account_id' => $target->id,
            'can_comment' => false,
            'message' => $restriction->reason ?: 'Vous pouvez continuer à regarder ce Live, mais vous ne pouvez plus commenter.',
        ]);

        return $restriction->refresh();
    }

    public function unsilence(
        LiveEvent $live,
        Account $target,
        Account $host,
        string $advertiserOrganizationId,
    ): void {
        $this->assertReadable($live);
        $this->assertHost($live, $host, $advertiserOrganizationId);
        $this->assertModeratableParticipant($live, $target);

        $restriction = LiveCommentRestriction::query()
            ->where('live_id', $live->id)
            ->where('account_id', $target->id)
            ->where('is_active', true)
            ->first();

        if ($restriction === null) {
            return;
        }

        $restriction->update([
            'is_active' => false,
            'lifted_at' => now(),
            'lifted_by_account_id' => $host->id,
        ]);
        $this->audit($live, $host->id, 'LiveViewerUnsilenced', [
            'account_id' => $target->id,
            'restriction_id' => $restriction->id,
        ]);

        $this->broadcast($live, 'live.restriction.updated', [
            'account_id' => $target->id,
            'can_comment' => true,
            'message' => null,
        ]);
    }

    public function broadcastViewerCount(LiveEvent $live): int
    {
        $count = $this->viewerCount($live);
        $this->broadcast($live, 'live.viewer-count.changed', ['viewer_count' => $count]);

        return $count;
    }

    private function createComment(
        LiveEvent $live,
        Account $actor,
        string $body,
        ?string $parentCommentId,
    ): LiveComment {
        $body = trim($body);
        if ($body === '') {
            throw new AppException('LIVE_COMMENT_EMPTY', 'Le commentaire ne peut pas être vide.', [], 422);
        }

        $parent = null;
        if ($parentCommentId !== null) {
            $parent = LiveComment::query()->find($parentCommentId);
            if ($parent === null || $parent->live_id !== $live->id || $parent->hidden_at !== null) {
                throw new AppException('LIVE_COMMENT_REPLY_TARGET_INVALID', 'Le commentaire auquel vous répondez n’est plus disponible.', [], 422);
            }
        }

        $comment = LiveComment::query()->create([
            'live_id' => $live->id,
            'account_id' => $actor->id,
            'parent_comment_id' => $parent?->id,
            'body' => $body,
        ])->load(['account.profile', 'parent.account.profile']);

        $this->broadcast($live, 'live.comment.created', [
            'comment' => LiveInteractionPresenter::comment($comment),
        ]);

        return $comment;
    }

    private function broadcastReaction(LiveEvent $live, Account $actor, string $reaction): void
    {
        $emoji = self::REACTION_EMOJI[$reaction] ?? null;
        if ($emoji === null) {
            throw new AppException('LIVE_REACTION_INVALID', 'Cette réaction n’est pas disponible.', [], 422);
        }

        $this->broadcast($live, 'live.reaction', [
            'type' => $reaction,
            'emoji' => $emoji,
            'account_id' => $actor->id,
        ]);
    }

    /**
     * @return Builder<LiveComment>
     */
    private function commentsQuery(LiveEvent $live): Builder
    {
        return LiveComment::query()
            ->with(['account.profile', 'parent.account.profile'])
            ->where('live_id', $live->id);
    }

    /**
     * @return array{0: bool, 1: int}
     */
    private function settingsValues(LiveEvent $live): array
    {
        $settings = LiveInteractionSetting::query()->where('live_id', $live->id)->first();

        return [
            $settings?->comments_enabled ?? true,
            $settings?->slow_mode_seconds ?? 0,
        ];
    }

    private function viewerCount(LiveEvent $live): int
    {
        return LiveViewerSession::query()
            ->where('live_id', $live->id)
            ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
            ->count();
    }

    private function activeRestriction(LiveEvent $live, Account $account): ?LiveCommentRestriction
    {
        return LiveCommentRestriction::query()
            ->where('live_id', $live->id)
            ->where('account_id', $account->id)
            ->where('is_active', true)
            ->first();
    }

    private function assertReadable(LiveEvent $live): void
    {
        if (! in_array($live->status, [LiveEvent::STATUS_LIVE, LiveEvent::STATUS_PAUSED], true)) {
            throw new AppException('LIVE_INTERACTIONS_NOT_OPEN', 'Les interactions ne sont pas ouvertes pour ce Live.', [], 409);
        }
    }

    private function assertLive(LiveEvent $live): void
    {
        if ($live->status !== LiveEvent::STATUS_LIVE) {
            throw new AppException('LIVE_INTERACTIONS_NOT_OPEN', 'Les interactions sont ouvertes uniquement pendant le direct.', [], 409);
        }
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

    private function assertHost(LiveEvent $live, Account $host, string $advertiserOrganizationId): void
    {
        if ((string) $live->advertiser_organization_id !== $advertiserOrganizationId) {
            throw new AppException('LIVE_ADVERTISER_CONTEXT_MISMATCH', 'Ce Live n’appartient pas à l’espace annonceur actif.', [], 403);
        }

        if ($live->owner_account_id !== $host->id) {
            throw new AppException('LIVE_OWNER_REQUIRED', 'Seul le créateur de ce Live peut modérer les interactions.', [], 403);
        }
    }

    private function assertCommentBelongsToLive(LiveEvent $live, LiveComment $comment): void
    {
        if ($comment->live_id !== $live->id) {
            throw new AppException('LIVE_COMMENT_MISMATCH', 'Ce commentaire ne correspond pas à ce Live.', [], 404);
        }
    }

    private function assertModeratableParticipant(LiveEvent $live, Account $target): void
    {
        $known = LiveViewerSession::query()
            ->where('live_id', $live->id)
            ->where('account_id', $target->id)
            ->exists()
            || LiveComment::query()
                ->where('live_id', $live->id)
                ->where('account_id', $target->id)
                ->exists();

        if (! $known) {
            throw new AppException('LIVE_PARTICIPANT_NOT_FOUND', 'Ce membre ne participe pas à ce Live.', [], 404);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcast(LiveEvent $live, string $eventName, array $payload): void
    {
        try {
            event(new LiveInteractionBroadcast((string) $live->id, $eventName, $payload));
        } catch (Throwable $exception) {
            report($exception);
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

    private function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
