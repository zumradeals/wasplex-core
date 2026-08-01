<?php

use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('activates an advertiser space and keeps both contexts separated', function (): void {
    registerAccount($this);

    $spaceId = $this->postJson('/api/me/spaces/advertiser', [
        'organization_name' => 'GamaDeals',
    ])->assertCreated()->json('data.space_id');

    $advertiser = UserSpace::query()->findOrFail($spaceId);

    expect($advertiser->kind)->toBe(SpaceKind::Advertiser)
        ->and($advertiser->organization?->name)->toBe('GamaDeals')
        ->and(Organization::query()->count())->toBe(1);

    $this->postJson("/api/me/spaces/{$advertiser->id}/switch")
        ->assertOk()
        ->assertJsonPath('data.active_space_id', $advertiser->id);

    expect(AccountSession::query()->firstOrFail()->active_space_id)->toBe($advertiser->id);

    $this->withoutVite();
    $this->get('/studio')->assertOk();
    $this->get('/mon-espace')->assertForbidden();
});

it('denies an expired capability without granting a global role', function (): void {
    registerAccount($this);

    CapabilityGrant::query()
        ->where('capability', 'advertiser.space.activate')
        ->update(['expires_at' => now()->subSecond()]);

    $this->postJson('/api/me/spaces/advertiser', ['organization_name' => 'Refus attendu'])
        ->assertForbidden();
});

it('does not expose one organization to another account', function (): void {
    registerAccount($this, 'owner@example.com');
    $this->postJson('/api/me/spaces/advertiser', ['organization_name' => 'Organisation A'])->assertCreated();
    $organization = Organization::query()->firstOrFail();
    $this->postJson('/api/auth/logout')->assertOk();

    registerAccount($this, 'outsider@example.com');

    $this->postJson("/api/organizations/{$organization->id}/invitations", [
        'identifier' => 'member@example.com',
    ])->assertForbidden();
});
