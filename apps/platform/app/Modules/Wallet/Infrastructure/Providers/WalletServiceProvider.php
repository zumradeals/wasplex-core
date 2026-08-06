<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Providers;

use App\Modules\Wallet\Application\Contracts\UserWalletContract;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserWalletContract::class, UserWalletQueryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
