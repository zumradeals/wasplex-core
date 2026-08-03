<?php

namespace App\Modules\Wallet\Infrastructure\Providers;

use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Contracts\WalletReservationContract;
use App\Modules\Wallet\Application\Services\WalletReservationService;
use App\Modules\Wallet\Console\Commands\BootstrapWallet;
use App\Modules\Wallet\Console\Commands\ExpireWalletReservations;
use App\Modules\Wallet\Console\Commands\RebuildWalletProjections;
use App\Modules\Wallet\Infrastructure\Payments\GeniusPayGateway;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapWallet::class,
                RebuildWalletProjections::class,
                ExpireWalletReservations::class,
            ]);
        }
    }
}
