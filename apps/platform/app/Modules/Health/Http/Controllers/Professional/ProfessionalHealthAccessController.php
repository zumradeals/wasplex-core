<?php

declare(strict_types=1);

namespace App\Modules\Health\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Modules\Health\Application\Contracts\EmergencyCapsuleProvider;
use App\Modules\Health\Infrastructure\Models\HealthAccessEvent;
use App\Modules\Health\Infrastructure\Models\HealthPatient;
use App\Modules\Health\Infrastructure\Models\HealthProfessionalAccessRequest;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProfessionalHealthAccessController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EmergencyCapsuleProvider $capsules,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $space = $this->healthSpace($request);

        $requests = HealthProfessionalAccessRequest::query()
            ->with('patient:id,account_id')
            ->where('organization_id', $space->organization_id)
            ->latest('requested_at')
            ->limit(100)
            ->get()
            ->map(fn (HealthProfessionalAccessRequest $accessRequest): array => $this->requestProjection($accessRequest));

        return new JsonResponse(['requests' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $space = $this->healthSpace($request);
        $data = $request->validate([
            'patient_account_id' => ['required', 'string'],
            'justification' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $patient = HealthPatient::query()
            ->where('account_id', $data['patient_account_id'])
            ->where('status', 'active')
            ->first();

        if ($patient === null) {
            return new JsonResponse(['message' => 'Patient introuvable ou dossier Santé indisponible.'], 404);
        }

        $existing = HealthProfessionalAccessRequest::query()
            ->where('patient_id', $patient->id)
            ->where('organization_id', $space->organization_id)
            ->where('purpose', HealthProfessionalAccessRequest::PURPOSE_CARE_CAPSULE)
            ->where('status', HealthProfessionalAccessRequest::STATUS_PENDING)
            ->latest('requested_at')
            ->first();

        if ($existing !== null) {
            return new JsonResponse(['request' => $this->requestProjection($existing)]);
        }

        $accessRequest = HealthProfessionalAccessRequest::query()->create([
            'patient_id' => $patient->id,
            'professional_space_id' => $space->id,
            'organization_id' => $space->organization_id,
            'requester_account_id' => $request->user()?->id,
            'purpose' => HealthProfessionalAccessRequest::PURPOSE_CARE_CAPSULE,
            'status' => HealthProfessionalAccessRequest::STATUS_PENDING,
            'justification' => trim($data['justification']),
            'requested_at' => now(),
        ]);

        $this->audit->record('HealthProfessionalAccessRequested', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'health_professional_access_request',
            'resource_id' => $accessRequest->id,
            'patient_id' => $patient->id,
            'purpose' => $accessRequest->purpose,
        ], $request);

        return new JsonResponse(['request' => $this->requestProjection($accessRequest)], 201);
    }

    public function capsule(Request $request, HealthProfessionalAccessRequest $accessRequest): JsonResponse
    {
        $space = $this->healthSpace($request);

        if ($accessRequest->organization_id !== $space->organization_id) {
            abort(404);
        }

        if (! $accessRequest->isUsable()) {
            return new JsonResponse(['message' => 'Cette autorisation n’est pas active ou a expiré.'], 403);
        }

        $patient = HealthPatient::query()->with('capsule')->findOrFail($accessRequest->patient_id);
        if ($patient->capsule === null) {
            return new JsonResponse(['message' => 'Aucune capsule Santé disponible.'], 404);
        }

        $payload = $this->capsuleProjection($patient);

        DB::transaction(function () use ($request, $space, $patient, $accessRequest): void {
            HealthAccessEvent::query()->create([
                'patient_id' => $patient->id,
                'actor_account_id' => $request->user()?->id,
                'professional_space_id' => $space->id,
                'organization_id' => $space->organization_id,
                'actor_type' => 'professional',
                'access_type' => 'care_capsule',
                'purpose' => 'care',
                'consent_basis' => 'patient_approval',
                'institution_name' => $space->organization?->name,
                'justification' => 'Accès autorisé par le patient via une demande professionnelle approuvée.',
                'accessed_at' => now(),
            ]);

            $accessRequest->forceFill(['used_at' => now()])->save();
        });

        $this->audit->record('HealthProfessionalCapsuleAccessed', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'health_patient',
            'resource_id' => $patient->id,
            'access_request_id' => $accessRequest->id,
            'consent_basis' => 'patient_approval',
        ], $request);

        return new JsonResponse(['capsule' => $payload]);
    }

    public function emergencyCapsule(Request $request): JsonResponse
    {
        $space = $this->healthSpace($request);
        $data = $request->validate([
            'patient_account_id' => ['required', 'string'],
            'justification' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $capsule = $this->capsules->forAccount($data['patient_account_id']);
        if ($capsule === null) {
            return new JsonResponse([
                'message' => 'Capsule d’urgence indisponible ou consentement d’urgence absent.',
            ], 403);
        }

        $patientId = (string) $capsule['patient_id'];

        HealthAccessEvent::query()->create([
            'patient_id' => $patientId,
            'actor_account_id' => $request->user()?->id,
            'professional_space_id' => $space->id,
            'organization_id' => $space->organization_id,
            'actor_type' => 'professional',
            'access_type' => 'emergency_capsule',
            'purpose' => 'emergency',
            'consent_basis' => 'emergency_consent',
            'institution_name' => $space->organization?->name,
            'justification' => trim($data['justification']),
            'accessed_at' => now(),
        ]);

        $this->audit->record('HealthEmergencyCapsuleAccessed', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'health_patient',
            'resource_id' => $patientId,
            'consent_basis' => 'emergency_consent',
        ], $request);

        unset($capsule['patient_id']);

        return new JsonResponse(['capsule' => $capsule]);
    }

    private function healthSpace(Request $request): ProfessionalSpace
    {
        $space = ProfessionalSpace::query()
            ->with('organization')
            ->whereKey((string) $request->attributes->get('professional_space_id'))
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->whereIn('space_kind', [
                ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
                ProfessionalSpace::KIND_HEALTH_PROFESSIONAL,
            ])
            ->first();

        if ($space === null) {
            abort(403, 'Cet espace professionnel n’est pas un acteur Santé vérifié.');
        }

        return $space;
    }

    /** @return array<string, mixed> */
    private function requestProjection(HealthProfessionalAccessRequest $accessRequest): array
    {
        return [
            'id' => $accessRequest->id,
            'patient_account_id' => $accessRequest->patient?->account_id,
            'purpose' => $accessRequest->purpose,
            'status' => $accessRequest->status,
            'requested_at' => $accessRequest->requested_at?->toIso8601String(),
            'decided_at' => $accessRequest->decided_at?->toIso8601String(),
            'expires_at' => $accessRequest->expires_at?->toIso8601String(),
            'used_at' => $accessRequest->used_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function capsuleProjection(HealthPatient $patient): array
    {
        $payload = $patient->capsule?->payload ?? [];

        return [
            'blood_group' => $payload['blood_group'] ?? null,
            'critical_allergies' => $payload['critical_allergies'] ?? [],
            'critical_conditions' => $payload['critical_conditions'] ?? [],
            'vital_treatments' => $payload['vital_treatments'] ?? [],
            'urgent_instructions' => $payload['urgent_instructions'] ?? null,
            'emergency_contact' => $payload['emergency_contact'] ?? null,
            'reference_care' => $payload['reference_care'] ?? null,
            'provenance' => $patient->capsule?->provenance,
            'verified_at' => $patient->capsule?->verified_at?->toIso8601String(),
            'last_reviewed_at' => $patient->capsule?->last_reviewed_at?->toIso8601String(),
        ];
    }
}
