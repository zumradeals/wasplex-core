<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the technical foundation page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});
