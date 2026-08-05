<?php

namespace App\Modules\Distribution\Infrastructure\Providers;

use App\Modules\Distribution\Console\Commands\ExpireAdvertisingDeliveries;
use App\Modules\Distribution\Http\Controllers\AdvertisingDeliveryController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DistributionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::prefix('/api/feed')
            ->name('distribution.feed.')
            ->middleware([
                'web',
                'auth',
                'identity.session',
                'space.kind:user',
                'throttle:60,1',
            ])
            ->group(function (): void {
                Route::post('/sessions', [AdvertisingDeliveryController::class, 'startSession'])
                    ->name('sessions.store');
                Route::post('/sessions/{session}/next', [AdvertisingDeliveryController::class, 'next'])
                    ->name('sessions.next');
                Route::get('/deliveries/{delivery}', [AdvertisingDeliveryController::class, 'show'])
                    ->name('deliveries.show');
                Route::post('/deliveries/{delivery}/release', [AdvertisingDeliveryController::class, 'release'])
                    ->name('deliveries.release');
            });

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireAdvertisingDeliveries::class]);
        }
    }
}
