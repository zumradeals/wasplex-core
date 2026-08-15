<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;

it('uses first and last name on the Carte when display name is empty', function (): void {
    registerAndLogin('card-name-fallback@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-name-fallback@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $account->id)->update([
        'first_name' => 'Awa',
        'last_name' => 'Koné',
        'display_name' => null,
    ]);

    test()->postJson('/api/cards')
        ->assertCreated()
        ->assertJsonPath('card.display_name', 'Awa Koné');
});

it('uses the same name fallback when another member verifies a payment recipient', function (): void {
    registerAndLogin('card-name-payee@example.com');
    $beneficiary = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', 'card-name-payee@example.com'))->firstOrFail();
    PersonalProfile::query()->where('account_id', $beneficiary->id)->update([
        'first_name' => 'Moussa',
        'last_name' => 'Traoré',
        'display_name' => null,
    ]);
    $cardId = (string) test()->postJson('/api/cards')->assertCreated()->json('card.id');
    $payload = (string) test()->postJson("/api/cards/{$cardId}/receive-qr", ['amount_minor' => 500])
        ->assertOk()
        ->json('qr.payload');

    test()->postJson('/api/logout')->assertSuccessful();
    registerAndLogin('card-name-payer@example.com');

    test()->postJson('/api/card-scan/resolve', ['payload' => $payload])
        ->assertOk()
        ->assertJsonPath('payment.recipient.display_name', 'Moussa Traoré');
});
