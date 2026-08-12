<?php

declare(strict_types=1);

use App\Modules\Alerts\Infrastructure\Models\AlertDeclaration;

it('stores exact location encrypted and never exposes it in the member projection', function (): void {
    registerAndLogin('alerts-member@wasplex.test');

    test()->postJson('/api/alerts', [
        'category' => 'document',
        'situation' => 'lost',
        'title' => 'Carte nationale perdue',
        'description' => 'J’ai perdu ma carte nationale dans le quartier Liby à Abobo.',
        'city' => 'Abidjan',
        'area_label' => 'Abobo',
        'exact_location' => 'Devant la boutique rouge, rue exacte privée',
    ])->assertCreated()
        ->assertJsonPath('alert.status', 'submitted')
        ->assertJsonMissing(['exact_location']);

    $alert = AlertDeclaration::query()->firstOrFail();

    expect($alert->exact_location)->toBe('Devant la boutique rouge, rue exacte privée')
        ->and($alert->getRawOriginal('exact_location'))->not->toContain('boutique rouge')
        ->and($alert->public_visibility)->toBeFalse();

    test()->getJson('/api/alerts/mine')
        ->assertOk()
        ->assertJsonMissing(['exact_location']);
});

it('does not expose a submitted citizen declaration in the public alerts feed', function (): void {
    registerAndLogin('alerts-private@wasplex.test');

    test()->postJson('/api/alerts', [
        'category' => 'object',
        'situation' => 'found',
        'title' => 'Sac retrouvé',
        'description' => 'Un sac noir a été retrouvé près du marché, son contenu reste confidentiel.',
        'city' => 'Abidjan',
        'area_label' => 'Adjamé',
    ])->assertCreated();

    test()->getJson('/api/alerts/public')
        ->assertOk()
        ->assertJsonCount(0, 'alerts');
});

it('classifies SOS as protection priority P0 without any payment field', function (): void {
    registerAndLogin('alerts-sos@wasplex.test');

    test()->postJson('/api/alerts', [
        'category' => 'sos',
        'situation' => 'emergency',
        'title' => 'Accident grave',
        'description' => 'Une personne est blessée après un accident et a besoin d’assistance.',
        'city' => 'Abidjan',
        'area_label' => 'Cocody',
    ])->assertCreated()
        ->assertJsonPath('alert.priority', 'P0');

    expect(AlertDeclaration::query()->firstOrFail()->getAttributes())
        ->not->toHaveKey('price')
        ->not->toHaveKey('wallet_id');
});
