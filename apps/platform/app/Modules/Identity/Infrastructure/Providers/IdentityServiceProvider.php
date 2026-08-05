<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Providers;

use App\Modules\Identity\Console\SeedFounderCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        // Session-based auth needs cookies + CSRF, so these JSON endpoints
        // run under the "web" middleware group despite the /api prefix —
        // the same pattern Laravel Breeze/Fortify use for a first-party SPA.
        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');

        Route::middleware('web')
            ->group(__DIR__.'/../../Http/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedFounderCommand::class,
            ]);
        }
    }
}
