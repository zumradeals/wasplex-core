<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Support\Facades\DB;

/**
 * Docs/09 #51-#53: an organization is created with a nominative founding
 * member, never a shared generic account. The advertiser space belongs to
 * the organization, not to the creating account.
 */
final class OrganizationRegistrationService
{
    public function register(Account $creator, string $name, string $type, string $countryCode): Organization
    {
        return DB::transaction(function () use ($creator, $name, $type, $countryCode) {
            $organization = Organization::create([
                'name' => $name,
                'type' => $type,
                'country_code' => $countryCode,
                'status' => 'active',
                'created_by' => $creator->id,
            ]);

            OrganizationMembership::create([
                'organization_id' => $organization->id,
                'account_id' => $creator->id,
                'title' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $space = UserSpace::create([
                'space_type' => UserSpace::TYPE_ADVERTISER,
                'organization_id' => $organization->id,
                'status' => 'active',
            ]);

            SpaceMembership::create([
                'user_space_id' => $space->id,
                'account_id' => $creator->id,
                'status' => 'active',
                'is_default' => false,
                'joined_at' => now(),
            ]);

            CapabilityGrant::create([
                'account_id' => $creator->id,
                'capability_code' => 'organization.manage.self',
                'scope_type' => CapabilityGrant::SCOPE_ORGANIZATION,
                'scope_id' => $organization->id,
                'status' => 'active',
                'starts_at' => now(),
                'granted_by' => $creator->id,
            ]);

            return $organization->refresh();
        });
    }
}
