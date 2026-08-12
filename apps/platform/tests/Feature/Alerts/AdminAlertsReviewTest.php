<?php

declare(strict_types=1);

use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountAuditEvent;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

function alertsAccountFor(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function verifyRecentMfaForAlertsTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function loginExistingAlertsAccount(string $identifier, string $password = 'Password123!'): void
{
    test()->withCredentials();

    $login = test()->postJson('/api/login', [
        'identifier_value' => $identifier,
        'password' => $password,
    ])->assertOk();

    $sessionCookieName = config('session.cookie');

    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }
}

function submitAlertForCurrentMember(array $overrides = []): AlertDeclaration
{
    $response = test()->postJson('/api/alerts', [
        'category' => 'document',
        'situation' => 'lost',
        'title' => 'Document perdu à Abobo',
        'description' => 'Une pièce importante a été perdue près du marché et doit être retrouvée.',
        'city' => 'Abidjan',
        'area_label' => 'Abobo',
        'exact_location' => 'Coordonnées exactes réservées à Wasplex',
        ...$overrides,
    ])->assertCreated();

    return AlertDeclaration::query()->findOrFail($response->json('alert.id'));
}

test('an authorized admin can publish a reviewed alert without exposing its private location', function (): void {
    registerAndLogin('alerts-publish-subject@wasplex.test');
    $alert = submitAlertForCurrentMember();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('alerts-publish-admin@wasplex.test');
    $admin = alertsAccountFor('alerts-publish-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.alerts.review']);
    verifyRecentMfaForAlertsTests();

    test()->postJson("/api/admin/alerts/{$alert->id}/publish", [
        'public_summary' => 'Document perdu dans la zone d’Abobo. Merci de signaler toute information utile.',
        'expires_in_hours' => 72,
    ])->assertOk()->assertJsonPath('alert.status', 'published');

    $fresh = $alert->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->public_visibility)->toBeTrue()
        ->and($fresh->reviewed_by_account_id)->toBe($admin->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->expires_at)->not->toBeNull();

    expect(AccountAuditEvent::query()
        ->where('action', 'AlertDeclarationPublished')
        ->where('resource_id', $alert->id)
        ->exists())->toBeTrue();

    test()->getJson('/api/alerts/public')
        ->assertOk()
        ->assertJsonPath('alerts.0.id', $alert->id)
        ->assertJsonPath('alerts.0.summary', 'Document perdu dans la zone d’Abobo. Merci de signaler toute information utile.')
        ->assertJsonMissing(['exact_location']);
});

test('rejecting an alert requires a reason that remains visible to its author', function (): void {
    registerAndLogin('alerts-reject-subject@wasplex.test');
    $alert = submitAlertForCurrentMember();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('alerts-reject-admin@wasplex.test');
    $admin = alertsAccountFor('alerts-reject-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.alerts.review']);
    verifyRecentMfaForAlertsTests();

    test()->postJson("/api/admin/alerts/{$alert->id}/reject", [])
        ->assertUnprocessable();

    test()->postJson("/api/admin/alerts/{$alert->id}/reject", [
        'reason' => 'Le lieu est trop vague. Précisez le quartier sans publier l’adresse exacte.',
    ])->assertOk()->assertJsonPath('alert.status', 'rejected');

    $fresh = $alert->fresh();
    expect($fresh->status)->toBe('rejected')
        ->and($fresh->public_visibility)->toBeFalse()
        ->and($fresh->rejection_reason)->toBe('Le lieu est trop vague. Précisez le quartier sans publier l’adresse exacte.')
        ->and($fresh->reviewed_by_account_id)->toBe($admin->id)
        ->and($fresh->reviewed_at)->not->toBeNull();

    expect(AccountAuditEvent::query()
        ->where('action', 'AlertDeclarationRejected')
        ->where('resource_id', $alert->id)
        ->exists())->toBeTrue();

    test()->postJson('/api/logout')->assertNoContent();
    loginExistingAlertsAccount('alerts-reject-subject@wasplex.test');

    test()->getJson('/api/alerts/mine')
        ->assertOk()
        ->assertJsonPath('alerts.0.status', 'rejected')
        ->assertJsonPath('alerts.0.rejection_reason', 'Le lieu est trop vague. Précisez le quartier sans publier l’adresse exacte.');
});

test('feed utility cadence is protected by a dedicated capability and audited', function (): void {
    registerAndLogin('alerts-config-admin@wasplex.test');
    $admin = alertsAccountFor('alerts-config-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.alerts.configuration.manage']);
    verifyRecentMfaForAlertsTests();

    test()->putJson('/api/admin/alerts/feed-settings', [
        'useful_content_interval' => 10,
        'circles_enabled' => true,
        'left_rail_enabled' => true,
        'full_screen_enabled' => true,
        'tips' => [
            ['title' => 'Conseil sécurité', 'body' => 'Ne partagez jamais une position privée dans un contenu public.'],
        ],
    ])->assertOk()->assertJsonPath('updated', true);

    $settings = DB::table('alert_feed_settings')->first();
    expect((int) $settings->useful_content_interval)->toBe(10);

    expect(AccountAuditEvent::query()
        ->where('action', 'AlertFeedSettingsUpdated')
        ->where('resource_id', $settings->id)
        ->exists())->toBeTrue();
});

test('alert review endpoints reject an admin without the dedicated capability', function (): void {
    registerAndLogin('alerts-no-review-cap@wasplex.test');
    $admin = alertsAccountFor('alerts-no-review-cap@wasplex.test');
    grantFounderAccessForTests($admin, []);
    verifyRecentMfaForAlertsTests();

    test()->getJson('/api/admin/alerts')->assertForbidden();
});
