<?php

it('renders the public guest feed at the root', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/GuestFeed')
            ->where('simulatedRewardMinor', 30)
        );
});

it('serves the existing visual auth experience for login and registration', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/Landing')
            ->where('initialMode', 'login')
        );

    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/Landing')
            ->where('initialMode', 'register')
        );
});

it('redirects an authenticated user space account away from the public feed to its shell', function (): void {
    registerAndLogin('landing-user@example.com');

    $this->get('/')->assertRedirect('/app');
});

it('serves the technical status page at /status', function (): void {
    $this->get('/status')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Technical'));
});

it('no longer serves the technical page at the root', function (): void {
    $this->get('/')
        ->assertInertia(fn ($page) => $page->component('Identity/GuestFeed'));
});
