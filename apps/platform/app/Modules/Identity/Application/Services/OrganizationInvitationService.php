<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationInvitation;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Docs/09 #53 + docs/chantiers/P001-CHANTIER.md invariant: an invitation
 * can only be accepted by the account that owns the targeted identifier.
 * No mail adapter is wired in P001 — the raw token is returned to the
 * caller (an organization admin) to transmit out of band.
 */
final class OrganizationInvitationService
{
    private const DEFAULT_TTL_HOURS = 72;

    /**
     * @return array{0: OrganizationInvitation, 1: string} the invitation and its one-time plaintext token
     */
    public function invite(
        Organization $organization,
        Account $inviter,
        string $identifierType,
        string $identifierValue,
        ?string $title = null,
    ): array {
        $token = Str::random(40);

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'inviter_account_id' => $inviter->id,
            'invited_identifier_type' => $identifierType,
            'invited_identifier_value' => $identifierValue,
            'title' => $title,
            'token_hash' => Hash::make($token),
            'status' => 'pending',
            'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
        ]);

        return [$invitation, $token];
    }

    public function accept(OrganizationInvitation $invitation, Account $account, string $token): OrganizationMembership
    {
        if (! $invitation->isPending()) {
            throw InvitationInvalidException::forInvitation($invitation->id, 'expired_or_used');
        }

        if (! Hash::check($token, $invitation->token_hash)) {
            throw InvitationInvalidException::forInvitation($invitation->id, 'invalid_token');
        }

        $ownsIdentifier = $account->identifiers()
            ->where('type', $invitation->invited_identifier_type)
            ->where('value', $invitation->invited_identifier_value)
            ->exists();

        if (! $ownsIdentifier) {
            throw InvitationInvalidException::forInvitation($invitation->id, 'identifier_mismatch');
        }

        return DB::transaction(function () use ($invitation, $account) {
            $membership = OrganizationMembership::create([
                'organization_id' => $invitation->organization_id,
                'account_id' => $account->id,
                'title' => $invitation->title,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $space = $invitation->organization->space;

            if ($space !== null) {
                SpaceMembership::firstOrCreate(
                    ['user_space_id' => $space->id, 'account_id' => $account->id],
                    ['status' => 'active', 'is_default' => false, 'joined_at' => now()],
                );
            }

            $invitation->update([
                'status' => 'accepted',
                'accepted_by_account_id' => $account->id,
                'accepted_at' => now(),
            ]);

            return $membership;
        });
    }
}
