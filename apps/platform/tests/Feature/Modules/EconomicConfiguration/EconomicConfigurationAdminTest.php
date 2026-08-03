<?php

namespace Tests\Feature\Modules\EconomicConfiguration;

use App\Modules\EconomicConfiguration\Application\Services\EconomicConfigurationService;
use App\Modules\EconomicConfiguration\Console\Commands\BootstrapEconomicConfiguration;
use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Http\Middleware\EnsureAccountSessionActive;
use App\Modules\Identity\Http\Middleware\RequireCapability;
use App\Modules\Identity\Http\Middleware\RequireRecentMfa;
use App\Modules\Identity\Http\Middleware\RequireSpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EconomicConfigurationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware([
            EnsureAccountSessionActive::class,
            RequireCapability::class,
            RequireRecentMfa::class,
            RequireSpaceKind::class,
        ]);

        $this->artisan(BootstrapEconomicConfiguration::class)->assertSuccessful();
    }

    public function test_founder_page_renders_the_four_published_classes(): void
    {
        $this->actingAs($this->account())
            ->get(route('admin.economy.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('EconomicConfiguration/AdminConsole')
                ->has('classes', 4)
                ->where('classes.0.code', 'FREE')
                ->where('classes.0.published.state', ConfigurationState::Published->value)
                ->where('classes.0.published.quotaMonthly', 120));
    }

    public function test_founder_can_apply_simple_percentages_and_preserve_technical_values(): void
    {
        $account = $this->account();
        $gold = EconomicClass::query()->where('code', 'GOLD')->firstOrFail();
        $goldBefore = EconomicClassVersion::query()
            ->where('economic_class_id', $gold->id)
            ->where('state', ConfigurationState::Published)
            ->whereNull('effective_to')
            ->firstOrFail();

        $this->actingAs($account)
            ->post(route('admin.economy.distribution.apply'), [
                'percentages' => [
                    'FREE' => 40,
                    'PREMIUM' => 30,
                    'GOLD' => 20,
                    'PLATINUM' => 10,
                ],
            ])
            ->assertRedirect(route('admin.economy.index'))
            ->assertSessionHas('success');

        $weights = EconomicClassVersion::query()
            ->with('economicClass')
            ->where('state', ConfigurationState::Published)
            ->whereNull('effective_to')
            ->get()
            ->mapWithKeys(fn (EconomicClassVersion $version): array => [
                $version->economicClass->code => $version->weight_basis_points,
            ]);

        $goldAfter = EconomicClassVersion::query()
            ->where('economic_class_id', $gold->id)
            ->where('state', ConfigurationState::Published)
            ->whereNull('effective_to')
            ->firstOrFail();

        expect($weights->all())->toBe([
            'FREE' => 4000,
            'PREMIUM' => 3000,
            'GOLD' => 2000,
            'PLATINUM' => 1000,
        ])->and($goldAfter->quota_monthly)->toBe($goldBefore->quota_monthly)
            ->and($goldAfter->targeting_coefficient_basis_points)
            ->toBe($goldBefore->targeting_coefficient_basis_points)
            ->and($goldAfter->features)->toBe($goldBefore->features)
            ->and($goldAfter->created_by_account_id)->toBe($account->id)
            ->and($goldAfter->approved_by_account_id)->toBe($account->id)
            ->and($goldAfter->published_by_account_id)->toBe($account->id);
    }

    public function test_simple_percentage_distribution_rejects_a_total_other_than_one_hundred_percent(): void
    {
        $versionsBefore = EconomicClassVersion::query()->count();

        $this->actingAs($this->account())
            ->from(route('admin.economy.index'))
            ->post(route('admin.economy.distribution.apply'), [
                'percentages' => [
                    'FREE' => 50,
                    'PREMIUM' => 20,
                    'GOLD' => 20,
                    'PLATINUM' => 20,
                ],
            ])
            ->assertRedirect(route('admin.economy.index'))
            ->assertSessionHasErrors('percentages');

        expect(EconomicClassVersion::query()->count())->toBe($versionsBefore);
    }

    public function test_founder_can_version_approve_publish_and_suspend_a_class(): void
    {
        $account = $this->account();
        $economicClass = EconomicClass::query()->where('code', 'FREE')->firstOrFail();

        $this->actingAs($account)
            ->post(route('admin.economy.versions.store', $economicClass), [
                'public_name' => 'Essentiel',
                'quota_monthly' => 150,
                'weight_basis_points' => 1000,
                'targeting_coefficient_basis_points' => 10500,
                'features' => ['fund_eligible' => false],
            ])
            ->assertRedirect(route('admin.economy.index'))
            ->assertSessionHas('success');

        $version = EconomicClassVersion::query()
            ->where('economic_class_id', $economicClass->id)
            ->where('state', ConfigurationState::Draft)
            ->firstOrFail();

        expect($version->created_by_account_id)->toBe($account->id)
            ->and($version->version)->toBe(2);

        $this->actingAs($account)
            ->post(route('admin.economy.versions.approve', $version))
            ->assertRedirect(route('admin.economy.index'));

        $version->refresh();
        expect($version->state)->toBe(ConfigurationState::Approved)
            ->and($version->approved_by_account_id)->toBe($account->id);

        $this->actingAs($account)
            ->post(route('admin.economy.versions.publish', $version))
            ->assertRedirect(route('admin.economy.index'));

        $version->refresh();
        expect($version->state)->toBe(ConfigurationState::Published)
            ->and($version->published_by_account_id)->toBe($account->id)
            ->and($version->effective_from)->not->toBeNull();

        $this->actingAs($account)
            ->post(route('admin.economy.versions.suspend', $version), [
                'reason' => 'Suspension de validation fonctionnelle.',
            ])
            ->assertRedirect(route('admin.economy.index'));

        $version->refresh();
        expect($version->state)->toBe(ConfigurationState::Suspended)
            ->and($version->suspended_by_account_id)->toBe($account->id)
            ->and($version->suspension_reason)->toBe('Suspension de validation fonctionnelle.');
    }

    public function test_founder_can_publish_compensating_weight_changes_atomically(): void
    {
        $account = $this->account();
        $service = app(EconomicConfigurationService::class);
        $free = EconomicClass::query()->where('code', 'FREE')->firstOrFail();
        $premium = EconomicClass::query()->where('code', 'PREMIUM')->firstOrFail();

        $freeVersion = $service->createDraft($free, [
            'public_name' => 'Gratuit',
            'quota_monthly' => 120,
            'weight_basis_points' => 1200,
            'targeting_coefficient_basis_points' => 10000,
            'features' => ['fund_eligible' => false],
        ], $account->id);
        $premiumVersion = $service->createDraft($premium, [
            'public_name' => 'Premium',
            'quota_monthly' => 300,
            'weight_basis_points' => 1800,
            'targeting_coefficient_basis_points' => 11500,
            'features' => ['fund_eligible' => true],
        ], $account->id);

        $service->approve($freeVersion, $account->id);
        $service->approve($premiumVersion, $account->id);

        $this->actingAs($account)
            ->post(route('admin.economy.versions.publish-many'), [
                'version_ids' => [$freeVersion->id, $premiumVersion->id],
            ])
            ->assertRedirect(route('admin.economy.index'))
            ->assertSessionHas('success');

        $freeVersion->refresh();
        $premiumVersion->refresh();

        expect($freeVersion->state)->toBe(ConfigurationState::Published)
            ->and($premiumVersion->state)->toBe(ConfigurationState::Published)
            ->and($freeVersion->published_by_account_id)->toBe($account->id)
            ->and($premiumVersion->published_by_account_id)->toBe($account->id)
            ->and($service->published()->sum('weight_basis_points'))->toBe(10_000)
            ->and($service->published())->toHaveCount(4);
    }

    public function test_server_simulation_returns_a_visible_validation_result(): void
    {
        $this->actingAs($this->account())
            ->post(route('admin.economy.simulate'), [
                'weights' => [1000, 2000, 3500, 3500],
            ])
            ->assertRedirect(route('admin.economy.index'))
            ->assertSessionHas('economic_simulation', [
                'total_basis_points' => 10000,
                'valid' => true,
            ]);
    }

    private function account(): Account
    {
        return Account::query()->create([
            'status' => AccountStatus::Active,
            'password_hash' => str_repeat('x', 60),
            'locale' => 'fr',
            'timezone' => 'Africa/Abidjan',
        ]);
    }
}
