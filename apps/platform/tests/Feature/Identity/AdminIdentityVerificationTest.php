<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\IdentityVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;

function kycAccountFor(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function verifyRecentMfaForKycTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function submitKycForCurrentMember(): IdentityVerification
{
    test()->post('/api/me/identity-verification', [
        'first_name' => 'Mariam',
        'last_name' => 'Traoré',
        'birth_date' => '1994-06-18',
        'document_type' => 'national_id',
        'document_country' => 'CI',
        'document_number' => 'CI-KYC-0001',
        'document_front' => UploadedFile::fake()->image('piece.jpg', 900, 600),
        'selfie' => UploadedFile::fake()->image('selfie.jpg', 600, 600),
    ])->assertOk();

    return IdentityVerification::query()->firstOrFail();
}

function loginExistingKycAccount(string $identifier, string $password = 'Password123!'): void
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

test('an authorized admin can inspect private KYC data and approve a submitted dossier', function (): void {
    Storage::fake('local');
    registerAndLogin('kyc-subject@wasplex.test');
    $verification = submitKycForCurrentMember();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('kyc-admin@wasplex.test');
    $admin = kycAccountFor('kyc-admin@wasplex.test');
    grantFounderAccessForTests($admin, [
        'admin.identity-verifications.view',
        'admin.identity-verifications.decide',
    ]);
    verifyRecentMfaForKycTests();

    $list = test()->getJson('/api/admin/identity-verifications')->assertOk()->json('verifications');
    expect(collect($list)->pluck('id'))->toContain($verification->id);

    test()->getJson("/api/admin/identity-verifications/{$verification->id}")
        ->assertOk()
        ->assertJsonPath('verification.document_number', 'CI-KYC-0001')
        ->assertJsonPath('verification.birth_date', '1994-06-18');

    test()->get("/api/admin/identity-verifications/{$verification->id}/documents/document")->assertOk();
    test()->get("/api/admin/identity-verifications/{$verification->id}/documents/selfie")->assertOk();

    test()->postJson("/api/admin/identity-verifications/{$verification->id}/approve")
        ->assertOk()
        ->assertJsonPath('verification.status', 'approved');

    $fresh = $verification->fresh();
    expect($fresh->status)->toBe('approved')
        ->and($fresh->reviewed_by_account_id)->toBe($admin->id)
        ->and($fresh->reviewed_at)->not->toBeNull();
});

test('an authorized admin can reject a dossier with a reason visible to the member', function (): void {
    Storage::fake('local');
    registerAndLogin('kyc-reject-subject@wasplex.test');
    $verification = submitKycForCurrentMember();
    test()->postJson('/api/logout')->assertNoContent();

    registerAndLogin('kyc-reject-admin@wasplex.test');
    grantFounderAccessForTests(kycAccountFor('kyc-reject-admin@wasplex.test'), [
        'admin.identity-verifications.view',
        'admin.identity-verifications.decide',
    ]);
    verifyRecentMfaForKycTests();

    test()->postJson("/api/admin/identity-verifications/{$verification->id}/reject", [
        'reason' => 'La photo de la pièce est trop floue.',
    ])->assertOk()->assertJsonPath('verification.status', 'rejected');

    test()->postJson('/api/logout')->assertNoContent();
    loginExistingKycAccount('kyc-reject-subject@wasplex.test');

    test()->getJson('/api/me/identity-verification')
        ->assertOk()
        ->assertJsonPath('verification.status', 'rejected')
        ->assertJsonPath('verification.rejection_reason', 'La photo de la pièce est trop floue.');
});

test('KYC review endpoints require dedicated capabilities', function (): void {
    registerAndLogin('kyc-no-cap@wasplex.test');
    grantFounderAccessForTests(kycAccountFor('kyc-no-cap@wasplex.test'), []);
    verifyRecentMfaForKycTests();

    test()->getJson('/api/admin/identity-verifications')->assertForbidden();
});
