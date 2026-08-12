<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;
use App\Modules\Alerts\Infrastructure\Models\AlertInstitutionalCase;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AlertInstitutionalCasesController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function destinations(): JsonResponse
    {
        $destinations = ProfessionalSpace::query()
            ->with('organization:id,name,status')
            ->where('space_kind', ProfessionalSpace::KIND_SECURITY_INSTITUTION)
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))
            ->orderBy('trade_name')
            ->limit(200)
            ->get()
            ->map(fn (ProfessionalSpace $space): array => [
                'id' => $space->id,
                'organization_id' => $space->organization_id,
                'name' => $space->trade_name ?: $space->organization?->name ?: $space->legal_name,
                'territory' => $space->territory,
            ])
            ->values();

        return new JsonResponse(['destinations' => $destinations]);
    }

    public function refer(Request $request, AlertDeclaration $alert): JsonResponse
    {
        $data = $request->validate([
            'professional_space_id' => ['required', 'string', 'exists:professional_spaces,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1200'],
            'institutional_reference' => ['nullable', 'string', 'max:120'],
        ]);

        if (! in_array($alert->status, ['submitted', 'published'], true) || $alert->resolved_at !== null) {
            return new JsonResponse(['message' => 'Cette alerte ne peut pas être transmise dans son état actuel.'], 422);
        }

        $space = ProfessionalSpace::query()
            ->with('organization')
            ->whereKey($data['professional_space_id'])
            ->where('space_kind', ProfessionalSpace::KIND_SECURITY_INSTITUTION)
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->first();

        if ($space === null || $space->organization === null || $space->organization->status !== 'active') {
            return new JsonResponse(['message' => 'L’institution destinataire doit être une institution de sécurité vérifiée et active.'], 422);
        }

        if (AlertInstitutionalCase::query()
            ->where('alert_declaration_id', $alert->id)
            ->where('organization_id', $space->organization_id)
            ->exists()) {
            return new JsonResponse(['message' => 'Cette alerte a déjà été transmise à cette institution.'], 422);
        }

        $case = DB::transaction(function () use ($alert, $space, $data, $request): AlertInstitutionalCase {
            $case = AlertInstitutionalCase::query()->create([
                'alert_declaration_id' => $alert->id,
                'professional_space_id' => $space->id,
                'organization_id' => $space->organization_id,
                'referred_by_account_id' => $request->user()?->id,
                'status' => AlertInstitutionalCase::STATUS_REFERRED,
                'referral_reason' => trim($data['reason']),
                'institutional_reference' => isset($data['institutional_reference']) ? trim($data['institutional_reference']) : null,
                'referred_at' => now(),
            ]);

            if ($alert->reviewed_at === null) {
                $alert->forceFill([
                    'reviewed_by_account_id' => $request->user()?->id,
                    'reviewed_at' => now(),
                ])->save();
            }

            return $case;
        });

        $this->audit->record('AlertInstitutionalReferralCreated', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'alert_institutional_case',
            'resource_id' => $case->id,
            'alert_declaration_id' => $alert->id,
            'professional_space_id' => $space->id,
            'priority' => $alert->priority,
        ], $request);

        return new JsonResponse([
            'case' => [
                'id' => $case->id,
                'status' => $case->status,
                'organization_id' => $case->organization_id,
                'referred_at' => $case->referred_at?->toIso8601String(),
            ],
        ], 201);
    }
}
