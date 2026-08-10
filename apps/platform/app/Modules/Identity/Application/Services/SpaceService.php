<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Docs/09 #6: switching space recalculates the permission context; no data
 * leaks from one space into another.
 */
final class SpaceService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return Collection<int, SpaceMembership>
     */
    public function accessibleMemberships(Account $account): Collection
    {
        return SpaceMembership::query()
            ->with('space.organization')
            ->where('account_id', $account->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (SpaceMembership $membership): bool => $membership->space->isActive())
            ->values();
    }

    public function activeSpace(Account $account, Request $request): ?UserSpace
    {
        $spaceId = $request->session()->get('active_space_id');

        if ($spaceId !== null) {
            $membership = $this->accessibleMemberships($account)
                ->first(fn (SpaceMembership $m): bool => $m->user_space_id === $spaceId);

            if ($membership !== null) {
                return $membership->space;
            }
        }

        $default = $this->accessibleMemberships($account)->first(fn (SpaceMembership $m): bool => $m->is_default)
            ?? $this->accessibleMemberships($account)->first();

        return $default?->space;
    }

    public function switchTo(Account $account, string $userSpaceId, Request $request): UserSpace
    {
        $membership = $this->accessibleMemberships($account)
            ->first(fn (SpaceMembership $m): bool => $m->user_space_id === $userSpaceId);

        if ($membership === null) {
            throw SpaceAccessDeniedException::forSpace($userSpaceId);
        }

        $request->session()->put('active_space_id', $userSpaceId);

        $this->auditLogger->record('UserSpaceSwitched', [
            'actor_account_id' => $account->id,
            'actor_space_id' => $userSpaceId,
            'organization_id' => $membership->space->organization_id,
        ], $request);

        return $membership->space;
    }

    /**
     * Docs/09 #6 + docs/12 #78-#80: a capability grant authorises *acting*
     * on /admin/*, but only an admin-space membership makes the
     * "Administration" link appear (SpaceSwitcher renders auth.spaces,
     * which only ever reads space_memberships). Granting an admin.*
     * capability through the console must provision that membership too,
     * the same way SeedFounderCommand does for the founder's bootstrap —
     * otherwise every admin invited through the UI keeps the capability
     * to use /admin by URL but never sees the link to it.
     */
    public function ensureAdminMembership(Account $account, Account $grantedBy, ?Request $request = null): SpaceMembership
    {
        return DB::transaction(function () use ($account, $grantedBy, $request): SpaceMembership {
            $existing = SpaceMembership::query()
                ->where('account_id', $account->id)
                ->whereHas('space', fn ($q) => $q->where('space_type', UserSpace::TYPE_ADMIN))
                ->first();

            if ($existing !== null) {
                if ($existing->status !== 'active') {
                    $existing->update(['status' => 'active']);

                    $this->auditLogger->record('AdminSpaceMembershipReactivated', [
                        'actor_account_id' => $grantedBy->id,
                        'resource_type' => 'account',
                        'resource_id' => $account->id,
                    ], $request);
                }

                return $existing;
            }

            $adminSpace = UserSpace::query()
                ->where('space_type', UserSpace::TYPE_ADMIN)
                ->first()
                ?? UserSpace::create(['space_type' => UserSpace::TYPE_ADMIN, 'status' => 'active']);

            $membership = SpaceMembership::create([
                'user_space_id' => $adminSpace->id,
                'account_id' => $account->id,
                'status' => 'active',
                'is_default' => false,
                'joined_at' => now(),
            ]);

            $this->auditLogger->record('AdminSpaceMembershipGranted', [
                'actor_account_id' => $grantedBy->id,
                'resource_type' => 'account',
                'resource_id' => $account->id,
            ], $request);

            return $membership;
        });
    }
}
