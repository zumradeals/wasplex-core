<?php

declare(strict_types=1);

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveStreamSession;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;

it('reserves Live creation to the active advertiser Studio', function (): void {
    registerAndLogin('live-member-only@example.com');

    test()->postJson('/api/advertiser/lives', ['title' => 'Live interdit'])
        ->assertForbidden();

    test()->postJson('/api/creator/lives', ['title' => 'Ancienne route'])
        ->assertNotFound();
});

it('creates schedules starts pauses resumes and ends an advertiser live without touching the Ledger', function (): void {
    registerAndLogin('live-advertiser@example.com');
    $organizationId = createAdvertiserOrganization('Wasplex Live Demo');
    $ledgerBefore = LedgerTransaction::query()->count();

    $created = test()->postJson('/api/advertiser/lives', [
        'title' => 'Live annonceur Wasplex',
        'description' => 'Premier test du Live depuis le Studio annonceur.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'planned_duration_minutes' => 60,
    ])->assertCreated()
        ->assertJsonPath('live.status', LiveEvent::STATUS_SCHEDULED)
        ->assertJsonPath('live.title', 'Live annonceur Wasplex')
        ->assertJsonPath('live.owner.display_name', 'Wasplex Live Demo')
        ->assertJsonPath('live.stream.media_ready', false);

    $liveId = (string) $created->json('live.id');

    expect(LiveEvent::query()->findOrFail($liveId)->advertiser_organization_id)->toBe($organizationId);

    test()->postJson("/api/advertiser/lives/{$liveId}/start")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_LIVE)
        ->assertJsonPath('live.stream.provider', 'pending_adapter');

    test()->postJson("/api/advertiser/lives/{$liveId}/pause")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_PAUSED);

    test()->postJson("/api/advertiser/lives/{$liveId}/resume")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_LIVE);

    test()->postJson("/api/advertiser/lives/{$liveId}/end")
        ->assertOk()
        ->assertJsonPath('live.status', LiveEvent::STATUS_ENDED);

    expect(LiveStreamSession::query()->where('live_id', $liveId)->count())->toBe(1)
        ->and(LiveStreamSession::query()->where('live_id', $liveId)->value('status'))->toBe(LiveStreamSession::STATUS_ENDED)
        ->and(LiveAuditEvent::query()->where('live_id', $liveId)->where('event_type', 'LiveStarted')->exists())->toBeTrue()
        ->and(LedgerTransaction::query()->count())->toBe($ledgerBefore);
});

it('lists only advertiser-published public lives and lets a member join and leave', function (): void {
    registerAndLogin('live-public-advertiser@example.com');
    createAdvertiserOrganization('Annonceur Live Public');

    test()->postJson('/api/advertiser/lives', [
        'title' => 'Brouillon annonceur',
    ])->assertCreated();

    $active = test()->postJson('/api/advertiser/lives', [
        'title' => 'Live public actif',
        'planned_duration_minutes' => 30,
    ])->assertCreated();
    $activeId = (string) $active->json('live.id');
    test()->postJson("/api/advertiser/lives/{$activeId}/start")->assertOk();

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-viewer@example.com');

    test()->getJson('/api/lives')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Live public actif'])
        ->assertJsonFragment(['display_name' => 'Annonceur Live Public'])
        ->assertJsonMissing(['title' => 'Brouillon annonceur']);

    test()->postJson("/api/lives/{$activeId}/join")
        ->assertOk()
        ->assertJsonPath('viewer_session.status', LiveViewerSession::STATUS_WATCHING)
        ->assertJsonPath('live.viewer_count', 1);

    test()->postJson("/api/lives/{$activeId}/leave")
        ->assertOk()
        ->assertJsonPath('left', true);

    expect(LiveViewerSession::query()->where('live_id', $activeId)->value('status'))->toBe(LiveViewerSession::STATUS_LEFT);
});

it('isolates Live management between advertiser organizations', function (): void {
    registerAndLogin('live-multi-advertiser@example.com');
    createAdvertiserOrganization('Organisation Live A');

    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live organisation A'])
        ->assertCreated()
        ->json('live.id');

    createAdvertiserOrganization('Organisation Live B');

    test()->getJson('/api/advertiser/lives')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Live organisation A']);

    test()->postJson("/api/advertiser/lives/{$liveId}/start")
        ->assertForbidden();

    expect(LiveEvent::query()->findOrFail($liveId)->status)->toBe(LiveEvent::STATUS_DRAFT);
});

it('exposes the authenticated spectator Live page', function (): void {
    registerAndLogin('live-page@example.com');

    test()->get('/live')->assertOk();
});
