<?php

declare(strict_types=1);

namespace App\Modules\Health\Infrastructure\Providers;

use App\Modules\Health\Application\Contracts\EmergencyCapsuleProvider;
use App\Modules\Health\Application\Services\EloquentEmergencyCapsuleProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class HealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmergencyCapsuleProvider::class, EloquentEmergencyCapsuleProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
