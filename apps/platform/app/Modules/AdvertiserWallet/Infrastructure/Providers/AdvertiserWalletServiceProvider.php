<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Infrastructure\Providers;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletReconcilablePaymentDirectory;
use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletReservationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AdvertiserWalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdvertiserWalletReservationContract::class, AdvertiserWalletReservationService::class);
        $this->app->tag([AdvertiserWalletReconcilablePaymentDirectory::class], 'reconciliation.directories');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        RateLimiter::for('geniuspay-webhook', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');

        // Deliberately outside the "web" group: server-to-server, signed,
        // no session/CSRF (docs/chantiers/P003-CHANTIER.md).
        Route::prefix('api')
            ->middleware(['throttle:geniuspay-webhook'])
            ->group(__DIR__.'/../../Http/routes/webhook.php');
    }
}
