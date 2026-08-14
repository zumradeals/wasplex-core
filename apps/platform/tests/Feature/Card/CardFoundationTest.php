<?php

declare(strict_types=1);

use App\Modules\Card\Application\Services\CardQrService;
use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardAuditEvent;
use App\Modules\Card\Infrastructure\Models\CardQrToken;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

it('issues one virtual Wasplex Base card per member without creating a card balance', function (): void {
    registerAndLogin('card-base@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-base@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $account->id)->update(['display_name' => 'Awa Wasplex']);

    $first = test()->postJson('/api/cards')->assertCreated();
    $cardId = $first->json('card.id');

    $first->assertJsonPath('card.offer.code', 'WASPLEX_BASE')
        ->assertJsonPath('card.status', Card::STATUS_ACTIVE)
        ->assertJsonPath('card.supports_virtual', true)
        ->assertJsonMissingPath('card.phone')
        ->assertJsonMissingPath('card.email')
        ->assertJsonMissingPath('card.balance');

    expect((string) $first->json('card.public_identifier'))->toStartWith('WPLX-CI-');

    test()->postJson('/api/cards')->assertCreated()->assertJsonPath('card.id', $cardId);

    test()->getJson('/api/cards')
        ->assertOk()
        ->assertJsonPath('card.id', $cardId)
        ->assertJsonPath('card.status', Card::STATUS_ACTIVE);

    expect(Card::query()->where('account_id', $account->id)->count())->toBe(1);
    expect(CardAuditEvent::query()->where('event_type', 'CardIssued')->count())->toBe(1);
});

it('generates an expiring single-use identity QR and resolves only minimal public data', function (): void {
    registerAndLogin('card-qr-owner@example.com');
    $owner = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-qr-owner@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $owner->id)->update(['display_name' => 'Moussa Test']);

    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $response = test()->postJson("/api/cards/{$cardId}/qr")->assertOk();
    $payload = (string) $response->json('qr.payload');

    expect($payload)->toContain('/api/cards/qr/check?token=');
    parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);
    $secret = $query['token'] ?? null;
    expect($secret)->toBeString()->not->toBeEmpty();

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-qr-scanner@example.com');

    test()->getJson('/api/cards/qr/check?token='.urlencode($secret))
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('card.display_name', 'Moussa Test')
        ->assertJsonPath('card.offer_name', 'Wasplex Base')
        ->assertJsonMissingPath('card.phone')
        ->assertJsonMissingPath('card.email')
        ->assertJsonMissingPath('card.balance');

    test()->getJson('/api/cards/qr/check?token='.urlencode($secret))->assertGone();
    expect(CardAuditEvent::query()->where('event_type', 'CardQrResolved')->count())->toBe(1);
});

it('expires QR tokens at the service boundary', function (): void {
    registerAndLogin('card-expire@example.com');
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/qr")->assertOk()->json('qr.payload');
    parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);

    $token = CardQrToken::query()->firstOrFail();
    $token->update(['expires_at' => now()->subSecond()]);

    expect(fn () => app(CardQrService::class)->resolve((string) $query['token']))
        ->toThrow(GoneHttpException::class);
});

it('revokes active QR tokens when the owner suspends the card and blocks new QR generation', function (): void {
    registerAndLogin('card-suspend@example.com');
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/qr")->assertOk()->json('qr.payload');
    parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);

    test()->postJson("/api/cards/{$cardId}/suspend")
        ->assertOk()
        ->assertJsonPath('card.status', Card::STATUS_SUSPENDED);

    test()->getJson('/api/cards/qr/check?token='.urlencode((string) $query['token']))->assertGone();
    test()->postJson("/api/cards/{$cardId}/qr")->assertConflict();

    expect(CardAuditEvent::query()->where('event_type', 'CardSuspended')->count())->toBe(1);
});

it('prevents another authenticated member from operating a card they do not own', function (): void {
    registerAndLogin('card-owner@example.com');
    $cardId = test()->postJson('/api/cards')->assertCreated()->json('card.id');

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-intruder@example.com');

    test()->postJson("/api/cards/{$cardId}/qr")->assertNotFound();
    test()->postJson("/api/cards/{$cardId}/suspend")->assertNotFound();
});

it('keeps the Carte entry transversal outside the five primary tabs', function (): void {
    registerAndLogin('card-page@example.com');

    test()->get('/services/wasplex')->assertOk();
});
