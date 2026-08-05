<?php

namespace App\Modules\ValueEngine\Infrastructure\Providers;

use App\Modules\ValueEngine\Console\Commands\BootstrapAdvertisingValueEngine;
use App\Modules\ValueEngine\Console\Commands\ExpireValueAttempts;
use Illuminate\Support\ServiceProvider;

final class ValueEngineServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapAdvertisingValueEngine::class,
                ExpireValueAttempts::class,
            ]);
        }
    }
}
