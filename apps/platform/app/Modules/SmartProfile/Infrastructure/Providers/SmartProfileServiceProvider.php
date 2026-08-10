<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Providers;

use App\Modules\SmartProfile\Application\Contracts\AdvertisingConsentContract;
use App\Modules\SmartProfile\Application\Contracts\ProfileTargetingContract;
use App\Modules\SmartProfile\Application\Services\ConsentService;
use App\Modules\SmartProfile\Application\Services\ProfileTargetingService;
use App\Modules\SmartProfile\Console\SeedCatalogCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SmartProfileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdvertisingConsentContract::class, ConsentService::class);
        $this->app->bind(ProfileTargetingContract::class, ProfileTargetingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SeedCatalogCommand::class]);
        }

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
