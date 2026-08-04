<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Application\Services\AdvertisingConsentService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileService;
use App\Modules\Advertising\Application\Services\AdvertisingProfileSignalService;
use App\Modules\Advertising\Domain\Enums\AdvertisingConsentStatus;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingMarket;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingProfileQuestion;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingSector;
use App\Modules\Advertising\Infrastructure\Models\AdvertisingTaxonomy;
use App\Modules\Identity\Application\Services\IdentityProvisioner;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdvertisingProfileIntelligenceRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_refactor_bootstrap_uses_mali_as_default_without_losing_international_structure(): void
    {
        $account = $this->account();

        $this->artisan('advertising:bootstrap-intelligence')->assertSuccessful();

        $mali = AdvertisingMarket::query()->where('code', 'ML')->firstOrFail();
        $ivoryCoast = AdvertisingMarket::query()->where('code', 'CI')->firstOrFail();
        $profile = app(AdvertisingProfileService::class)->profile($account);

        self::assertTrue($mali->is_default);
        self::assertFalse($ivoryCoast->is_default);
        self::assertSame('Africa/Bamako', $mali->timezone);
        self::assertContains('bm', $mali->supported_locales ?? []);
        self::assertGreaterThanOrEqual(13, AdvertisingSector::query()->count());
        self::assertGreaterThanOrEqual(12, AdvertisingProfileQuestion::query()->count());
        self::assertSame('ML', $profile['market']['code']);
        self::assertGreaterThanOrEqual(13, $profile['summary']['availableSectors']);
        self::assertTrue(AdvertisingTaxonomy::query()
            ->where('code', 'automotive.current_relationship')
            ->where('signal_kind', 'status')
            ->exists());
        self::assertSame(
            ['CI'],
            AdvertisingTaxonomy::query()
                ->where('code', 'geo.ci.abidjan.commune')
                ->firstOrFail()
                ->country_codes,
        );
    }

    public function test_ai_proposal_never_enters_matching_before_user_confirmation(): void
    {
        $account = $this->readyAccount();
        $taxonomy = AdvertisingTaxonomy::query()
            ->where('code', 'commerce.shopping.channel_preference')
            ->firstOrFail();
        $signals = app(AdvertisingProfileSignalService::class);
        $profiles = app(AdvertisingProfileService::class);

        $proposal = $signals->proposeDerived(
            account: $account,
            taxonomy: $taxonomy,
            value: ['selected' => 'online'],
            explanation: 'Plusieurs interactions volontaires concernent des offres accessibles en ligne.',
            evidence: ['allowed_events' => 3],
            confidence: 0.85,
            marketCode: 'ML',
        );

        self::assertSame('proposed', $proposal->status);
        self::assertTrue($proposal->requires_user_confirmation);
        self::assertArrayNotHasKey($taxonomy->code, $profiles->matchingFacts($account));

        $confirmed = $signals->decide($account, $proposal, true);
        $facts = $profiles->matchingFacts($account);

        self::assertSame('active', $confirmed->status);
        self::assertFalse($confirmed->requires_user_confirmation);
        self::assertSame(['selected' => 'online'], $facts[$taxonomy->code]);
    }

    public function test_ai_cannot_propose_a_signal_when_taxonomy_forbids_it(): void
    {
        $account = $this->readyAccount();
        $taxonomy = AdvertisingTaxonomy::query()
            ->where('code', 'commerce.shopping.channel_preference')
            ->firstOrFail();
        $taxonomy->forceFill(['ai_usage_mode' => 'forbidden'])->save();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ne peut pas alimenter le Profil intelligent');

        app(AdvertisingProfileSignalService::class)->proposeDerived(
            account: $account,
            taxonomy: $taxonomy,
            value: ['selected' => 'online'],
            explanation: 'Proposition qui doit être refusée.',
            evidence: ['allowed_events' => 2],
            confidence: 0.9,
            marketCode: 'ML',
        );
    }

    private function readyAccount(): Account
    {
        $account = $this->account();
        $this->artisan('advertising:bootstrap-intelligence')->assertSuccessful();
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
            displayName: 'Utilisateur P008-R',
            email: 'p008r@example.com',
            password: 'Wasplex-P008R-Secure7',
        );
    }
}
