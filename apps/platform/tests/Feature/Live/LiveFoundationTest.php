<?php

declare(strict_types=1);

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveStreamSession;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;

it('creates schedules starts pauses resumes and ends a standard live without touching the Ledger', function (): void {
    registerAndLogin('live-owner@example.com');
    $ledgerBefore = LedgerTransaction::query()->count();

    $created = test()->postJson('/api/creator/lives', [
        'title' => 'Live communauté Wasplex',
        'description' => 'Premier test du Live standard.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'planned_duration_minutes' => 60,
    ])->assertCreated()
        ->assertJsonPath('live.status', LiveEvent::STATUS_SCHEDULED)
        ->assertJsonPath('live.title', 'Live communauté Wasplex')
        ->assertJsonPath('live.stream.media_ready', false);

    $liveId = (string) $created->json('live.id');

    test()->postJson("/api/creator/lives/{$liveId}/start")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_LIVE)
        ->assertJsonPath('live.stream.provider', 'pending_adapter');

    test()->postJson("/api/creator/lives/{$liveId}/pause")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_PAUSED);

    test()->postJson("/api/creator/lives/{$liveId}/resume")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_LIVE);

    test()->postJson("/api/creator/lives/{$liveId}/end")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_ENDED);

    expect(LiveStreamSession::query()->where('live_id', $liveId)->count())->toBe(1)
        ->and(LiveStreamSession::query()->where('live_id', $liveId)->value('status'))->toBe(LiveStreamSession::STATUS_ENDED)
        ->and(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveStarted')->exists())->toBeTrue()
        ->and(LedgerTransaction::query()->count())->toBe($ledgerBefore);
});

it('lists only public scheduled or active lives and lets another member join and leave', function (): void {
    registerAndLogin('live-public-owner@example.com');

    test()->postJson('/api/creator/lives', [
        'title' => 'Brouillon secret',
    ])->assertCreated();

    $active = test()->postJson('/api/creator/lives', [
        'title' => 'Live public actif',
        'planned_duration_minutes' => 30,
    ])->assertCreated();
    $activeId = (string) $active->json('live.id');
    test()->postJson("/api/creator/lives/{$activeId}/start")->assertOk();

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-viewer@example.com');

    test()->getJson('/api/lives')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Live public actif'])
        ->assertJsonMissing(['title' => 'Brouillon secret']);

    test()->postJson("/api/lives/{$activeId}/join")
        ->assertOk()
        ->assertJsonPath('viewer_session.status', LiveViewerSession::STATUS_WATCHING)
        ->assertJsonPath('live.viewer_count', 1);

    test()->postJson("/api/lives/{$activeId}/leave")
        ->assertOk()
        ->assertJsonPath('left', true);

    expect(LiveViewerSession::query()->where('live_id', $activeId)->value('status'))->toBe(LiveViewerSession::STATUS_LEFT);
});

it('prevents another member from controlling a live they do not own', function (): void {
    registerAndLogin('live-control-owner@example.com');
    $liveId = (string) test()->postJson('/api/creator/lives', ['title' => 'Live protégé'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/creator/lives/{$liveId}/start")->assertOk();

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-control-intruder@example.com');

    test()->postJson("/api/creator/lives/{$liveId}/pause")->assertForbidden();
    expect(LiveEvent::query()->findOrFail($liveId)->status)->toBe(LiveEvent::STATUS_LIVE);
});

it('exposes the authenticated Live page', function (): void {
    registerAndLogin('live-page@example.com');

    test()->get('/live')->assertOk();
});
