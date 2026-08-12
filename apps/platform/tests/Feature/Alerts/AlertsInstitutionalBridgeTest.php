<?php

declare(strict_types=1);

use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;
use App\Modules\Alerts\Infrastructure\Models\AlertInstitutionalCase;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use PragmaRX\Google2FA\Google2FA;

function bridgeAlertAccount(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function bridgeAlertMfa(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function bridgeAlertLogin(string $identifier): void
{
    test()->withCredentials();
    $login = test()->postJson('/api/login', [
        'identifier_value' => $identifier,
        'password' => 'Password123!',
    ])->assertOk();

    $sessionCookieName = config('session.cookie');
    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }
}

function bridgeAlertRequestSecuritySpace(): ProfessionalSpace
{
    $response = test()->postJson('/api/professional-spaces', [
        'space_kind' => ProfessionalSpace::KIND_SECURITY_INSTITUTION,
        'legal_name' => 'Unité de sécurité raccordement Alertes',
        'trade_name' => 'Unité Alertes',
        'registration_number' => 'SEC-BRIDGE-001',
        'country_code' => 'CI',
        'territory' => [
            'country_code' => 'CI',
            'city' => 'Abidjan',
        ],
        'purposes' => ['Prise en charge de signalements transmis'],
    ])->assertCreated();

    return ProfessionalSpace::query()->findOrFail($response->json('space.id'));
}

it('forwards a citizen alert only to a verified security institution and reflects proven transitions', function (): void {
    registerAndLogin('bridge-alert-citizen@wasplex.test');
    $citizen = bridgeAlertAccount('bridge-alert-citizen@wasplex.test');

    $alertId = test()->postJson('/api/alerts', [
        'category' => 'person',
        'situation' => 'missing',
        'title' => 'Personne recherchée par sa famille',
        'description' => 'Signalement sérieux nécessitant une vérification par un service habilité.',
        'city' => 'Abidjan',
        'area_label' => 'Abobo',
        'exact_location' => 'Repère privé près du marché principal',
    ])->assertCreated()->json('alert.id');

    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-alert-security-owner@wasplex.test');
    $space = bridgeAlertRequestSecuritySpace();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-alert-admin@wasplex.test');
    $admin = bridgeAlertAccount('bridge-alert-admin@wasplex.test');
    grantFounderAccessForTests($admin, [
        'admin.professional-spaces.view',
        'admin.professional-spaces.decide',
        'admin.alerts.review',
    ]);
    bridgeAlertMfa();

    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", [
        'decision' => 'verify',
    ])->assertOk();

    $caseId = test()->postJson("/api/admin/alerts/{$alertId}/refer", [
        'professional_space_id' => $space->id,
        'reason' => 'Transmission contrôlée pour vérification et intervention terrain.',
        'institutional_reference' => 'DOSSIER-SEC-1001',
    ])->assertCreated()
        ->assertJsonPath('case.status', AlertInstitutionalCase::STATUS_REFERRED)
        ->json('case.id');

    expect(AlertDeclaration::query()->findOrFail($alertId)->exact_location)
        ->toBe('Repère privé près du marché principal');

    test()->postJson('/api/logout')->assertNoContent();
    bridgeAlertLogin('bridge-alert-security-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();

    test()->getJson('/api/professional/alerts')
        ->assertOk()
        ->assertJsonPath('cases.0.id', $caseId)
        ->assertJsonPath('cases.0.alert.exact_location', 'Repère privé près du marché principal');

    test()->postJson("/api/professional/alerts/{$caseId}/transition", [
        'action' => 'acknowledge',
        'institutional_reference' => 'DOSSIER-SEC-1001',
    ])->assertOk()->assertJsonPath('case.status', AlertInstitutionalCase::STATUS_ACKNOWLEDGED);

    test()->postJson("/api/professional/alerts/{$caseId}/transition", [
        'action' => 'start',
    ])->assertOk()->assertJsonPath('case.status', AlertInstitutionalCase::STATUS_IN_PROGRESS);

    test()->postJson("/api/professional/alerts/{$caseId}/transition", [
        'action' => 'resolve',
        'note' => 'Intervention terminée et situation clôturée par le service.',
    ])->assertOk()->assertJsonPath('case.status', AlertInstitutionalCase::STATUS_RESOLVED);

    expect(AlertDeclaration::query()->findOrFail($alertId)->resolved_at)->not->toBeNull();

    test()->postJson('/api/logout')->assertNoContent();
    bridgeAlertLogin('bridge-alert-citizen@wasplex.test');
    test()->getJson('/api/alerts/mine')
        ->assertOk()
        ->assertJsonPath('alerts.0.institutional_status', 'resolved')
        ->assertJsonPath('alerts.0.institutional_case.institution_name', 'Unité Alertes')
        ->assertJsonPath('alerts.0.institutional_case.institutional_reference', 'DOSSIER-SEC-1001');

    expect($citizen->id)->not->toBeNull();
});

it('rejects a referral to an unverified professional space', function (): void {
    registerAndLogin('bridge-alert-unverified-owner@wasplex.test');
    $space = bridgeAlertRequestSecuritySpace();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-alert-referral-citizen@wasplex.test');
    $alertId = test()->postJson('/api/alerts', [
        'category' => 'sos',
        'situation' => 'emergency',
        'title' => 'Urgence de sécurité immédiate',
        'description' => 'Une situation urgente demande une intervention vérifiée et traçable.',
    ])->assertCreated()->json('alert.id');
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-alert-referral-admin@wasplex.test');
    $admin = bridgeAlertAccount('bridge-alert-referral-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.alerts.review']);
    bridgeAlertMfa();

    test()->postJson("/api/admin/alerts/{$alertId}/refer", [
        'professional_space_id' => $space->id,
        'reason' => 'Tentative de transmission vers un espace non vérifié.',
    ])->assertUnprocessable();

    expect(AlertInstitutionalCase::query()->count())->toBe(0);
});
