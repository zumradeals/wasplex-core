<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Application\Services\LiveInteractionService;
use App\Modules\Live\Events\LiveInteractionBroadcast;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveComment;
use App\Modules\Live\Infrastructure\Models\LiveCommentReport;
use App\Modules\Live\Infrastructure\Models\LiveCommentRestriction;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('services.livekit.url', 'wss://live.test');
    config()->set('services.livekit.api_key', 'devkey');
    config()->set('services.livekit.api_secret', 'test-secret');
    Event::fake([LiveInteractionBroadcast::class]);
});

it('persists Live comments replies reports and ephemeral reactions only for members in the room', function (): void {
    registerAndLogin('live-interactions-host@example.com');
    createAdvertiserOrganization('Annonceur interactions');
    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live interactions'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    $ledgerBefore = LedgerTransaction::query()->count();
    $hostCommentId = (string) test()->postJson("/api/advertiser/lives/{$liveId}/comments", [
        'body' => 'Bienvenue dans le direct Wasplex.',
    ])->assertCreated()
        ->assertJsonPath('comment.body', 'Bienvenue dans le direct Wasplex.')
        ->json('comment.id');

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-interactions-viewer@example.com');

    test()->postJson("/api/lives/{$liveId}/comments", ['body' => 'Trop tôt'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'LIVE_VIEWER_SESSION_REQUIRED');

    test()->postJson("/api/lives/{$liveId}/join")
        ->assertOk()
        ->assertJsonPath('live.viewer_count', 1);

    $viewerCommentId = (string) test()->postJson("/api/lives/{$liveId}/comments", [
        'body' => 'Bonsoir tout le monde 👋',
    ])->assertCreated()
        ->assertJsonPath('comment.body', 'Bonsoir tout le monde 👋')
        ->json('comment.id');

    test()->postJson("/api/lives/{$liveId}/comments", [
        'body' => 'Merci pour votre accueil.',
        'parent_comment_id' => $hostCommentId,
    ])->assertCreated()
        ->assertJsonPath('comment.reply_to.id', $hostCommentId);

    test()->postJson("/api/lives/{$liveId}/reactions", ['reaction' => 'heart'])
        ->assertStatus(202)
        ->assertJsonPath('accepted', true);

    test()->postJson("/api/lives/{$liveId}/comments/{$hostCommentId}/report", [
        'category' => 'other',
    ])->assertCreated()
        ->assertJsonPath('reported', true);

    test()->getJson("/api/lives/{$liveId}/comments")
        ->assertOk()
        ->assertJsonFragment(['body' => 'Bienvenue dans le direct Wasplex.'])
        ->assertJsonFragment(['body' => 'Bonsoir tout le monde 👋'])
        ->assertJsonPath('settings.comments_enabled', true)
        ->assertJsonPath('viewer.can_comment', true);

    expect(LiveComment::query()->count())->toBe(3)
        ->and(LiveComment::query()->findOrFail($viewerCommentId)->account_id)->not->toBeNull()
        ->and(LiveCommentReport::query()->count())->toBe(1)
        ->and(LedgerTransaction::query()->count())->toBe($ledgerBefore);

    Event::assertDispatched(
        LiveInteractionBroadcast::class,
        fn (LiveInteractionBroadcast $event): bool => $event->liveId === $liveId
            && $event->eventName === 'live.comment.created',
    );
    Event::assertDispatched(
        LiveInteractionBroadcast::class,
        fn (LiveInteractionBroadcast $event): bool => $event->liveId === $liveId
            && $event->eventName === 'live.reaction'
            && $event->payload['type'] === 'heart',
    );
    Event::assertDispatched(
        LiveInteractionBroadcast::class,
        fn (LiveInteractionBroadcast $event): bool => $event->liveId === $liveId
            && $event->eventName === 'live.viewer-count.changed'
            && $event->payload['viewer_count'] === 1,
    );
});

it('lets the host pin hide freeze and locally silence without creating a global sanction', function (): void {
    registerAndLogin('live-moderation-host@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur modération');
    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live modération'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    $hostAccountId = (string) LiveEvent::query()->findOrFail($liveId)->owner_account_id;
    $host = Account::query()->findOrFail($hostAccountId);

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-moderation-viewer@example.com');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();
    $viewerCommentId = (string) test()->postJson("/api/lives/{$liveId}/comments", [
        'body' => 'Commentaire à modérer',
    ])->assertCreated()->json('comment.id');

    $comment = LiveComment::query()->findOrFail($viewerCommentId);
    $viewer = Account::query()->findOrFail((string) $comment->account_id);
    $live = LiveEvent::query()->findOrFail($liveId);
    $service = app(LiveInteractionService::class);

    $service->togglePin($live, $comment, $host, $organizationId);
    expect(LiveComment::query()->findOrFail($viewerCommentId)->pinned_at)->not->toBeNull();

    $service->silence($live, $viewer, $host, $organizationId, null);
    test()->postJson("/api/lives/{$liveId}/comments", ['body' => 'Je suis silencé'])
        ->assertForbidden()
        ->assertJsonPath('code', 'LIVE_COMMENT_RESTRICTED');

    $service->unsilence($live, $viewer, $host, $organizationId);
    $service->updateSettings($live, $host, $organizationId, [
        'comments_enabled' => true,
        'slow_mode_seconds' => 5,
    ]);
    test()->postJson("/api/lives/{$liveId}/comments", ['body' => 'Trop rapide'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'LIVE_COMMENT_SLOW_MODE');

    $service->updateSettings($live, $host, $organizationId, ['comments_enabled' => false]);
    test()->postJson("/api/lives/{$liveId}/comments", ['body' => 'Chat fermé'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'LIVE_COMMENTS_FROZEN');

    $service->hideComment($live, $comment->refresh(), $host, $organizationId);
    test()->getJson("/api/lives/{$liveId}/comments")
        ->assertOk()
        ->assertJsonMissing(['body' => 'Commentaire à modérer'])
        ->assertJsonPath('settings.comments_enabled', false)
        ->assertJsonPath('settings.slow_mode_seconds', 5)
        ->assertJsonPath('viewer.can_comment', true);

    $restriction = LiveCommentRestriction::query()
        ->where('live_id', $liveId)
        ->where('account_id', $viewer->id)
        ->firstOrFail();

    expect($restriction->is_active)->toBeFalse()
        ->and(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveCommentPinned')->exists())
        ->toBeTrue()
        ->and(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveViewerSilenced')->exists())
        ->toBeTrue()
        ->and(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveViewerUnsilenced')->exists())
        ->toBeTrue()
        ->and(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveCommentHidden')->exists())
        ->toBeTrue();
});

it('keeps advertiser moderation scoped to the active organization', function (): void {
    registerAndLogin('live-moderation-scope@example.com');
    createAdvertiserOrganization('Organisation Live A');
    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live organisation A'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();
    $commentId = (string) test()->postJson("/api/advertiser/lives/{$liveId}/comments", [
        'body' => 'Commentaire hôte organisation A',
    ])->assertCreated()->json('comment.id');

    createAdvertiserOrganization('Organisation Live B');

    test()->postJson("/api/advertiser/lives/{$liveId}/comments/{$commentId}/pin")
        ->assertForbidden()
        ->assertJsonPath('code', 'LIVE_ADVERTISER_CONTEXT_MISMATCH');
});
