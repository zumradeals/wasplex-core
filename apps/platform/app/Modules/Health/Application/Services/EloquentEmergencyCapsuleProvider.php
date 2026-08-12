<?php

declare(strict_types=1);

namespace App\Modules\Health\Application\Services;

use App\Modules\Health\Application\Contracts\EmergencyCapsuleProvider;
use App\Modules\Health\Infrastructure\Models\HealthConsent;
use App\Modules\Health\Infrastructure\Models\HealthPatient;

final class EloquentEmergencyCapsuleProvider implements EmergencyCapsuleProvider
{
    public function forAccount(string $accountId): ?array
    {
        $patient = HealthPatient::query()
            ->with('capsule')
            ->where('account_id', $accountId)
            ->first();

        if ($patient === null || $patient->capsule === null) {
            return null;
        }

        $consent = HealthConsent::query()
            ->where('patient_id', $patient->id)
            ->where('purpose', 'emergency_capsule')
            ->first();

        if ($consent?->granted !== true) {
            return null;
        }

        $payload = $patient->capsule->payload ?? [];

        return [
            'patient_id' => $patient->id,
            'blood_group' => $payload['blood_group'] ?? null,
            'critical_allergies' => $payload['critical_allergies'] ?? [],
            'critical_conditions' => $payload['critical_conditions'] ?? [],
            'vital_treatments' => $payload['vital_treatments'] ?? [],
            'urgent_instructions' => $payload['urgent_instructions'] ?? null,
            'emergency_contact' => $payload['emergency_contact'] ?? null,
            'reference_care' => $payload['reference_care'] ?? null,
            'provenance' => $patient->capsule->provenance,
            'verified_at' => $patient->capsule->verified_at?->toIso8601String(),
            'last_reviewed_at' => $patient->capsule->last_reviewed_at?->toIso8601String(),
        ];
    }
}
