<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\OrganizationMembership;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use PragmaRX\Google2FA\Google2FA;

function p019RoleAccount(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function p019RoleVerifyMfa(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function p019RoleLoginExisting(string $identifier, string $password = 'Password123!'): void
{
    test()->withCredentials();
    $login = test()->postJson('/api/login', [
        'identifier_value' => $identifier,
        'password' => $password,
    ])->assertOk();

    $sessionCookieName = config('session.cookie');
    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }
}

function p019RoleRequestSpace(string $kind): ProfessionalSpace
{
    $response = test()->postJson('/api/professional-spaces', [
        'space_kind' => $kind,
        'legal_name' => 'Organisation P019 de test',
        'trade_name' => 'P019 Test',
        'registration_number' => 'P019-ROLE-001',
        'country_code' => 'CI',
        'territory' => [
            'country_code' => 'CI',
            'city' => 'Abidjan',
        ],
        'purposes' => ['Test de capacités professionnelles'],
    ])->assertCreated();

    return ProfessionalSpace::query()->findOrFail($response->json('space.id'));
}

function p019RoleVerifySpace(ProfessionalSpace $space): void
{
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('p019-role-admin@wasplex.test');
    $admin = p019RoleAccount('p019-role-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.professional-spaces.view', 'admin.professional-spaces.decide']);
    p019RoleVerifyMfa();

    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", [
        'decision' => 'verify',
    ])->assertOk();

    test()->postJson('/api/logout')->assertNoContent();
}

test('a security officer receives only security capabilities in a verified security institution', function (): void {
    registerAndLogin('p019-security-owner@wasplex.test');
    $owner = p019RoleAccount('p019-security-owner@wasplex.test');
    $space = p019RoleRequestSpace(ProfessionalSpace::KIND_SECURITY_INSTITUTION);
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('p019-security-agent@wasplex.test');
    $agent = p019RoleAccount('p019-security-agent@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    OrganizationMembership::create([
        'organization_id' => $space->organization_id,
        'account_id' => $agent->id,
        'title' => 'agent',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    p019RoleVerifySpace($space);

    p019RoleLoginExisting('p019-security-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();
    test()->postJson('/api/professional/roles', [
        'account_id' => $agent->id,
        'role_code' => 'security_officer',
    ])->assertOk()
        ->assertJsonPath('capabilities.0', 'professional.space.access')
        ->assertJsonFragment(['professional.alerts.institution.view'])
        ->assertJsonFragment(['professional.alerts.institution.act']);

    expect(CapabilityGrant::query()
        ->where('account_id', $agent->id)
        ->where('scope_id', $space->organization_id)
        ->where('capability_code', 'professional.health.access.request')
        ->where('status', 'active')
        ->exists())->toBeFalse();

    expect($owner->id)->not->toBe($agent->id);
});

test('a health role is rejected inside a security institution', function (): void {
    registerAndLogin('p019-incompatible-owner@wasplex.test');
    $space = p019RoleRequestSpace(ProfessionalSpace::KIND_SECURITY_INSTITUTION);
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('p019-incompatible-agent@wasplex.test');
    $agent = p019RoleAccount('p019-incompatible-agent@wasplex.test');
    test()->postJson('/api/logout')->assertNoContent();

    OrganizationMembership::create([
        'organization_id' => $space->organization_id,
        'account_id' => $agent->id,
        'title' => 'agent',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    p019RoleVerifySpace($space);

    p019RoleLoginExisting('p019-incompatible-owner@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();
    test()->postJson('/api/professional/roles', [
        'account_id' => $agent->id,
        'role_code' => 'health_professional',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Ce rôle n’est pas compatible avec ce type d’espace professionnel.');

    expect(CapabilityGrant::query()
        ->where('account_id', $agent->id)
        ->where('scope_id', $space->organization_id)
        ->where('capability_code', 'professional.health.access.request')
        ->exists())->toBeFalse();
});
