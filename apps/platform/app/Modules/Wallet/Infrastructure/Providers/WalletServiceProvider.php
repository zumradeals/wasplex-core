<?php

namespace App\Modules\Wallet\Infrastructure\Providers;

use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use App\Modules\Wallet\Application\Services\WalletReservationService;
use App\Modules\Wallet\Console\Commands\BootstrapGeniusPayConfiguration;
use App\Modules\Wallet\Console\Commands\BootstrapWallet;
use App\Modules\Wallet\Console\Commands\ExpireWalletReservations;
use App\Modules\Wallet\Console\Commands\RebuildWalletProjections;
use App\Modules\Wallet\Http\Controllers\GeniusPayConfigurationAdminController;
use App\Modules\Wallet\Infrastructure\Payments\GeniusPayGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayContract::class, GeniusPayGateway::class);
        $this->app->bind(WalletReservationContract::class, WalletReservationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::prefix('/administration/paiements/geniuspay')
            ->name('admin.payments.geniuspay.')
            ->middleware(['web', 'auth', 'identity.session', 'space.kind:administration', 'mfa.recent'])
            ->group(function (): void {
                Route::get('/', [GeniusPayConfigurationAdminController::class, 'index'])
                    ->middleware('capability:payment.configuration.view')
                    ->name('index');
                Route::patch('/{environment}', [GeniusPayConfigurationAdminController::class, 'update'])
                    ->middleware(['capability:payment.configuration.manage', 'throttle:10,1'])
                    ->name('update');
                Route::post('/{environment}/tester', [GeniusPayConfigurationAdminController::class, 'test'])
                    ->middleware(['capability:payment.configuration.test', 'throttle:10,1'])
                    ->name('test');
                Route::post('/{environment}/activer', [GeniusPayConfigurationAdminController::class, 'activate'])
                    ->middleware(['capability:payment.configuration.activate', 'throttle:5,1'])
                    ->name('activate');
                Route::post('/reverifier-les-depots', [GeniusPayConfigurationAdminController::class, 'reconcilePending'])
                    ->middleware(['capability:wallet.deposit.reconcile', 'throttle:5,1'])
                    ->name('reconcile');
            });

        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapWallet::class,
                BootstrapGeniusPayConfiguration::class,
                RebuildWalletProjections::class,
                ExpireWalletReservations::class,
            ]);
        }
    }
}
