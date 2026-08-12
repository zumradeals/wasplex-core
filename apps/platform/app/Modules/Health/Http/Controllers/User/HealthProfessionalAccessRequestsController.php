<?php

declare(strict_types=1);

namespace App\Modules\Health\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Health\Infrastructure\Models\HealthPatient;
use App\Modules\Health\Infrastructure\Models\HealthProfessionalAccessRequest;
use App\Modules\Identity\Application\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HealthProfessionalAccessRequestsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $patient = HealthPatient::query()
            ->where('account_id', $request->user()?->id)
            ->first();

        if ($patient === null) {
            return new JsonResponse(['requests' => []]);
        }

        $requests = HealthProfessionalAccessRequest::query()
            ->with('organization:id,name,status')
            ->where('patient_id', $patient->id)
            ->latest('requested_at')
            ->limit(50)
            ->get()
            ->map(fn (HealthProfessionalAccessRequest $accessRequest): array => [
                'id' => $accessRequest->id,
                'institution_name' => $accessRequest->organization?->name,
                'purpose' => $accessRequest->purpose,
                'status' => $this->effectiveStatus($accessRequest),
                'justification' => $accessRequest->justification,
                'requested_at' => $accessRequest->requested_at?->toIso8601String(),
                'decided_at' => $accessRequest->decided_at?->toIso8601String(),
                'expires_at' => $accessRequest->expires_at?->toIso8601String(),
                'used_at' => $accessRequest->used_at?->toIso8601String(),
            ])->values();

        return new JsonResponse(['requests' => $requests]);
    }

    public function decide(Request $request, HealthProfessionalAccessRequest $accessRequest): JsonResponse
    {
        $patient = HealthPatient::query()
            ->where('account_id', $request->user()?->id)
            ->firstOrFail();

        if ($accessRequest->patient_id !== $patient->id) {
            abort(404);
        }

        if ($accessRequest->status !== HealthProfessionalAccessRequest::STATUS_PENDING) {
            return new JsonResponse(['message' => 'Cette demande a déjà été traitée.'], 422);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'deny'])],
        ]);

        $approved = $data['decision'] === 'approve';

        $accessRequest->update([
            'status' => $approved
                ? HealthProfessionalAccessRequest::STATUS_APPROVED
                : HealthProfessionalAccessRequest::STATUS_DENIED,
            'decided_by_account_id' => $request->user()?->id,
            'decided_at' => now(),
            'expires_at' => $approved ? now()->addHours(24) : null,
        ]);

        $this->audit->record($approved ? 'HealthProfessionalAccessApproved' : 'HealthProfessionalAccessDenied', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $accessRequest->organization_id,
            'resource_type' => 'health_professional_access_request',
            'resource_id' => $accessRequest->id,
            'patient_id' => $patient->id,
            'purpose' => $accessRequest->purpose,
        ], $request);

        return new JsonResponse([
            'request' => [
                'id' => $accessRequest->id,
                'status' => $accessRequest->status,
                'expires_at' => $accessRequest->expires_at?->toIso8601String(),
            ],
        ]);
    }

    private function effectiveStatus(HealthProfessionalAccessRequest $accessRequest): string
    {
        if ($accessRequest->status === HealthProfessionalAccessRequest::STATUS_APPROVED
            && $accessRequest->expires_at !== null
            && $accessRequest->expires_at->isPast()) {
            return HealthProfessionalAccessRequest::STATUS_EXPIRED;
        }

        return $accessRequest->status;
    }
}
