<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\DepositService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

test('an unauthenticated request to the advertiser wallet API is refused', function (): void {
    test()->getJson('/api/advertiser/wallet')->assertStatus(401);
});

test('an account with no active advertiser space is refused', function (): void {
    registerAndLogin('no-advertiser-space@wasplex.test');

    test()->getJson('/api/advertiser/wallet')
        ->assertStatus(403)
        ->assertJsonPath('code', 'ADVERTISER_SPACE_REQUIRED');
});

test('an invited team member without wallet capabilities cannot view the wallet', function (): void {
    registerAndLogin('wallet-owner@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    $invitation = test()->postJson("/api/organizations/{$organizationId}/invitations", [
        'identifier_type' => 'email',
        'identifier_value' => 'wallet-teammate@wasplex.test',
    ])->assertCreated();

    registerAndLogin('wallet-teammate@wasplex.test');
    test()->postJson("/api/organizations/invitations/{$invitation->json('id')}/accept", ['token' => $invitation->json('token')])
        ->assertOk();

    $spaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    $advertiserSpace = collect($spaces)->firstWhere('organization_id', $organizationId);
    test()->postJson("/api/me/spaces/{$advertiserSpace['user_space_id']}/switch")->assertOk();

    test()->getJson('/api/advertiser/wallet')
        ->assertStatus(403)
        ->assertJsonPath('code', 'CAPABILITY_DENIED');
});

test('an organization only ever sees its own wallet and deposits', function (): void {
    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'SANDBOX_iso1',
            'checkout_url' => 'https://geniuspay.ci/checkout/SANDBOX_iso1',
            'status' => null,
            'environment' => 'sandbox',
        ], 201),
    ]);

    registerAndLogin('org-a-owner@wasplex.test');
    $orgA = createAdvertiserOrganization('Org A');
    $depositA = app(DepositService::class)->createDeposit($orgA, 'a', 5000, 'XOF');

    registerAndLogin('org-b-owner@wasplex.test');
    $orgB = createAdvertiserOrganization('Org B');

    $walletB = test()->getJson('/api/advertiser/wallet')->assertOk();
    expect($walletB->json('wallet.organization_id'))->toBe($orgB);
    expect($walletB->json('wallet.available_minor'))->toBe(0);

    $depositsB = test()->getJson('/api/advertiser/wallet/deposits')->assertOk();
    expect($depositsB->json('deposits'))->toBeEmpty();

    test()->getJson("/api/advertiser/wallet/deposits/{$depositA->id}")->assertStatus(404);
});
