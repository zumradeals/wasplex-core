<?php

declare(strict_types=1);

use App\Modules\Health\Infrastructure\Models\HealthAccessEvent;
use App\Modules\Health\Infrastructure\Models\HealthProfessionalAccessRequest;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use PragmaRX\Google2FA\Google2FA;

function bridgeHealthAccount(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function bridgeHealthMfa(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function bridgeHealthLogin(string $identifier): void
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

function bridgeHealthRequestSpace(): ProfessionalSpace
{
    $response = test()->postJson('/api/professional-spaces', [
        'space_kind' => ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
        'legal_name' => 'Centre de santé raccordement Wasplex',
        'trade_name' => 'Centre Santé Bridge',
        'registration_number' => 'HEALTH-BRIDGE-001',
        'country_code' => 'CI',
        'territory' => [
            'country_code' => 'CI',
            'city' => 'Abidjan',
        ],
        'purposes' => ['Soins autorisés par le patient'],
    ])->assertCreated();

    return ProfessionalSpace::query()->findOrFail($response->json('space.id'));
}

it('requires patient approval before a verified health organization can read the care capsule', function (): void {
    registerAndLogin('bridge-health-patient@wasplex.test');
    $patientAccount = bridgeHealthAccount('bridge-health-patient@wasplex.test');

    test()->putJson('/api/health/me/capsule', [
        'blood_group' => 'B+',
        'critical_allergies' => ['Aspirine'],
        'critical_conditions' => ['Hypertension'],
        'vital_treatments' => ['Traitement antihypertenseur'],
        'urgent_instructions' => 'Surveiller la tension artérielle.',
    ])->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-health-owner@wasplex.test');
    $space = bridgeHealthRequestSpace();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-health-admin@wasplex.test');
    $admin = bridgeHealthAccount('bridge-health-admin@wasplex.test');
    grantFounderAccessForTests($admin, [
        'admin.professional-spaces.view',
        'admin.professional-spaces.decide',
    ]);
    bridgeHealthMfa();
    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", ['decision' => 'verify'])
        ->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    bridgeHealthLogin('bridge-health-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();

    $requestId = test()->postJson('/api/professional/health/access-requests', [
        'patient_account_id' => $patientAccount->id,
        'justification' => 'Consultation médicale nécessitant la capsule d’urgence déclarée par le patient.',
    ])->assertCreated()
        ->assertJsonPath('request.status', HealthProfessionalAccessRequest::STATUS_PENDING)
        ->json('request.id');

    test()->getJson("/api/professional/health/access-requests/{$requestId}/capsule")
        ->assertForbidden();

    test()->postJson('/api/logout')->assertNoContent();
    bridgeHealthLogin('bridge-health-patient@wasplex.test');

    test()->getJson('/api/health/me/access-requests')
        ->assertOk()
        ->assertJsonPath('requests.0.id', $requestId)
        ->assertJsonPath('requests.0.institution_name', 'Centre de santé raccordement Wasplex')
        ->assertJsonPath('requests.0.status', HealthProfessionalAccessRequest::STATUS_PENDING);

    test()->postJson("/api/health/me/access-requests/{$requestId}/decision", [
        'decision' => 'approve',
    ])->assertOk()
        ->assertJsonPath('request.status', HealthProfessionalAccessRequest::STATUS_APPROVED);

    test()->postJson('/api/logout')->assertNoContent();
    bridgeHealthLogin('bridge-health-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();

    test()->getJson("/api/professional/health/access-requests/{$requestId}/capsule")
        ->assertOk()
        ->assertJsonPath('capsule.blood_group', 'B+')
        ->assertJsonPath('capsule.critical_allergies.0', 'Aspirine');

    expect(HealthAccessEvent::query()
        ->where('organization_id', $space->organization_id)
        ->where('actor_type', 'professional')
        ->where('access_type', 'care_capsule')
        ->where('consent_basis', 'patient_approval')
        ->exists())->toBeTrue();
});

it('allows emergency capsule access only when the patient enabled emergency consent', function (): void {
    registerAndLogin('bridge-health-emergency-patient@wasplex.test');
    $patientAccount = bridgeHealthAccount('bridge-health-emergency-patient@wasplex.test');
    test()->putJson('/api/health/me/capsule', [
        'blood_group' => 'O-',
        'critical_allergies' => ['Pénicilline'],
        'critical_conditions' => [],
        'vital_treatments' => [],
    ])->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-health-emergency-owner@wasplex.test');
    $space = bridgeHealthRequestSpace();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('bridge-health-emergency-admin@wasplex.test');
    $admin = bridgeHealthAccount('bridge-health-emergency-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.professional-spaces.decide']);
    bridgeHealthMfa();
    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", ['decision' => 'verify'])->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    bridgeHealthLogin('bridge-health-emergency-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();
    test()->postJson('/api/professional/health/emergency-capsule', [
        'patient_account_id' => $patientAccount->id,
        'justification' => 'Urgence médicale déclarée au point de soins nécessitant les informations vitales.',
    ])->assertForbidden();

    test()->postJson('/api/logout')->assertNoContent();
    bridgeHealthLogin('bridge-health-emergency-patient@wasplex.test');
    test()->putJson('/api/health/me/consents/emergency-capsule', ['granted' => true])
        ->assertOk();
    test()->postJson('/api/logout')->assertNoContent();

    bridgeHealthLogin('bridge-health-emergency-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();
    test()->postJson('/api/professional/health/emergency-capsule', [
        'patient_account_id' => $patientAccount->id,
        'justification' => 'Urgence médicale déclarée au point de soins nécessitant les informations vitales.',
    ])->assertOk()
        ->assertJsonPath('capsule.blood_group', 'O-')
        ->assertJsonPath('capsule.critical_allergies.0', 'Pénicilline');

    expect(HealthAccessEvent::query()
        ->where('organization_id', $space->organization_id)
        ->where('access_type', 'emergency_capsule')
        ->where('consent_basis', 'emergency_consent')
        ->exists())->toBeTrue();
});
