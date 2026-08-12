<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Identity\Infrastructure\Models\ProfessionalRoleAssignment;
use App\Modules\Identity\Infrastructure\Models\ProfessionalServicePoint;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ProfessionalWorkspaceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): JsonResponse
    {
        $space = $this->resolveSpace($request);
        /** @var Account $account */
        $account = $request->user();

        $roles = $space->roleAssignments()
            ->where('account_id', $account->id)
            ->where('status', 'active')
            ->get(['id', 'role_code', 'scope', 'starts_at', 'ends_at']);

        $members = OrganizationMembership::query()
            ->where('organization_id', $space->organization_id)
            ->where('status', 'active')
            ->with('account.identifiers')
            ->get()
            ->map(fn (OrganizationMembership $membership) => [
                'membership_id' => $membership->id,
                'account_id' => $membership->account_id,
                'title' => $membership->title,
                'joined_at' => $membership->joined_at?->toIso8601String(),
            ]);

        return new JsonResponse([
            'space' => [
                'id' => $space->id,
                'organization_id' => $space->organization_id,
                'organization_name' => $space->organization?->name,
                'space_kind' => $space->space_kind,
                'verification_status' => $space->verification_status,
                'territory' => $space->territory,
                'purposes' => $space->purposes ?? [],
                'restrictions' => $space->restrictions ?? [],
                'verified_at' => $space->verified_at?->toIso8601String(),
            ],
            'my_roles' => $roles,
            'members' => $members->values(),
            'service_points' => $space->servicePoints()
                ->orderBy('name')
                ->get()
                ->map(fn (ProfessionalServicePoint $point) => [
                    'id' => $point->id,
                    'name' => $point->name,
                    'type' => $point->type,
                    'country_code' => $point->country_code,
                    'city' => $point->city,
                    'area_label' => $point->area_label,
                    'address' => $point->address,
                    'status' => $point->status,
                ])->values(),
        ]);
    }

    public function storeServicePoint(Request $request): JsonResponse
    {
        $space = $this->resolveSpace($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'max:48'],
            'country_code' => ['required', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'area_label' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'coordinates' => ['nullable', 'array'],
            'coordinates.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'coordinates.lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $point = $space->servicePoints()->create([
            ...$data,
            'country_code' => strtoupper($data['country_code']),
            'status' => 'active',
        ]);

        $this->audit->record('professional.service-point.created', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'professional_service_point',
            'resource_id' => $point->id,
        ], $request);

        return new JsonResponse(['id' => $point->id], 201);
    }

    public function assignRole(Request $request): JsonResponse
    {
        $space = $this->resolveSpace($request);
        $data = $request->validate([
            'account_id' => ['required', 'string', 'exists:accounts,id'],
            'role_code' => ['required', Rule::in([
                'organization_admin',
                'operations_manager',
                'supervisor',
                'agent',
                'reviewer',
                'finance_manager',
                'health_professional',
                'security_officer',
                'partner_cashier',
                'auditor',
                'read_only',
            ])],
            'scope' => ['nullable', 'array'],
        ]);

        $isMember = OrganizationMembership::query()
            ->where('organization_id', $space->organization_id)
            ->where('account_id', $data['account_id'])
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            return new JsonResponse(['message' => 'Ce compte doit d’abord rejoindre l’organisation.'], 422);
        }

        $capabilities = $this->roleCapabilities($space, $data['role_code']);
        if ($capabilities === null) {
            return new JsonResponse(['message' => 'Ce rôle n’est pas compatible avec ce type d’espace professionnel.'], 422);
        }

        /** @var Account $assigner */
        $assigner = $request->user();

        $assignment = DB::transaction(function () use ($space, $data, $assigner, $capabilities): ProfessionalRoleAssignment {
            $assignment = ProfessionalRoleAssignment::query()->updateOrCreate([
                'professional_space_id' => $space->id,
                'account_id' => $data['account_id'],
                'role_code' => $data['role_code'],
            ], [
                'status' => 'active',
                'scope' => $data['scope'] ?? null,
                'starts_at' => now(),
                'ends_at' => null,
                'assigned_by_account_id' => $assigner->id,
            ]);

            foreach ($capabilities as $capability) {
                CapabilityGrant::query()->updateOrCreate([
                    'account_id' => $data['account_id'],
                    'capability_code' => $capability,
                    'scope_type' => CapabilityGrant::SCOPE_ORGANIZATION,
                    'scope_id' => $space->organization_id,
                ], [
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null,
                    'granted_by' => $assigner->id,
                ]);
            }

            return $assignment;
        });

        $this->audit->record('professional.role.assigned', [
            'actor_account_id' => $assigner->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'professional_role_assignment',
            'resource_id' => $assignment->id,
        ], $request);

        return new JsonResponse([
            'assignment_id' => $assignment->id,
            'capabilities' => $capabilities,
        ]);
    }

    /**
     * @return array<int, string>|null
     */
    private function roleCapabilities(ProfessionalSpace $space, string $roleCode): ?array
    {
        $base = ['professional.space.access'];
        $fundsView = $this->supportsFundsPartner($space) ? ['professional.funds.quote.view'] : [];
        $fundsRespond = $this->supportsFundsPartner($space)
            ? ['professional.funds.quote.view', 'professional.funds.quote.respond']
            : [];

        return match ($roleCode) {
            'organization_admin' => [
                ...$base,
                'professional.team.manage',
                'professional.service-points.manage',
                ...$fundsRespond,
            ],
            'security_officer' => $space->space_kind === ProfessionalSpace::KIND_SECURITY_INSTITUTION
                ? [...$base, 'professional.alerts.institution.view', 'professional.alerts.institution.act']
                : null,
            'health_professional' => in_array($space->space_kind, [
                ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
                ProfessionalSpace::KIND_HEALTH_PROFESSIONAL,
            ], true)
                ? [...$base, 'professional.health.access.request', 'professional.health.emergency-capsule.request']
                : null,
            'operations_manager', 'reviewer', 'partner_cashier' => [...$base, ...$fundsRespond],
            'finance_manager', 'read_only' => [...$base, ...$fundsView],
            'supervisor', 'agent', 'auditor' => $base,
            default => null,
        };
    }

    private function supportsFundsPartner(ProfessionalSpace $space): bool
    {
        return in_array($space->space_kind, [
            ProfessionalSpace::KIND_PARTNER,
            ProfessionalSpace::KIND_MERCHANT,
            ProfessionalSpace::KIND_SERVICE_PROVIDER,
            ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
            ProfessionalSpace::KIND_FINANCIAL_OPERATOR,
        ], true);
    }

    private function resolveSpace(Request $request): ProfessionalSpace
    {
        return ProfessionalSpace::query()
            ->with('organization')
            ->whereKey((string) $request->attributes->get('professional_space_id'))
            ->where('verification_status', ProfessionalSpace::VERIFICATION_VERIFIED)
            ->firstOrFail();
    }
}
