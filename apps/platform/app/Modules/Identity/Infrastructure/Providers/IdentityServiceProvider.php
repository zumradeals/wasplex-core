<?php

namespace App\Modules\Identity\Infrastructure\Providers;

use App\Modules\Identity\Console\Commands\BootstrapFounder;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapFounder::class]);
        }
    }
}
