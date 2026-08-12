<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Services\AuditLogger;
use App\Modules\Identity\Application\Services\ProfessionalSpaceRegistrationService;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProfessionalSpacesController extends Controller
{
    public function __construct(
        private readonly ProfessionalSpaceRegistrationService $registration,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $spaces = ProfessionalSpace::query()
            ->whereHas('userSpace.memberships', fn ($query) => $query
                ->where('account_id', $account->id)
                ->where('status', 'active'))
            ->with(['organization:id,name,status', 'userSpace:id,status,organization_id'])
            ->latest('created_at')
            ->get()
            ->map(fn (ProfessionalSpace $space) => $this->serialize($space));

        return new JsonResponse(['spaces' => $spaces->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $kinds = [
            ProfessionalSpace::KIND_PARTNER,
            ProfessionalSpace::KIND_MERCHANT,
            ProfessionalSpace::KIND_SERVICE_PROVIDER,
            ProfessionalSpace::KIND_SECURITY_INSTITUTION,
            ProfessionalSpace::KIND_HEALTHCARE_INSTITUTION,
            ProfessionalSpace::KIND_HEALTH_PROFESSIONAL,
            ProfessionalSpace::KIND_FINANCIAL_OPERATOR,
            ProfessionalSpace::KIND_FIELD_AGENT,
        ];

        $data = $request->validate([
            'space_kind' => ['required', Rule::in($kinds)],
            'legal_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'registration_number' => ['nullable', 'string', 'max:180'],
            'country_code' => ['required', 'string', 'size:2'],
            'territory' => ['nullable', 'array'],
            'territory.country_code' => ['nullable', 'string', 'size:2'],
            'territory.city' => ['nullable', 'string', 'max:100'],
            'territory.area_label' => ['nullable', 'string', 'max:160'],
            'purposes' => ['nullable', 'array', 'max:10'],
            'purposes.*' => ['string', 'max:80'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        $space = $this->registration->register($account, $data);

        $this->audit->record('professional.space.requested', [
            'actor_account_id' => $account->id,
            'organization_id' => $space->organization_id,
            'resource_type' => 'professional_space',
            'resource_id' => $space->id,
        ], $request);

        return new JsonResponse([
            'space' => $this->serialize($space->load(['organization', 'userSpace'])),
        ], 201);
    }

    private function serialize(ProfessionalSpace $space): array
    {
        return [
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
            'review_note' => $space->review_note,
            'submitted_at' => $space->submitted_at?->toIso8601String(),
            'verified_at' => $space->verified_at?->toIso8601String(),
            'space_status' => $space->userSpace?->status,
        ];
    }
}
