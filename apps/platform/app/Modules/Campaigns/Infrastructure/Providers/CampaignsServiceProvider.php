<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Providers;

use App\Modules\Campaigns\Console\SeedPriceCatalogCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CampaignsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedPriceCatalogCommand::class,
            ]);
        }

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
