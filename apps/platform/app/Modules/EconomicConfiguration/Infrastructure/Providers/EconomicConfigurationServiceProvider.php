<?php

namespace App\Modules\EconomicConfiguration\Infrastructure\Providers;

use App\Modules\EconomicConfiguration\Console\Commands\BootstrapEconomicConfiguration;
use Illuminate\Support\ServiceProvider;

final class EconomicConfigurationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapEconomicConfiguration::class]);
        }
    }
}
