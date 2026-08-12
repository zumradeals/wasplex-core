<?php

declare(strict_types=1);

namespace App\Modules\Health\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Health\Infrastructure\Models\HealthConsent;
use App\Modules\Health\Infrastructure\Models\HealthEmergencyCapsule;
use App\Modules\Health\Infrastructure\Models\HealthPatient;
use App\Modules\Health\Infrastructure\Models\HealthRepresentative;
use App\Modules\Identity\Application\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HealthController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        $patient = HealthPatient::query()
            ->with(['capsule', 'consents', 'representatives' => fn ($query) => $query->latest(), 'accessEvents' => fn ($query) => $query->latest('accessed_at')->limit(30)])
            ->where('account_id', $request->user()?->id)
            ->first();

        if ($patient === null) {
            return new JsonResponse([
                'patient' => null,
                'capsule' => null,
                'emergency_consent' => false,
                'representatives' => [],
                'accesses' => [],
            ]);
        }

        $consent = $patient->consents->firstWhere('purpose', 'emergency_capsule');
        $capsule = $patient->capsule;

        return new JsonResponse([
            'patient' => [
                'id' => $patient->id,
                'status' => $patient->status,
                'territory' => $patient->territory,
                'origin' => $patient->origin,
                'verification_level' => $patient->verification_level,
            ],
            'capsule' => $capsule === null ? null : [
                'blood_group' => $capsule->payload['blood_group'] ?? null,
                'critical_allergies' => $capsule->payload['critical_allergies'] ?? [],
                'critical_conditions' => $capsule->payload['critical_conditions'] ?? [],
                'vital_treatments' => $capsule->payload['vital_treatments'] ?? [],
                'urgent_instructions' => $capsule->payload['urgent_instructions'] ?? null,
                'emergency_contact' => $capsule->payload['emergency_contact'] ?? null,
                'reference_care' => $capsule->payload['reference_care'] ?? null,
                'provenance' => $capsule->provenance,
                'verified_at' => $capsule->verified_at?->toIso8601String(),
                'last_reviewed_at' => $capsule->last_reviewed_at?->toIso8601String(),
            ],
            'emergency_consent' => $consent?->granted ?? false,
            'representatives' => $patient->representatives->map(fn (HealthRepresentative $representative): array => [
                'id' => $representative->id,
                'name' => $representative->representative_name,
                'phone' => $representative->representative_phone,
                'relationship' => $representative->relationship,
                'scope' => $representative->scope,
                'status' => $representative->status,
                'proof_status' => $representative->proof_status,
            ])->values(),
            'accesses' => $patient->accessEvents->map(fn ($event): array => [
                'id' => $event->id,
                'actor_type' => $event->actor_type,
                'access_type' => $event->access_type,
                'purpose' => $event->purpose,
                'institution_name' => $event->institution_name,
                'accessed_at' => $event->accessed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function updateCapsule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'blood_group' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'critical_allergies' => ['nullable', 'array', 'max:10'],
            'critical_allergies.*' => ['string', 'max:120'],
            'critical_conditions' => ['nullable', 'array', 'max:10'],
            'critical_conditions.*' => ['string', 'max:160'],
            'vital_treatments' => ['nullable', 'array', 'max:10'],
            'vital_treatments.*' => ['string', 'max:160'],
            'urgent_instructions' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:120'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:60'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:80'],
            'reference_care' => ['nullable', 'array'],
            'reference_care.name' => ['nullable', 'string', 'max:160'],
            'reference_care.type' => ['nullable', 'string', 'max:80'],
            'reference_care.phone' => ['nullable', 'string', 'max:60'],
        ]);

        $patient = $this->patientFor($request);

        $payload = [
            'blood_group' => $data['blood_group'] ?? null,
            'critical_allergies' => array_values($data['critical_allergies'] ?? []),
            'critical_conditions' => array_values($data['critical_conditions'] ?? []),
            'vital_treatments' => array_values($data['vital_treatments'] ?? []),
            'urgent_instructions' => $data['urgent_instructions'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'reference_care' => $data['reference_care'] ?? null,
        ];

        HealthEmergencyCapsule::query()->updateOrCreate(
            ['patient_id' => $patient->id],
            [
                'payload' => $payload,
                'provenance' => 'self_declared',
                'verified_at' => null,
                'last_reviewed_at' => now(),
            ],
        );

        $this->auditLogger->record('HealthEmergencyCapsuleUpdated', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'health_patient',
            'resource_id' => $patient->id,
            'provenance' => 'self_declared',
            'allergies_count' => count($payload['critical_allergies']),
            'conditions_count' => count($payload['critical_conditions']),
            'treatments_count' => count($payload['vital_treatments']),
        ], $request);

        return new JsonResponse(['updated' => true]);
    }

    public function updateEmergencyConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'granted' => ['required', 'boolean'],
        ]);

        $patient = $this->patientFor($request);
        $granted = (bool) $data['granted'];

        HealthConsent::query()->updateOrCreate(
            ['patient_id' => $patient->id, 'purpose' => 'emergency_capsule'],
            [
                'granted' => $granted,
                'version' => 'v1',
                'granted_at' => $granted ? now() : null,
                'revoked_at' => $granted ? null : now(),
            ],
        );

        $this->auditLogger->record($granted ? 'HealthEmergencyConsentGranted' : 'HealthEmergencyConsentRevoked', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'health_patient',
            'resource_id' => $patient->id,
            'purpose' => 'emergency_capsule',
        ], $request);

        return new JsonResponse(['granted' => $granted]);
    }

    public function addRepresentative(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:60'],
            'relationship' => ['required', 'string', 'max:80'],
            'scope' => ['required', 'string', 'in:emergency_contact,legal_representation'],
        ]);

        $patient = $this->patientFor($request);

        $representative = HealthRepresentative::query()->create([
            'patient_id' => $patient->id,
            'representative_name' => trim($data['name']),
            'representative_phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'relationship' => trim($data['relationship']),
            'scope' => $data['scope'],
            'status' => 'pending',
            'proof_status' => 'unverified',
        ]);

        $this->auditLogger->record('HealthRepresentativeAdded', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'health_representative',
            'resource_id' => $representative->id,
            'patient_id' => $patient->id,
            'scope' => $representative->scope,
        ], $request);

        return new JsonResponse(['representative' => ['id' => $representative->id, 'status' => 'pending']], 201);
    }

    public function suspendRepresentative(Request $request, string $representativeId): JsonResponse
    {
        $patient = HealthPatient::query()->where('account_id', $request->user()?->id)->firstOrFail();
        $representative = HealthRepresentative::query()
            ->where('patient_id', $patient->id)
            ->whereKey($representativeId)
            ->firstOrFail();

        $representative->update([
            'status' => 'suspended',
            'ends_at' => now(),
        ]);

        $this->auditLogger->record('HealthRepresentativeSuspended', [
            'actor_account_id' => $request->user()?->id,
            'resource_type' => 'health_representative',
            'resource_id' => $representative->id,
            'patient_id' => $patient->id,
        ], $request);

        return new JsonResponse(['suspended' => true]);
    }

    private function patientFor(Request $request): HealthPatient
    {
        $account = $request->user();

        return HealthPatient::query()->firstOrCreate(
            ['account_id' => $account?->id],
            [
                'status' => 'active',
                'territory' => $account?->country_code,
                'origin' => 'self',
                'verification_level' => 'self_declared',
            ],
        );
    }
}
