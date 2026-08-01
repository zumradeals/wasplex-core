<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Services\AccessAuditor;
use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\MembershipStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Domain\Events\OrganizationInvitationAccepted;
use App\Modules\Identity\Domain\Events\OrganizationMemberInvited;
use App\Modules\Identity\Domain\Events\UserSpaceCreated;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationInvitation;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OrganizationController
{
    public function __construct(
        private AccessAuditor $auditor,
        private CapabilityService $capabilities,
    ) {}

    public function invite(Request $request, Organization $organization): JsonResponse
    {
        $space = $request->attributes->get('active_space');
        abort_unless($space?->organization_id === $organization->id, 404);

        $validated = $request->validate([
            'identifier' => ['required', 'email:rfc', 'max:255'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        $plainToken = Str::random(64);
        $invitation = DB::transaction(function () use ($request, $organization, $validated, $plainToken) {
            $invitation = $organization->invitations()->create([
                'invited_by_account_id' => $request->user()?->getAuthIdentifier(),
                'identifier' => Str::lower(trim($validated['identifier'])),
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'pending',
                'expires_at' => now()->addDays((int) ($validated['expires_in_days'] ?? 7)),
            ]);

            DB::afterCommit(static fn () => event(new OrganizationMemberInvited($organization->id, $invitation->id)));

            return $invitation;
        });

        $this->auditor->record(
            $request,
            'organization.member_invited',
            'success',
            'organization.members.invite',
            ['organization_id' => $organization->id, 'invitation_id' => $invitation->id],
        );

        return response()->json([
            'data' => [
                'invitation_id' => $invitation->id,
                'token' => $plainToken,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $invitation = OrganizationInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_unless(
            $account->identifiers()->where('normalized_value', $invitation->identifier)->exists(),
            403,
        );
        abort_if(
            $account->id === $invitation->invited_by_account_id,
            409,
            'Le créateur ne peut pas accepter sa propre invitation.',
        );

        $space = DB::transaction(function () use ($account, $invitation): UserSpace {
            $invitation->forceFill([
                'status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            $organization = Organization::query()->findOrFail($invitation->organization_id);
            $organization->memberships()->updateOrCreate(
                ['account_id' => $account->id],
                ['status' => MembershipStatus::Active, 'title' => 'Membre'],
            );

            $space = $account->spaces()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'kind' => SpaceKind::Advertiser,
                ],
                [
                    'label' => $organization->name,
                    'status' => 'active',
                ],
            );

            $space->memberships()->updateOrCreate(
                ['account_id' => $account->id],
                ['status' => MembershipStatus::Active, 'title' => 'Membre'],
            );

            foreach (['advertiser.space.view', 'organization.members.view'] as $capability) {
                $this->capabilities->grant(
                    account: $account,
                    capability: $capability,
                    grantedBy: $organization->creator,
                    space: $space,
                    organizationId: $organization->id,
                    reason: 'Acceptation d’une invitation d’organisation',
                );
            }

            DB::afterCommit(static function () use ($account, $invitation, $space): void {
                event(new OrganizationInvitationAccepted($invitation->organization_id, $invitation->id, $account->id));
                event(new UserSpaceCreated($account->id, $space->id, SpaceKind::Advertiser->value));
            });

            return $space;
        });

        $this->auditor->record(
            $request,
            'organization.invitation_accepted',
            'success',
            'organization.invitation.accept',
            ['organization_id' => $invitation->organization_id, 'invitation_id' => $invitation->id],
        );

        return response()->json([
            'data' => [
                'organization_id' => $invitation->organization_id,
                'space_id' => $space->id,
                'membership_status' => MembershipStatus::Active->value,
            ],
        ]);
    }
}
