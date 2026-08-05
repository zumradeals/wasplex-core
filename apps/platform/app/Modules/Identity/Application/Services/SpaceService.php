<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

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
}
