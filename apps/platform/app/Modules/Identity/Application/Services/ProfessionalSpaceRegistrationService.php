<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Identity\Infrastructure\Models\ProfessionalRoleAssignment;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\DB;

final class ProfessionalSpaceRegistrationService
{
    /**
     * @param array<string, mixed> $data
     */
    public function register(Account $creator, array $data): ProfessionalSpace
    {
        return DB::transaction(function () use ($creator, $data): ProfessionalSpace {
            $organization = Organization::create([
                'name' => $data['trade_name'] ?: $data['legal_name'],
                'type' => 'professional',
                'country_code' => strtoupper($data['country_code']),
                'status' => 'pending_verification',
                'created_by' => $creator->id,
            ]);

            OrganizationMembership::create([
                'organization_id' => $organization->id,
                'account_id' => $creator->id,
                'title' => 'organization_owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $userSpace = UserSpace::create([
                'space_type' => UserSpace::TYPE_PROFESSIONAL,
                'organization_id' => $organization->id,
                'status' => 'pending_verification',
            ]);

            SpaceMembership::create([
                'user_space_id' => $userSpace->id,
                'account_id' => $creator->id,
                'status' => 'active',
                'is_default' => false,
                'joined_at' => now(),
            ]);

            $professionalSpace = ProfessionalSpace::create([
                'user_space_id' => $userSpace->id,
                'organization_id' => $organization->id,
                'created_by_account_id' => $creator->id,
                'space_kind' => $data['space_kind'],
                'verification_status' => ProfessionalSpace::VERIFICATION_PENDING,
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'country_code' => strtoupper($data['country_code']),
                'territory' => $data['territory'] ?? null,
                'purposes' => $data['purposes'] ?? null,
                'submitted_at' => now(),
            ]);

            ProfessionalRoleAssignment::create([
                'professional_space_id' => $professionalSpace->id,
                'account_id' => $creator->id,
                'role_code' => 'organization_owner',
                'status' => 'active',
                'assigned_by_account_id' => $creator->id,
                'starts_at' => now(),
            ]);

            foreach ([
                'organization.manage.self',
                'professional.space.view.self',
                'professional.organization.manage.self',
            ] as $capability) {
                CapabilityGrant::create([
                    'account_id' => $creator->id,
                    'capability_code' => $capability,
                    'scope_type' => CapabilityGrant::SCOPE_ORGANIZATION,
                    'scope_id' => $organization->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'granted_by' => $creator->id,
                ]);
            }

            return $professionalSpace->refresh();
        });
    }
}
