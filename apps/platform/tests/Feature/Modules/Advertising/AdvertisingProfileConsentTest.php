<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingConsent;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileAnswer;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingPurpose;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Application\Services\IdentityProvisioner;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdvertisingProfileConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_publishes_minimal_safe_catalog_and_grants_user_capabilities(): void
    {
        $account = $this->account();

        $this->artisan('advertising:bootstrap')->assertSuccessful();
        $profile = app(AdvertisingProfileService::class)->profile($account);

        self::assertSame(3, AdvertisingPurpose::query()->count());
        self::assertSame(4, AdvertisingTaxonomy::query()->count());
        self::assertSame(4, AdvertisingProfileQuestion::query()->count());
        self::assertSame(4, $profile['total']);
        self::assertSame(0, $profile['completion']);
        self::assertTrue(CapabilityGrant::query()
            ->where('account_id', $account->id)
            ->where('capability', 'advertising.profile.manage.self')
            ->exists());
        self::assertFalse(AdvertisingTaxonomy::query()->where('sensitive', true)->exists());
    }

    public function test_answer_correction_and_removal_are_append_only_and_keep_provenance(): void
    {
        $account = $this->readyAccount();
        $profiles = app(AdvertisingProfileService::class);
        $first = $profiles->answer($account, 'primary_telecom_network', 'orange');
        $replay = $profiles->answer($account, 'primary_telecom_network', 'orange');
        $corrected = $profiles->answer($account, 'primary_telecom_network', 'mtn');
        $removed = $profiles->remove($account, 'primary_telecom_network');

        self::assertSame($first->id, $replay->id);
        self::assertSame(1, $first->version);
        self::assertSame('declared_by_user', $first->provenance);
        self::assertSame(2, $corrected->version);
        self::assertSame('corrected', $corrected->provenance);
        self::assertSame($first->id, $corrected->replaces_answer_id);
        self::assertSame(3, $removed->version);
        self::assertSame('deleted', $removed->status);
        self::assertSame($corrected->id, $removed->replaces_answer_id);
        self::assertSame(3, AdvertisingProfileAnswer::query()->count());
        self::assertSame([], $profiles->matchingFacts($account));
        self::assertSame(0, $profiles->profile($account)['completion']);
    }

    public function test_withdrawing_smart_profile_consent_blocks_new_matching_immediately(): void
    {
        $account = $this->readyAccount();
        $profiles = app(AdvertisingProfileService::class);
        $consents = app(AdvertisingConsentService::class);

        $profiles->answer($account, 'primary_telecom_network', 'orange');
        $profiles->answer($account, 'mobile_internet_interest', 'yes');
        $profiles->answer($account, 'abidjan_approximate_commune', 'cocody');

        self::assertSame([
            'telecom.network.primary' => 'orange',
            'interest.mobile_internet' => 'yes',
            'geo.ci.abidjan.commune' => 'cocody',
        ], $profiles->matchingFacts($account));

        $withdrawn = $consents->decide(
            $account,
            'smart_profile_usage',
            AdvertisingConsentStatus::Withdrawn,
        );

        self::assertSame(AdvertisingConsentStatus::Withdrawn, $withdrawn->status);
        self::assertSame([], $profiles->matchingFacts($account));
        self::assertSame(4, AdvertisingConsent::query()->where('account_id', $account->id)->count());
        self::assertFalse(collect($consents->summary($account))
            ->firstWhere('code', 'smart_profile_usage')['active']);
    }

    public function test_approximate_territory_is_not_exposed_without_its_separate_consent(): void
    {
        $account = $this->account();
        $this->artisan('advertising:bootstrap')->assertSuccessful();
        $consents = app(AdvertisingConsentService::class);
        $profiles = app(AdvertisingProfileService::class);

        $consents->decide($account, 'advertising_personalization', AdvertisingConsentStatus::Granted);
        $consents->decide($account, 'smart_profile_usage', AdvertisingConsentStatus::Granted);
        $profiles->answer($account, 'primary_telecom_network', 'orange');
        $profiles->answer($account, 'abidjan_approximate_commune', 'cocody');

        self::assertSame([
            'telecom.network.primary' => 'orange',
        ], $profiles->matchingFacts($account));

        $consents->decide(
            $account,
            'approximate_location_targeting',
            AdvertisingConsentStatus::Granted,
        );

        self::assertSame([
            'telecom.network.primary' => 'orange',
            'geo.ci.abidjan.commune' => 'cocody',
        ], $profiles->matchingFacts($account));
    }

    public function test_sensitive_or_non_targetable_taxonomy_cannot_receive_an_answer(): void
    {
        $account = $this->readyAccount();
        $taxonomy = AdvertisingTaxonomy::query()
            ->where('code', 'telecom.network.primary')
            ->firstOrFail();
        $taxonomy->forceFill([
            'allowed_for_targeting' => false,
            'sensitive' => true,
        ])->save();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ne peut pas alimenter le profil publicitaire');

        app(AdvertisingProfileService::class)->answer(
            $account,
            'primary_telecom_network',
            'orange',
        );
    }

    private function readyAccount(): Account
    {
        $account = $this->account();
        $this->artisan('advertising:bootstrap')->assertSuccessful();
        $consents = app(AdvertisingConsentService::class);

        foreach ([
            'advertising_personalization',
            'smart_profile_usage',
            'approximate_location_targeting',
        ] as $purpose) {
            $consents->decide($account, $purpose, AdvertisingConsentStatus::Granted);
        }

        return $account;
    }

    private function account(): Account
    {
        return app(IdentityProvisioner::class)->createAccount(
            displayName: 'Utilisateur P008',
            email: 'p008@example.com',
            password: 'Wasplex-P008-Secure7',
        );
    }
}
