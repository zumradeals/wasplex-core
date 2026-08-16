<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Live\Application\Services\LiveRealtimeService;
use App\Modules\Live\Infrastructure\Models\LiveAuditEvent;
use App\Modules\Live\Infrastructure\Models\LiveEvent;
use App\Modules\Live\Infrastructure\Models\LiveStageRequest;
use App\Modules\Live\Infrastructure\Models\LiveStreamSession;
use App\Modules\Live\Infrastructure\Models\LiveViewerSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('services.livekit.url', 'wss://live.test');
    config()->set('services.livekit.api_key', 'devkey');
    config()->set('services.livekit.api_secret', 'test-secret');
});

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
        ->assertJsonPath('live.stream.provider', 'livekit')
        ->assertJsonPath('live.stream.room', "wasplex-live-{$liveId}")
        ->assertJsonPath('live.stream.media_ready', true);

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

it('refuses to mark a Live active when realtime media is not configured', function (): void {
    config()->set('services.livekit.url', null);
    config()->set('services.livekit.api_key', null);
    config()->set('services.livekit.api_secret', null);

    registerAndLogin('live-no-media@example.com');
    createAdvertiserOrganization('Annonceur sans média');
    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live sans média'])
        ->assertCreated()
        ->json('live.id');

    test()->postJson("/api/advertiser/lives/{$liveId}/start")
        ->assertStatus(503)
        ->assertJsonPath('code', 'LIVE_MEDIA_NOT_CONFIGURED');

    expect(LiveEvent::query()->findOrFail($liveId)->status)->toBe(LiveEvent::STATUS_DRAFT);
});

it('issues short lived LiveKit tokens with distinct host and viewer publishing rights and connection identities', function (): void {
    registerAndLogin('live-token-host@example.com');
    createAdvertiserOrganization('Annonceur Token');

    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live token'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    $hostConnectionA = (string) Str::uuid();
    $hostConnectionB = (string) Str::uuid();
    $hostMediaA = test()->postJson("/api/advertiser/lives/{$liveId}/media-token", [
        'connection_id' => $hostConnectionA,
    ])->assertOk()
        ->assertJsonPath('media.can_publish', true)
        ->assertJsonPath('media.url', 'wss://live.test');
    $hostMediaB = test()->postJson("/api/advertiser/lives/{$liveId}/media-token", [
        'connection_id' => $hostConnectionB,
    ])->assertOk();

    $decodePayload = static function (string $token): array {
        $payload = explode('.', $token)[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    };

    $hostPayload = $decodePayload((string) $hostMediaA->json('media.token'));
    expect($hostPayload['video']['roomJoin'])->toBeTrue()
        ->and($hostPayload['video']['canPublish'])->toBeTrue()
        ->and($hostPayload['video']['room'])->toBe("wasplex-live-{$liveId}")
        ->and($hostPayload['exp'] - $hostPayload['nbf'])->toBe(300)
        ->and($hostMediaA->json('media.identity'))->not->toBe($hostMediaB->json('media.identity'));

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-token-viewer@example.com');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    $viewerConnection = (string) Str::uuid();
    $viewerMedia = test()->postJson("/api/lives/{$liveId}/media-token", [
        'connection_id' => $viewerConnection,
    ])->assertOk()
        ->assertJsonPath('media.can_publish', false);
    $viewerPayload = $decodePayload((string) $viewerMedia->json('media.token'));

    expect($viewerPayload['video']['canPublish'])->toBeFalse()
        ->and($viewerPayload['video']['canSubscribe'])->toBeTrue()
        ->and($viewerPayload['sub'])->toBe($viewerMedia->json('media.identity'))
        ->and($viewerMedia->json('media.identity'))->not->toBe($hostMediaA->json('media.identity'));
});

it('lets a viewer request the stage and grants publishing only to the requesting LiveKit connection', function (): void {
    Http::fake([
        'https://live.test/twirp/livekit.RoomService/UpdateParticipant' => Http::response(['identity' => 'ok'], 200),
    ]);

    registerAndLogin('live-stage-host@example.com');
    $organizationId = createAdvertiserOrganization('Annonceur Scène');
    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live scène'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    $live = LiveEvent::query()->findOrFail($liveId);
    $host = Account::query()->findOrFail((string) $live->owner_account_id);

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('live-stage-viewer@example.com');
    test()->postJson("/api/lives/{$liveId}/join")->assertOk();

    $viewerAccountId = (string) LiveViewerSession::query()
        ->where('live_id', $liveId)
        ->whereIn('status', [LiveViewerSession::STATUS_WATCHING, LiveViewerSession::STATUS_PAUSED])
        ->value('account_id');
    $viewer = Account::query()->findOrFail($viewerAccountId);
    $viewerConnection = (string) Str::uuid();
    $otherViewerConnection = (string) Str::uuid();

    $viewerIdentity = (string) test()->postJson("/api/lives/{$liveId}/media-token", [
        'connection_id' => $viewerConnection,
    ])->assertOk()
        ->assertJsonPath('media.can_publish', false)
        ->json('media.identity');

    $requestId = (string) test()->postJson("/api/lives/{$liveId}/stage-request", [
        'connection_id' => $viewerConnection,
    ])->assertCreated()
        ->assertJsonPath('stage_request.status', LiveStageRequest::STATUS_PENDING)
        ->json('stage_request.id');

    $stageRequest = LiveStageRequest::query()->findOrFail($requestId);
    expect($stageRequest->provider_participant_identity)->toBe($viewerIdentity);

    $service = app(LiveRealtimeService::class);
    $approved = $service->approve($live, $stageRequest, $host, $organizationId);
    expect($approved->status)->toBe(LiveStageRequest::STATUS_APPROVED);

    $speakerCredentials = $service->viewerCredentials($live, $viewer, $viewerConnection);
    $otherConnectionCredentials = $service->viewerCredentials($live, $viewer, $otherViewerConnection);
    expect($speakerCredentials['can_publish'])->toBeTrue()
        ->and($speakerCredentials['role'])->toBe('speaker')
        ->and($speakerCredentials['identity'])->toBe($viewerIdentity)
        ->and($otherConnectionCredentials['can_publish'])->toBeFalse()
        ->and($otherConnectionCredentials['role'])->toBe('viewer');

    $lowered = $service->lower($live, $approved, $host, $organizationId);
    expect($lowered->status)->toBe(LiveStageRequest::STATUS_LOWERED);

    Http::assertSentCount(2);
    Http::assertSent(function ($request) use ($viewerIdentity): bool {
        if (! str_ends_with($request->url(), '/twirp/livekit.RoomService/UpdateParticipant')) {
            return false;
        }

        return $request['identity'] === $viewerIdentity
            && $request['permission']['canSubscribe'] === true
            && $request['permission']['canPublishData'] === false;
    });
});

it('requires a connection id before issuing realtime media credentials', function (): void {
    registerAndLogin('live-connection-host@example.com');
    createAdvertiserOrganization('Annonceur connexion');
    $liveId = (string) test()->postJson('/api/advertiser/lives', ['title' => 'Live connexion'])
        ->assertCreated()
        ->json('live.id');
    test()->postJson("/api/advertiser/lives/{$liveId}/start")->assertOk();

    test()->postJson("/api/advertiser/lives/{$liveId}/media-token")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['details' => ['errors' => ['connection_id']]]);
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

it('exposes the authenticated spectator Live page with same-origin camera and microphone permission', function (): void {
    registerAndLogin('live-page@example.com');

    test()->get('/live')
        ->assertOk()
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()');
});
