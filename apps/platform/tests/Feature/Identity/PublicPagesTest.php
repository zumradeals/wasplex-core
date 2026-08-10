<?php

it('renders the public landing page for a guest', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Identity/Landing'));
});

it('uses the public landing as the single login page', function (): void {
    $this->get('/login')->assertRedirect('/');
});

it('redirects an authenticated user space account away from the landing page to its shell', function (): void {
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
        ->assertInertia(fn ($page) => $page->component('Identity/Landing'));
});
