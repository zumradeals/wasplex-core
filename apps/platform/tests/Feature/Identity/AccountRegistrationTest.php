<?php

use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\AccessAuditEvent;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\AccountSession;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one universal account with its personal space and revocable session', function (): void {
    $response = $this->withHeader('User-Agent', 'Wasplex Pest on Linux')->postJson('/api/auth/register', [
        'name' => 'Koné Ousmane',
        'email' => 'KONE@example.com',
        'password' => 'Wasplex-P001-Secure7',
        'password_confirmation' => 'Wasplex-P001-Secure7',
    ]);

    $response->assertCreated()->assertJsonPath('data.account_id', fn (string $id): bool => $id !== '');

    $account = Account::query()->firstOrFail();
    $identifier = AccountIdentifier::query()->firstOrFail();
    $space = UserSpace::query()->firstOrFail();
    $session = AccountSession::query()->firstOrFail();

    expect($identifier->normalized_value)->toBe('kone@example.com')
        ->and($identifier->account_id)->toBe($account->id)
        ->and($space->kind)->toBe(SpaceKind::User)
        ->and($session->active_space_id)->toBe($space->id)
        ->and($session->device?->platform)->toBe('Linux')
        ->and(SpaceMembership::query()->where('space_id', $space->id)->where('account_id', $account->id)->exists())->toBeTrue()
        ->and(CapabilityGrant::query()->where('account_id', $account->id)->count())->toBe(3)
        ->and(AccessAuditEvent::query()->where('action', 'account.registered')->exists())->toBeTrue();

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.profile.display_name', 'Koné Ousmane')
        ->assertJsonPath('data.active_space_id', $space->id);
});

it('rejects duplicate normalized identifiers', function (): void {
    registerAccount($this, 'First@example.com');
    $this->postJson('/api/auth/logout')->assertOk();

    $this->postJson('/api/auth/register', registrationPayload('first@EXAMPLE.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('updates only the minimal profile fields allowed by its explicit capability', function (): void {
    $account = registerAccount($this);

    $this->patchJson('/api/me', [
        'display_name' => 'Compte actualisé',
        'locale' => 'fr',
        'timezone' => 'Africa/Abidjan',
    ])->assertOk()
        ->assertJsonPath('data.display_name', 'Compte actualisé')
        ->assertJsonPath('data.timezone', 'Africa/Abidjan');

    expect($account->fresh()?->timezone)->toBe('Africa/Abidjan')
        ->and($account->profile()->value('display_name'))->toBe('Compte actualisé')
        ->and(AccessAuditEvent::query()->where('action', 'account.profile_updated')->exists())->toBeTrue();
});

function registrationPayload(string $email = 'account@example.com'): array
{
    return [
        'name' => 'Compte Wasplex',
        'email' => $email,
        'password' => 'Wasplex-P001-Secure7',
        'password_confirmation' => 'Wasplex-P001-Secure7',
    ];
}

function registerAccount($test, string $email = 'account@example.com'): Account
{
    $test->withHeader('User-Agent', 'Wasplex Pest on Linux')
        ->postJson('/api/auth/register', registrationPayload($email))
        ->assertCreated();

    return Account::query()->whereHas(
        'identifiers',
        fn ($query) => $query->where('normalized_value', strtolower($email)),
    )->firstOrFail();
}
