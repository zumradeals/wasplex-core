<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('protects the user feed page behind authentication', function (): void {
    $this->get('/mon-espace/pour-toi')->assertRedirect('/connexion');
});

it('renders the P009-C feed with server-owned wallet and endpoint contracts', function (): void {
    $account = registerAccount($this, 'feed-p009c@example.com');
    $this->withoutVite();

    $this->get('/mon-espace/pour-toi')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('User/Feed')
            ->where('account.id', $account->id)
            ->where('wallet.balances.available', 0)
            ->where('feedConfig.defaultMarket', 'CI')
            ->where('feedConfig.mediaLoadTimeoutMs', 15000)
            ->where('endpoints.sessions', '/api/feed/sessions')
            ->where('endpoints.prepare', '/api/feed/sessions/__SESSION__/prepare')
            ->where('endpoints.confirm', '/api/feed/deliveries/__DELIVERY__/confirm')
            ->where('endpoints.release', '/api/feed/deliveries/__DELIVERY__/release')
            ->where('endpoints.dismiss', '/api/feed/deliveries/__DELIVERY__/dismiss')
            ->has('spaces', 1));
});
