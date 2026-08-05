<?php

declare(strict_types=1);
use App\Modules\Identity\Infrastructure\Models\OrganizationInvitation;

test('an invited account can accept using the matching identifier and token', function (): void {
    registerAndLogin('inviter@wasplex.test');

    $organizationId = test()->postJson('/api/organizations', [
        'name' => 'GamaDeals',
        'type' => 'advertiser',
        'country_code' => 'CI',
    ])->assertCreated()->json('id');

    $invitation = test()->postJson("/api/organizations/{$organizationId}/invitations", [
        'identifier_type' => 'email',
        'identifier_value' => 'teammate@wasplex.test',
        'title' => 'media buyer',
    ])->assertCreated();

    $token = $invitation->json('token');
    $invitationId = $invitation->json('id');

    registerAndLogin('teammate@wasplex.test');

    test()->postJson("/api/organizations/invitations/{$invitationId}/accept", ['token' => $token])
        ->assertOk()
        ->assertJsonPath('organization_id', $organizationId);

    $spaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    expect(collect($spaces)->firstWhere('space_type', 'advertiser')['organization_id'])->toBe($organizationId);
});

test('accepting an invitation with a different identifier is refused', function (): void {
    registerAndLogin('inviter2@wasplex.test');

    $organizationId = test()->postJson('/api/organizations', [
        'name' => 'GamaDeals',
        'type' => 'advertiser',
        'country_code' => 'CI',
    ])->assertCreated()->json('id');

    $invitation = test()->postJson("/api/organizations/{$organizationId}/invitations", [
        'identifier_type' => 'email',
        'identifier_value' => 'teammate2@wasplex.test',
    ])->assertCreated();

    registerAndLogin('someone-else@wasplex.test');

    test()->postJson("/api/organizations/invitations/{$invitation->json('id')}/accept", [
        'token' => $invitation->json('token'),
    ])->assertStatus(422)->assertJsonPath('code', 'INVITATION_INVALID');
});

test('an expired invitation cannot be accepted', function (): void {
    registerAndLogin('inviter3@wasplex.test');

    $organizationId = test()->postJson('/api/organizations', [
        'name' => 'GamaDeals',
        'type' => 'advertiser',
        'country_code' => 'CI',
    ])->assertCreated()->json('id');

    $invitation = test()->postJson("/api/organizations/{$organizationId}/invitations", [
        'identifier_type' => 'email',
        'identifier_value' => 'teammate3@wasplex.test',
    ])->assertCreated();

    OrganizationInvitation::query()
        ->whereKey($invitation->json('id'))
        ->update(['expires_at' => now()->subDay()]);

    registerAndLogin('teammate3@wasplex.test');

    test()->postJson("/api/organizations/invitations/{$invitation->json('id')}/accept", [
        'token' => $invitation->json('token'),
    ])->assertStatus(422)->assertJsonPath('code', 'INVITATION_INVALID');
});
