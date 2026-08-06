<?php

declare(strict_types=1);

it('auto-creates a draft profile the first time the Studio is opened', function (): void {
    registerAndLogin('studio-profile-1@example.com');
    createAdvertiserOrganization();

    test()->getJson('/api/advertiser/profile')
        ->assertOk()
        ->assertJsonPath('profile.status', 'draft')
        ->assertJsonPath('profile.advertiser_type', null);
});

it('lets the advertiser choose a subtype and legal identity', function (): void {
    registerAndLogin('studio-profile-2@example.com');
    createAdvertiserOrganization();

    test()->patchJson('/api/advertiser/profile', [
        'advertiser_type' => 'business',
        'legal_name' => 'GamaDeals SARL',
    ])
        ->assertOk()
        ->assertJsonPath('profile.advertiser_type', 'business')
        ->assertJsonPath('profile.legal_name', 'GamaDeals SARL');
});

it('refuses an unauthenticated profile request', function (): void {
    test()->getJson('/api/advertiser/profile')->assertStatus(401);
});

it('refuses access without an active advertiser space', function (): void {
    registerAndLogin('studio-profile-3@example.com');

    test()->getJson('/api/advertiser/profile')->assertStatus(403);
});
