<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Providers;

use App\Modules\Matching\Application\Contracts\MatchingContract;
use App\Modules\Matching\Application\Services\MatchingService;
use App\Modules\Matching\Console\SeedConfigurationCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MatchingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MatchingContract::class, MatchingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SeedConfigurationCommand::class]);
        }

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
