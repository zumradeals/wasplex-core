<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileAnswer;
use App\Modules\SmartProfile\Infrastructure\Models\ProfileTaxonomy;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('smartprofile:seed-catalog');
});

function accountForProfileFacetTests(string $email): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $email))
        ->firstOrFail();
}

it('treats independent possessions as separate targeting facts', function (): void {
    registerAndLogin('profile-facets-possession@example.com');
    $account = accountForProfileFacetTests('profile-facets-possession@example.com');

    $smartphone = ProfileTaxonomy::query()->where('code', 'possession.smartphone')->firstOrFail();
    $twoWheeler = ProfileTaxonomy::query()->where('code', 'possession.deux_roues')->firstOrFail();

    test()->postJson("/api/me/smart-profile/{$smartphone->id}", ['answer' => true])->assertCreated();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll(
        $account->id,
        ['possession.smartphone', 'possession.deux_roues'],
    ))->toBeFalse();

    test()->postJson("/api/me/smart-profile/{$twoWheeler->id}", ['answer' => true])->assertCreated();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll(
        $account->id,
        ['possession.smartphone', 'possession.deux_roues'],
    ))->toBeTrue();
});

it('lets an advertiser target either gender while a member keeps one gender choice', function (): void {
    registerAndLogin('profile-facets-gender@example.com');
    $account = accountForProfileFacetTests('profile-facets-gender@example.com');

    $woman = ProfileTaxonomy::query()->where('code', 'demographic.gender.woman')->firstOrFail();
    $man = ProfileTaxonomy::query()->where('code', 'demographic.gender.man')->firstOrFail();

    test()->postJson("/api/me/smart-profile/{$man->id}", ['answer' => true])->assertCreated();
    test()->postJson("/api/me/smart-profile/{$woman->id}", ['answer' => true])->assertCreated();

    expect(ProfileAnswer::query()
        ->where('account_id', $account->id)
        ->where('profile_taxonomy_id', $man->id)
        ->whereNull('withdrawn_at')
        ->exists())->toBeFalse();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll(
        $account->id,
        ['demographic.gender.woman', 'demographic.gender.man'],
    ))->toBeTrue();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll(
        $account->id,
        ['demographic.gender.man'],
    ))->toBeFalse();
});

it('uses age bands as a single-choice targeting facet', function (): void {
    registerAndLogin('profile-facets-age@example.com');
    $account = accountForProfileFacetTests('profile-facets-age@example.com');

    $young = ProfileTaxonomy::query()->where('code', 'demographic.age.18_24')->firstOrFail();
    $adult = ProfileTaxonomy::query()->where('code', 'demographic.age.25_34')->firstOrFail();

    expect($young->facet)->toBe('demographic.age_band')
        ->and($young->input_type)->toBe(ProfileTaxonomy::INPUT_SINGLE_CHOICE);

    test()->postJson("/api/me/smart-profile/{$young->id}", ['answer' => true])->assertCreated();
    test()->postJson("/api/me/smart-profile/{$adult->id}", ['answer' => true])->assertCreated();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll($account->id, ['demographic.age.25_34']))->toBeTrue()
        ->and(app(ProfileTargetingContract::class)->accountMatchesAll($account->id, ['demographic.age.18_24']))->toBeFalse();
});

it('treats options of a multi-choice interest facet as advertiser OR choices', function (): void {
    registerAndLogin('profile-facets-interests@example.com');
    $account = accountForProfileFacetTests('profile-facets-interests@example.com');

    $formation = ProfileTaxonomy::query()->where('code', 'interest.formation')->firstOrFail();
    test()->postJson("/api/me/smart-profile/{$formation->id}", ['answer' => true])->assertCreated();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll(
        $account->id,
        ['interest.formation', 'interest.mode_beaute'],
    ))->toBeTrue();
});

it('includes car ownership as an independent boolean question', function (): void {
    $car = ProfileTaxonomy::query()->where('code', 'possession.voiture')->firstOrFail();

    expect($car->category)->toBe(ProfileTaxonomy::CATEGORY_POSSESSION)
        ->and($car->facet)->toBe('possession.voiture')
        ->and($car->input_type)->toBe(ProfileTaxonomy::INPUT_BOOLEAN)
        ->and($car->label)->toBe('Possède une voiture');
});

it('suspends country-specific mobile operators from the international default catalog', function (): void {
    expect(ProfileTaxonomy::query()->where('code', 'usage.reseau_orange')->value('status'))
        ->toBe(ProfileTaxonomy::STATUS_SUSPENDED)
        ->and(ProfileTaxonomy::query()->where('code', 'usage.reseau_mtn')->value('status'))
        ->toBe(ProfileTaxonomy::STATUS_SUSPENDED);
});

it('lets a member update the country used by targeting', function (): void {
    registerAndLogin('profile-country@example.com');
    $account = accountForProfileFacetTests('profile-country@example.com');

    test()->patchJson('/api/me/smart-profile/country', ['country_code' => 'sn'])
        ->assertOk()
        ->assertJsonPath('country_code', 'SN');

    expect($account->fresh()->country_code)->toBe('SN');
});
