<?php

namespace Tests\Feature\Modules\EconomicConfiguration;

use App\Modules\EconomicConfiguration\Application\Services\EconomicConfigurationService;
use App\Modules\EconomicConfiguration\Console\Commands\BootstrapEconomicConfiguration;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EconomicConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_four_classes_with_official_quotas_and_weights(): void
    {
        $this->artisan(BootstrapEconomicConfiguration::class)->assertSuccessful();

        $this->assertDatabaseCount('economic_classes', 4);
        $this->assertDatabaseHas('economic_class_versions', ['public_name' => 'Gratuit', 'quota_monthly' => 120, 'weight_basis_points' => 1000]);
        $this->assertDatabaseHas('economic_class_versions', ['public_name' => 'Premium', 'quota_monthly' => 300, 'weight_basis_points' => 2000]);
        $this->assertDatabaseHas('economic_class_versions', ['public_name' => 'Gold', 'quota_monthly' => 600, 'weight_basis_points' => 3500]);
        $this->assertDatabaseHas('economic_class_versions', ['public_name' => 'Platine', 'quota_monthly' => 900, 'weight_basis_points' => 3500]);
    }

    public function test_weight_simulation_requires_exactly_one_hundred_percent(): void
    {
        $service = app(EconomicConfigurationService::class);

        $this->assertTrue($service->simulateWeights([1000, 2000, 3500, 3500])['valid']);
        $this->assertFalse($service->simulateWeights([1000, 2000, 3000, 3000])['valid']);
    }

    public function test_draft_creation_is_versioned(): void
    {
        $class = EconomicClass::query()->create(['code' => 'FREE', 'status' => 'active']);
        $service = app(EconomicConfigurationService::class);

        $first = $service->createDraft($class, ['public_name' => 'Gratuit', 'quota_monthly' => 120, 'weight_basis_points' => 1000, 'targeting_coefficient_basis_points' => 10000]);
        $second = $service->createDraft($class, ['public_name' => 'Essentiel', 'quota_monthly' => 150, 'weight_basis_points' => 1000, 'targeting_coefficient_basis_points' => 10000]);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
    }
}
