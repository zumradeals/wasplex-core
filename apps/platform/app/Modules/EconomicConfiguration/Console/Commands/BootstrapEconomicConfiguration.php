<?php

namespace App\Modules\EconomicConfiguration\Console\Commands;

use App\Modules\EconomicConfiguration\Domain\Enums\ConfigurationState;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClass;
use App\Modules\EconomicConfiguration\Infrastructure\Models\EconomicClassVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BootstrapEconomicConfiguration extends Command
{
    protected $signature = 'economic:bootstrap';

    protected $description = 'Initialise les quatre classes économiques canoniques de Wasplex.';

    public function handle(): int
    {
        $classes = [
            'FREE' => ['Gratuit', 120, 1000, 10000],
            'PREMIUM' => ['Premium', 300, 2000, 11500],
            'GOLD' => ['Gold', 600, 3500, 13500],
            'PLATINUM' => ['Platine', 900, 3500, 16000],
        ];

        DB::transaction(function () use ($classes): void {
            foreach ($classes as $code => [$name, $quota, $weight, $coefficient]) {
                $class = EconomicClass::query()->firstOrCreate(['code' => $code], ['status' => 'active']);
                EconomicClassVersion::query()->firstOrCreate(
                    ['economic_class_id' => $class->id, 'version' => 1],
                    [
                        'state' => ConfigurationState::Published,
                        'public_name' => $name,
                        'quota_monthly' => $quota,
                        'weight_basis_points' => $weight,
                        'targeting_coefficient_basis_points' => $coefficient,
                        'features' => ['fund_eligible' => $code !== 'FREE'],
                        'effective_from' => now(),
                        'published_at' => now(),
                    ],
                );
            }
        });

        $this->info('Classes FREE, PREMIUM, GOLD et PLATINUM initialisées.');

        return self::SUCCESS;
    }
}
