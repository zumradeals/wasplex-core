<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AdvertiserStudioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');
        $this->mergeConfigFrom(config_path('advertiser_studio.php'), 'advertiser_studio');

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
