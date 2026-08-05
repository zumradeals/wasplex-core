<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Services\AccountRegistrationService;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;

test('creating an organization grants its creator an advertiser space and no data leaks to a stranger', function (): void {
    registerAndLogin('advertiser@wasplex.test');

    $organizationId = test()->postJson('/api/organizations', [
        'name' => 'GamaDeals',
        'type' => 'advertiser',
        'country_code' => 'CI',
    ])->assertCreated()->json('id');

    $spaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    expect($spaces)->toHaveCount(2);

    $advertiserSpace = collect($spaces)->firstWhere('space_type', 'advertiser');
    expect($advertiserSpace['organization_id'])->toBe($organizationId);

    test()->postJson("/api/me/spaces/{$advertiserSpace['user_space_id']}/switch")
        ->assertOk()
        ->assertJsonPath('space_type', 'advertiser');

    // A second, unrelated account has no access to the first organization.
    registerAndLogin('stranger@wasplex.test');

    test()->getJson("/api/organizations/{$organizationId}/members")
        ->assertStatus(403)
        ->assertJsonPath('code', 'CAPABILITY_DENIED');

    $strangerSpaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    expect($strangerSpaces)->toHaveCount(1);
});

test('switching to a space without membership is denied', function (): void {
    registerAndLogin('lonely@wasplex.test');

    $owner = app(AccountRegistrationService::class)->register('email', 'owner@wasplex.test', 'Password123!', 'CI');

    $organization = Organization::create([
        'name' => 'AutreOrg',
        'type' => 'advertiser',
        'country_code' => 'CI',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $foreignSpace = UserSpace::create([
        'space_type' => 'advertiser',
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);

    test()->postJson("/api/me/spaces/{$foreignSpace->id}/switch")
        ->assertStatus(403)
        ->assertJsonPath('code', 'SPACE_ACCESS_DENIED');
});
