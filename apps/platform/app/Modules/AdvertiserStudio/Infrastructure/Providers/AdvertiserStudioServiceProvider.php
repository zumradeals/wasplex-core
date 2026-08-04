<?php

namespace App\Modules\AdvertiserStudio\Infrastructure\Providers;

use App\Modules\AdvertiserStudio\Console\Commands\BootstrapAdvertiserStudio;
use Illuminate\Support\ServiceProvider;

final class AdvertiserStudioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapAdvertiserStudio::class]);
        }
    }
}
