<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
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

it('keeps real alternatives in the same facet as OR choices', function (): void {
    registerAndLogin('profile-facets-gender@example.com');
    $account = accountForProfileFacetTests('profile-facets-gender@example.com');

    $woman = ProfileTaxonomy::query()->where('code', 'demographic.gender.woman')->firstOrFail();
    test()->postJson("/api/me/smart-profile/{$woman->id}", ['answer' => true])->assertCreated();

    expect(app(ProfileTargetingContract::class)->accountMatchesAll(
        $account->id,
        ['demographic.gender.woman', 'demographic.gender.man'],
    ))->toBeTrue();
});

it('includes car ownership as a distinct smart-profile question', function (): void {
    $car = ProfileTaxonomy::query()->where('code', 'possession.voiture')->firstOrFail();

    expect($car->category)->toBe(ProfileTaxonomy::CATEGORY_POSSESSION);
    expect($car->label)->toBe('Possède une voiture');
});
