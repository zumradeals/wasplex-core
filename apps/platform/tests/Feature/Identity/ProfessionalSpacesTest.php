<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountAuditEvent;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\ProfessionalRoleAssignment;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use PragmaRX\Google2FA\Google2FA;

function p019AccountFor(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function p019VerifyRecentMfa(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function p019LoginExisting(string $identifier, string $password = 'Password123!'): void
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

function p019RequestProfessionalSpace(array $overrides = []): ProfessionalSpace
{
    $response = test()->postJson('/api/professional-spaces', [
        'space_kind' => 'security_institution',
        'legal_name' => 'Service de protection Abidjan Nord',
        'trade_name' => 'Protection Nord',
        'registration_number' => 'CI-SEC-2026-0001',
        'country_code' => 'CI',
        'territory' => [
            'country_code' => 'CI',
            'city' => 'Abidjan',
            'area_label' => 'Abidjan Nord',
        ],
        'purposes' => ['Traitement de signalements autorisés'],
        ...$overrides,
    ])->assertCreated();

    return ProfessionalSpace::query()->findOrFail($response->json('space.id'));
}

test('a citizen can request a nominative professional space without receiving sensitive access', function (): void {
    registerAndLogin('p019-requester@wasplex.test');
    $account = p019AccountFor('p019-requester@wasplex.test');
    $space = p019RequestProfessionalSpace();

    expect($space->verification_status)->toBe(ProfessionalSpace::VERIFICATION_PENDING)
        ->and($space->userSpace->space_type)->toBe(UserSpace::TYPE_PROFESSIONAL)
        ->and($space->userSpace->status)->toBe('pending_verification')
        ->and($space->organization->status)->toBe('pending_verification')
        ->and($space->registration_number)->toBe('CI-SEC-2026-0001')
        ->and($space->getRawOriginal('registration_number'))->not->toContain('CI-SEC-2026-0001');

    expect(ProfessionalRoleAssignment::query()
        ->where('professional_space_id', $space->id)
        ->where('account_id', $account->id)
        ->where('role_code', 'organization_owner')
        ->exists())->toBeTrue();

    expect(CapabilityGrant::query()
        ->where('account_id', $account->id)
        ->where('scope_id', $space->organization_id)
        ->where('capability_code', 'professional.alerts.institution.view')
        ->exists())->toBeFalse();

    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertForbidden();

    expect(AccountAuditEvent::query()
        ->where('action', 'professional.space.requested')
        ->where('resource_id', $space->id)
        ->exists())->toBeTrue();
});

test('an authorized admin verifies a professional space and activates only its scoped capabilities', function (): void {
    registerAndLogin('p019-subject@wasplex.test');
    $subject = p019AccountFor('p019-subject@wasplex.test');
    $space = p019RequestProfessionalSpace();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('p019-admin@wasplex.test');
    $admin = p019AccountFor('p019-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.professional-spaces.view', 'admin.professional-spaces.decide']);
    p019VerifyRecentMfa();

    test()->getJson("/api/admin/professional-spaces/{$space->id}")
        ->assertOk()
        ->assertJsonPath('space.registration_number', 'CI-SEC-2026-0001');

    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", [
        'decision' => 'verify',
    ])->assertOk()->assertJsonPath('space.verification_status', 'verified');

    $fresh = $space->fresh(['organization', 'userSpace']);
    expect($fresh?->organization?->status)->toBe('active')
        ->and($fresh?->userSpace?->status)->toBe('active')
        ->and($fresh?->verified_at)->not->toBeNull();

    foreach ([
        'professional.space.access',
        'professional.team.manage',
        'professional.service-points.manage',
        'professional.alerts.institution.view',
        'professional.alerts.institution.act',
    ] as $capability) {
        expect(CapabilityGrant::query()
            ->where('account_id', $subject->id)
            ->where('scope_id', $space->organization_id)
            ->where('capability_code', $capability)
            ->where('status', 'active')
            ->exists())->toBeTrue();
    }

    expect(AccountAuditEvent::query()
        ->where('action', 'professional.space.review.decided')
        ->where('resource_id', $space->id)
        ->exists())->toBeTrue();

    test()->postJson('/api/logout')->assertNoContent();
    p019LoginExisting('p019-subject@wasplex.test');
    test()->postJson("/api/me/spaces/{$space->user_space_id}/switch")->assertOk();
    test()->getJson('/api/professional/workspace')
        ->assertOk()
        ->assertJsonPath('space.space_kind', 'security_institution')
        ->assertJsonPath('space.verification_status', 'verified');
});

test('restriction requires a reason and revokes sensitive capabilities', function (): void {
    registerAndLogin('p019-restrict-subject@wasplex.test');
    $subject = p019AccountFor('p019-restrict-subject@wasplex.test');
    $space = p019RequestProfessionalSpace();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('p019-restrict-admin@wasplex.test');
    $admin = p019AccountFor('p019-restrict-admin@wasplex.test');
    grantFounderAccessForTests($admin, ['admin.professional-spaces.view', 'admin.professional-spaces.decide']);
    p019VerifyRecentMfa();

    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", ['decision' => 'verify'])->assertOk();
    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", ['decision' => 'restrict'])
        ->assertUnprocessable();

    test()->postJson("/api/admin/professional-spaces/{$space->id}/decision", [
        'decision' => 'restrict',
        'reason' => 'Territoire à clarifier avant reprise des accès.',
    ])->assertOk()->assertJsonPath('space.verification_status', 'restricted');

    expect(CapabilityGrant::query()
        ->where('account_id', $subject->id)
        ->where('scope_id', $space->organization_id)
        ->where('capability_code', 'professional.alerts.institution.view')
        ->where('status', 'active')
        ->exists())->toBeFalse();
});

test('professional admin endpoints reject an admin without dedicated capabilities', function (): void {
    registerAndLogin('p019-no-cap-admin@wasplex.test');
    $admin = p019AccountFor('p019-no-cap-admin@wasplex.test');
    grantFounderAccessForTests($admin, []);
    p019VerifyRecentMfa();

    test()->getJson('/api/admin/professional-spaces')->assertForbidden();
});
