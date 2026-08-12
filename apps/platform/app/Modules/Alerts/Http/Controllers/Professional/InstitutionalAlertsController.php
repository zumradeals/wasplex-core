<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;
use App\Modules\Alerts\Infrastructure\Models\AlertInstitutionalCase;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class InstitutionalAlertsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        $this->assertSecuritySpace($request);

        $cases = AlertInstitutionalCase::query()
            ->with(['alert:id,account_id,category,situation,priority,title,description,city,area_label,exact_location,submitted_at,resolved_at'])
            ->where('organization_id', $organizationId)
            ->latest('referred_at')
            ->limit(100)
            ->get()
            ->map(fn (AlertInstitutionalCase $case): array => $this->projection($case));

        return new JsonResponse(['cases' => $cases]);
    }

    public function transition(Request $request, AlertInstitutionalCase $case): JsonResponse
    {
        $organizationId = (string) $request->attributes->get('professional_organization_id');
        $this->assertSecuritySpace($request);

        if ($case->organization_id !== $organizationId) {
            abort(404);
        }

        $data = $request->validate([
            'action' => ['required', Rule::in(['acknowledge', 'start', 'resolve', 'decline'])],
            'institutional_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = match ($case->status) {
            AlertInstitutionalCase::STATUS_REFERRED => ['acknowledge', 'decline'],
            AlertInstitutionalCase::STATUS_ACKNOWLEDGED => ['start', 'decline'],
            AlertInstitutionalCase::STATUS_IN_PROGRESS => ['resolve'],
            default => [],
        };

        if (! in_array($data['action'], $allowed, true)) {
            return new JsonResponse(['message' => 'Cette transition n’est pas permise pour ce dossier.'], 422);
        }

        if (in_array($data['action'], ['resolve', 'decline'], true) && trim((string) ($data['note'] ?? '')) === '') {
            return new JsonResponse(['message' => 'Une note est obligatoire pour clôturer ou refuser un dossier.'], 422);
        }

        $actorId = $request->user()?->id;

        DB::transaction(function () use ($case, $data, $actorId): void {
            $updates = [
                'assigned_account_id' => $case->assigned_account_id ?: $actorId,
            ];

            if (isset($data['institutional_reference'])) {
                $updates['institutional_reference'] = trim($data['institutional_reference']);
            }

            match ($data['action']) {
                'acknowledge' => $updates += [
                    'status' => AlertInstitutionalCase::STATUS_ACKNOWLEDGED,
                    'acknowledged_at' => now(),
                ],
                'start' => $updates += [
                    'status' => AlertInstitutionalCase::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ],
                'resolve' => $updates += [
                    'status' => AlertInstitutionalCase::STATUS_RESOLVED,
                    'resolution_note' => trim((string) $data['note']),
                    'resolved_at' => now(),
                ],
                'decline' => $updates += [
                    'status' => AlertInstitutionalCase::STATUS_DECLINED,
                    'resolution_note' => trim((string) $data['note']),
                    'resolved_at' => now(),
                ],
            };

            $case->update($updates);

            if ($data['action'] === 'resolve') {
                AlertDeclaration::query()
                    ->whereKey($case->alert_declaration_id)
                    ->whereNull('resolved_at')
                    ->update([
                        'resolved_at' => now(),
                        'public_visibility' => false,
                    ]);
            }
        });

        $this->audit->record('AlertInstitutionalCaseTransitioned', [
            'actor_account_id' => $actorId,
            'organization_id' => $organizationId,
            'resource_type' => 'alert_institutional_case',
            'resource_id' => $case->id,
            'alert_declaration_id' => $case->alert_declaration_id,
            'action' => $data['action'],
            'status' => $case->fresh()->status,
        ], $request);

        return new JsonResponse(['case' => $this->projection($case->fresh('alert'))]);
    }

    private function assertSecuritySpace(Request $request): void
    {
        $space = ProfessionalSpace::query()
            ->whereKey((string) $request->attributes->get('professional_space_id'))
            ->where('space_kind', ProfessionalSpace::KIND_SECURITY_INSTITUTION)
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->first();

        if ($space === null) {
            abort(403, 'Cet espace professionnel n’est pas une institution de sécurité vérifiée.');
        }
    }

    /** @return array<string, mixed> */
    private function projection(AlertInstitutionalCase $case): array
    {
        $alert = $case->alert;

        return [
            'id' => $case->id,
            'status' => $case->status,
            'institutional_reference' => $case->institutional_reference,
            'referred_at' => $case->referred_at?->toIso8601String(),
            'acknowledged_at' => $case->acknowledged_at?->toIso8601String(),
            'started_at' => $case->started_at?->toIso8601String(),
            'resolved_at' => $case->resolved_at?->toIso8601String(),
            'alert' => $alert === null ? null : [
                'id' => $alert->id,
                'category' => $alert->category,
                'situation' => $alert->situation,
                'priority' => $alert->priority,
                'title' => $alert->title,
                'description' => $alert->description,
                'city' => $alert->city,
                'area_label' => $alert->area_label,
                'exact_location' => $alert->exact_location,
                'submitted_at' => $alert->submitted_at?->toIso8601String(),
            ],
        ];
    }
}
