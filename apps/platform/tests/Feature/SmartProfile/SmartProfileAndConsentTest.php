<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\SmartProfile\Application\Contracts\AdvertisingConsentContract;
use App\Modules\SmartProfile\Application\Services\ConsentPurposeService;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileAnswer;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Artisan::call('smartprofile:seed-catalog');
});

function accountForSmartProfileTests(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function verifyRecentMfaForSmartProfileTests(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

function loginAsAdminForSmartProfileTests(string $email, array $capabilities): void
{
    registerAndLogin($email);
    grantFounderAccessForTests(accountForSmartProfileTests($email), $capabilities);
    verifyRecentMfaForSmartProfileTests();
}

it('lets a user declare and withdraw a profile answer while keeping the history', function (): void {
    registerAndLogin('profile-declare@example.com');
    $account = accountForSmartProfileTests('profile-declare@example.com');

    $categories = test()->getJson('/api/me/smart-profile')->assertOk()->json('categories');
    $taxonomy = collect($categories['interest'])->first();

    test()->postJson("/api/me/smart-profile/{$taxonomy['id']}")->assertCreated();

    $afterDeclare = test()->getJson('/api/me/smart-profile')->assertOk()->json('categories');
    expect(collect($afterDeclare['interest'])->firstWhere('id', $taxonomy['id'])['declared'])->toBeTrue();

    test()->deleteJson("/api/me/smart-profile/{$taxonomy['id']}")->assertOk();

    $afterWithdraw = test()->getJson('/api/me/smart-profile')->assertOk()->json('categories');
    expect(collect($afterWithdraw['interest'])->firstWhere('id', $taxonomy['id'])['declared'])->toBeFalse();

    // History is preserved (append-only), not deleted.
    expect(ProfileAnswer::query()->where('account_id', $account->id)->where('profile_taxonomy_id', $taxonomy['id'])->count())
        ->toBe(1);
    expect(ProfileAnswer::query()->where('account_id', $account->id)->whereNotNull('withdrawn_at')->count())->toBe(1);
});

it('rejects an unknown category — Santé, Alertes, Fonds and KYC cannot be injected as SmartProfile taxonomies', function (): void {
    loginAsAdminForSmartProfileTests('profile-forbidden-admin@wasplex.test', ['admin.smartprofile.taxonomies.manage']);

    foreach (['health', 'alerts', 'funds', 'kyc', 'sante', 'anything_else'] as $forbiddenCategory) {
        test()->postJson('/api/admin/smartprofile/taxonomies', [
            'code' => "forbidden.{$forbiddenCategory}",
            'category' => $forbiddenCategory,
            'label' => 'Test',
        ])->assertStatus(422);
    }

    expect(ProfileTaxonomy::query()->where('code', 'like', 'forbidden.%')->count())->toBe(0);
});

it('refuses SmartProfile administration without the capability', function (): void {
    loginAsAdminForSmartProfileTests('profile-nocap-admin@wasplex.test', []);

    test()->getJson('/api/admin/smartprofile/taxonomies')->assertStatus(403);
    test()->getJson('/api/admin/smartprofile/consent-purposes')->assertStatus(403);
});

it('grants, lists and withdraws a consent with an immutable event history', function (): void {
    registerAndLogin('consent-grant@example.com');

    $consents = test()->getJson('/api/me/consents')->assertOk()->json('consents');
    expect(collect($consents)->firstWhere('code', ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION)['status'])
        ->toBe('not_decided');

    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();

    $afterGrant = test()->getJson('/api/me/consents')->assertOk()->json('consents');
    expect(collect($afterGrant)->firstWhere('code', ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION)['status'])
        ->toBe('granted');

    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/withdraw')->assertOk();

    $afterWithdraw = test()->getJson('/api/me/consents')->assertOk()->json('consents');
    expect(collect($afterWithdraw)->firstWhere('code', ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION)['status'])
        ->toBe('withdrawn');

    $history = test()->getJson('/api/me/consents/history')->assertOk()->json('history');
    expect(collect($history)->pluck('event_type')->all())->toContain('granted', 'withdrawn');
});

it('treats an absent consent decision as a privacy doubt, never as a refusal', function (): void {
    registerAndLogin('consent-not-decided@example.com');
    $account = accountForSmartProfileTests('consent-not-decided@example.com');

    $state = app(AdvertisingConsentContract::class)->stateFor($account->id, ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION);

    expect($state)->toBe(AdvertisingConsentContract::STATE_NOT_DECIDED);
});

it('supersedes prior grants when a new consent text version requires a new decision', function (): void {
    registerAndLogin('consent-supersede@example.com');
    $account = accountForSmartProfileTests('consent-supersede@example.com');

    test()->postJson('/api/me/consents/'.ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION.'/grant')->assertOk();
    expect(app(AdvertisingConsentContract::class)->stateFor($account->id, ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION))
        ->toBe(AdvertisingConsentContract::STATE_ACTIVE);

    test()->postJson('/api/logout')->assertNoContent();

    loginAsAdminForSmartProfileTests('consent-supersede-admin@wasplex.test', ['admin.smartprofile.consents.manage']);

    $version = app(ConsentPurposeService::class)->createDraftVersion([
        'code' => ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION,
        'name' => 'Personnalisation publicitaire (v2)',
        'description' => 'Texte mis à jour.',
        'requires_new_decision' => true,
    ]);
    app(ConsentPurposeService::class)->publish($version->id);

    // Not a refusal — the prior grant is superseded, mapped to a doubt.
    expect(app(AdvertisingConsentContract::class)->stateFor($account->id, ConsentPurpose::CODE_ADVERTISING_PERSONALIZATION))
        ->toBe(AdvertisingConsentContract::STATE_NOT_DECIDED);
});
