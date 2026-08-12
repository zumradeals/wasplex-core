<?php

declare(strict_types=1);

use App\Modules\Health\Application\Contracts\EmergencyCapsuleProvider;
use App\Modules\Health\Infrastructure\Models\HealthEmergencyCapsule;
use App\Modules\Health\Infrastructure\Models\HealthPatient;
use App\Modules\Health\Infrastructure\Models\HealthRepresentative;

it('stores the citizen emergency capsule encrypted and exposes it only through emergency consent', function (): void {
    $account = registerAndLogin('health-member@wasplex.test');

    test()->putJson('/api/health/me/capsule', [
        'blood_group' => 'O+',
        'critical_allergies' => ['Pénicilline'],
        'critical_conditions' => ['Asthme sévère'],
        'vital_treatments' => ['Ventoline'],
        'urgent_instructions' => 'Prévenir le contact d’urgence.',
        'emergency_contact' => [
            'name' => 'Awa Koné',
            'phone' => '+2250102030405',
            'relationship' => 'Sœur',
        ],
    ])->assertOk()->assertJsonPath('updated', true);

    $patient = HealthPatient::query()->where('account_id', $account->id)->firstOrFail();
    $capsule = HealthEmergencyCapsule::query()->where('patient_id', $patient->id)->firstOrFail();

    expect($capsule->payload['blood_group'])->toBe('O+')
        ->and($capsule->payload['critical_allergies'])->toBe(['Pénicilline'])
        ->and($capsule->getRawOriginal('payload'))->not->toContain('Pénicilline');

    $provider = app(EmergencyCapsuleProvider::class);
    expect($provider->forAccount($account->id))->toBeNull();

    test()->putJson('/api/health/me/consents/emergency-capsule', ['granted' => true])
        ->assertOk()
        ->assertJsonPath('granted', true);

    $projection = $provider->forAccount($account->id);

    expect($projection)->not->toBeNull()
        ->and($projection['blood_group'])->toBe('O+')
        ->and($projection['critical_allergies'])->toBe(['Pénicilline'])
        ->and($projection)->not->toHaveKey('advertising_profile')
        ->and($projection)->not->toHaveKey('wallet');
});

it('marks self-entered medical information as declared and never medically verified', function (): void {
    registerAndLogin('health-provenance@wasplex.test');

    test()->putJson('/api/health/me/capsule', [
        'blood_group' => 'A+',
        'critical_allergies' => [],
        'critical_conditions' => [],
        'vital_treatments' => [],
    ])->assertOk();

    test()->getJson('/api/health/me')
        ->assertOk()
        ->assertJsonPath('capsule.provenance', 'self_declared')
        ->assertJsonPath('capsule.verified_at', null);
});

it('keeps representatives pending until a future verification flow exists', function (): void {
    registerAndLogin('health-representative@wasplex.test');

    test()->postJson('/api/health/me/representatives', [
        'name' => 'Mariam Koné',
        'phone' => '+2250504030201',
        'relationship' => 'Mère',
        'scope' => 'legal_representation',
    ])->assertCreated()
        ->assertJsonPath('representative.status', 'pending');

    $representative = HealthRepresentative::query()->firstOrFail();

    expect($representative->proof_status)->toBe('unverified')
        ->and($representative->representative_name)->toBe('Mariam Koné')
        ->and($representative->getRawOriginal('representative_name'))->not->toContain('Mariam');

    test()->postJson('/api/health/me/representatives/'.$representative->id.'/suspend')
        ->assertOk()
        ->assertJsonPath('suspended', true);

    expect($representative->fresh()?->status)->toBe('suspended');
});

it('requires authentication for citizen health data', function (): void {
    test()->getJson('/api/health/me')->assertUnauthorized();
});
