<?php

use App\Modules\Identity\Domain\Enums\MembershipStatus;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\AccessAuditEvent;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\OrganizationInvitation;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('turns an invitation into an organization-scoped membership and advertiser space', function (): void {
    registerAccount($this, 'owner@example.com');
    $ownerSpaceId = $this->postJson('/api/me/spaces/advertiser', [
        'organization_name' => 'Organisation Partagée',
    ])->assertCreated()->json('data.space_id');

    $this->postJson("/api/me/spaces/{$ownerSpaceId}/switch")->assertOk();
    $organization = Organization::query()->firstOrFail();

    $token = $this->postJson("/api/organizations/{$organization->id}/invitations", [
        'identifier' => 'member@example.com',
    ])->assertCreated()->json('data.token');

    $this->postJson('/api/auth/logout')->assertOk();
    $member = registerAccount($this, 'member@example.com');

    $memberSpaceId = $this->postJson("/api/invitations/{$token}/accept")
        ->assertOk()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.membership_status', MembershipStatus::Active->value)
        ->json('data.space_id');

    $memberSpace = UserSpace::query()->findOrFail($memberSpaceId);

    expect($memberSpace->account_id)->toBe($member->id)
        ->and($memberSpace->organization_id)->toBe($organization->id)
        ->and($memberSpace->kind)->toBe(SpaceKind::Advertiser)
        ->and(OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('account_id', $member->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists())->toBeTrue()
        ->and(CapabilityGrant::query()
            ->where('account_id', $member->id)
            ->where('space_id', $memberSpace->id)
            ->where('capability', 'advertiser.space.view')
            ->exists())->toBeTrue()
        ->and(OrganizationInvitation::query()->firstOrFail()->status)->toBe('accepted')
        ->and(AccessAuditEvent::query()->where('action', 'organization.invitation_accepted')->exists())->toBeTrue();

    $this->postJson("/api/invitations/{$token}/accept")->assertNotFound();
});

it('refuses an invitation whose identifier does not belong to the authenticated account', function (): void {
    registerAccount($this, 'owner@example.com');
    $ownerSpaceId = $this->postJson('/api/me/spaces/advertiser', [
        'organization_name' => 'Organisation Privée',
    ])->assertCreated()->json('data.space_id');
    $this->postJson("/api/me/spaces/{$ownerSpaceId}/switch")->assertOk();

    $organization = Organization::query()->firstOrFail();
    $token = $this->postJson("/api/organizations/{$organization->id}/invitations", [
        'identifier' => 'expected@example.com',
    ])->assertCreated()->json('data.token');

    $this->postJson('/api/auth/logout')->assertOk();
    registerAccount($this, 'other@example.com');

    $this->postJson("/api/invitations/{$token}/accept")->assertForbidden();
});
