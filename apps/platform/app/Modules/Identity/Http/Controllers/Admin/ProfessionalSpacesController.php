<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ProfessionalSpacesController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = ProfessionalSpace::query()
            ->with(['organization:id,name,status', 'creator:id,country_code', 'userSpace:id,status,organization_id'])
            ->latest('submitted_at');

        if (is_string($status) && $status !== '') {
            $query->where('verification_status', $status);
        }

        return new JsonResponse([
            'spaces' => $query->limit(100)->get()->map(fn (ProfessionalSpace $space) => $this->serialize($space))->values(),
        ]);
    }

    public function show(Request $request, ProfessionalSpace $professionalSpace): JsonResponse
    {
        $professionalSpace->load(['organization', 'creator', 'userSpace', 'roleAssignments.account', 'servicePoints']);

        $this->audit->record('professional.space.review.viewed', [
            'actor_account_id' => $request->user()?->id,
            'organization_id' => $professionalSpace->organization_id,
            'resource_type' => 'professional_space',
            'resource_id' => $professionalSpace->id,
        ], $request);

        return new JsonResponse(['space' => $this->serialize($professionalSpace, true)]);
    }

    public function decide(Request $request, ProfessionalSpace $professionalSpace): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verify', 'restrict', 'reject', 'suspend'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'restrictions' => ['nullable', 'array'],
        ]);

        if (in_array($data['decision'], ['restrict', 'reject', 'suspend'], true) && trim((string) ($data['reason'] ?? '')) === '') {
            return new JsonResponse(['message' => 'Un motif est obligatoire pour cette décision.'], 422);
        }

        /** @var Account $admin */
        $admin = $request->user();

        DB::transaction(function () use ($professionalSpace, $data, $admin): void {
            $status = match ($data['decision']) {
                'verify' => ProfessionalSpace::VERIFICATION_VERIFIED,
                'restrict' => ProfessionalSpace::VERIFICATION_RESTRICTED,
                'reject' => ProfessionalSpace::VERIFICATION_REJECTED,
                'suspend' => ProfessionalSpace::VERIFICATION_SUSPENDED,
            };

            $professionalSpace->forceFill([
                'verification_status' => $status,
                'reviewed_by_account_id' => $admin->id,
                'review_note' => $data['reason'] ?? null,
                'restrictions' => $data['restrictions'] ?? null,
                'reviewed_at' => now(),
                'verified_at' => $status === ProfessionalSpace::VERIFICATION_VERIFIED ? now() : $professionalSpace->verified_at,
            ])->save();

            $professionalSpace->organization()->update([
                'status' => $status === ProfessionalSpace::VERIFICATION_VERIFIED ? 'active' : $status,
            ]);

            $professionalSpace->userSpace()->update([
                'status' => $status === ProfessionalSpace::VERIFICATION_VERIFIED ? 'active' : $status,
            ]);

            if ($status === ProfessionalSpace::VERIFICATION_VERIFIED) {
                $this->grantVerifiedCapabilities($professionalSpace, $admin);
            } else {
                $this->revokeSensitiveCapabilities($professionalSpace, $admin);
            }
        });

        $this->audit->record('professional.space.review.decided', [
            'actor_account_id' => $admin->id,
            'organization_id' => $professionalSpace->organization_id,
            'resource_type' => 'professional_space',
            'resource_id' => $professionalSpace->id,
            'reason' => $data['reason'] ?? $data['decision'],
        ], $request);

        return new JsonResponse(['space' => $this->serialize($professionalSpace->fresh(['organization', 'userSpace']))]);
    }

    private function grantVerifiedCapabilities(ProfessionalSpace $space, Account $admin): void
    {
        $ownerIds = $space->roleAssignments()
            ->where('status', 'active')
            ->where('role_code', 'organization_owner')
            ->pluck('account_id');

        $capabilities = [
            'professional.space.access',
            'professional.team.manage',
            'professional.service-points.manage',
            ...$this->kindCapabilities($space->space_kind),
        ];

        foreach ($ownerIds as $accountId) {
            foreach ($capabilities as $capability) {
                CapabilityGrant::query()->updateOrCreate([
                    'account_id' => $accountId,
                    'capability_code' => $capability,
                    'scope_type' => CapabilityGrant::SCOPE_ORGANIZATION,
                    'scope_id' => $space->organization_id,
                ], [
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => null,
                    'granted_by' => $admin->id,
                    'revoked_at' => null,
                    'revoked_by' => null,
                ]);
            }
        }
    }

    private function revokeSensitiveCapabilities(ProfessionalSpace $space, Account $admin): void
    {
        CapabilityGrant::query()
            ->where('scope_type', CapabilityGrant::SCOPE_ORGANIZATION)
            ->where('scope_id', $space->organization_id)
            ->where(function ($query): void {
                $query->where('capability_code', 'professional.space.access')
                    ->orWhere('capability_code', 'like', 'professional.alerts.%')
                    ->orWhere('capability_code', 'like', 'professional.health.%');
            })
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => $admin->id,
            ]);
    }

    /** @return array<int, string> */
    private function kindCapabilities(string $kind): array
    {
        return match ($kind) {
            ProfessionalSpace::KIND_SECURITY_INSTITUTION => [
                'professional.alerts.institution.view',
                'professional.alerts.institution.act',
            ],
            ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION => [
                'professional.health.institution.manage',
                'professional.health.access.request',
            ],
            ProfessionalSpace::KIND_HEALTH_PROFESSIONAL => [
                'professional.health.access.request',
                'professional.health.emergency-capsule.request',
            ],
            default => [],
        };
    }

    private function serialize(ProfessionalSpace $space, bool $detail = false): array
    {
        $payload = [
            'id' => $space->id,
            'user_space_id' => $space->user_space_id,
            'organization_id' => $space->organization_id,
            'organization_name' => $space->organization?->name,
            'space_kind' => $space->space_kind,
            'verification_status' => $space->verification_status,
            'legal_name' => $space->legal_name,
            'trade_name' => $space->trade_name,
            'country_code' => $space->country_code,
            'territory' => $space->territory,
            'purposes' => $space->purposes ?? [],
            'restrictions' => $space->restrictions ?? [],
            'review_note' => $space->review_note,
            'submitted_at' => $space->submitted_at?->toIso8601String(),
            'reviewed_at' => $space->reviewed_at?->toIso8601String(),
            'verified_at' => $space->verified_at?->toIso8601String(),
            'space_status' => $space->userSpace?->status,
        ];

        if ($detail) {
            $payload['registration_number'] = $space->registration_number;
            $payload['roles'] = $space->roleAssignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'account_id' => $assignment->account_id,
                'role_code' => $assignment->role_code,
                'status' => $assignment->status,
                'scope' => $assignment->scope,
            ])->values();
            $payload['service_points'] = $space->servicePoints->map(fn ($point) => [
                'id' => $point->id,
                'name' => $point->name,
                'type' => $point->type,
                'city' => $point->city,
                'area_label' => $point->area_label,
                'status' => $point->status,
            ])->values();
        }

        return $payload;
    }
}
