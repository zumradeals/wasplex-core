<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Infrastructure\Providers;

use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserLiveBudgetReservationContract;
use App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract;
use App\Modules\AdvertiserWallet\Application\Services\AdvertiserLiveBudgetReservationService;
use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletReconcilablePaymentDirectory;
use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletReservationService;
use App\Modules\AdvertiserWallet\Infrastructure\Models\GeniusPayConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class AdvertiserWalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdvertiserWalletReservationContract::class, AdvertiserWalletReservationService::class);
        $this->app->bind(AdvertiserLiveBudgetReservationContract::class, AdvertiserLiveBudgetReservationService::class);
        $this->app->tag([AdvertiserWalletReconcilablePaymentDirectory::class], 'reconciliation.directories');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        // Composer's package:discover boots providers before CI has prepared
        // its database credentials. Database-backed GeniusPay settings are an
        // optional override, so an unavailable database must simply fall back
        // to services.geniuspay values from the environment.
        try {
            if (Schema::hasTable('geniuspay_configurations')) {
                $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();
                if ($configuration !== null) {
                    config([
                        'services.geniuspay.environment' => 'sandbox',
                        'services.geniuspay.api_key' => $configuration->api_key,
                        'services.geniuspay.api_secret' => $configuration->api_secret,
                        'services.geniuspay.webhook_secret' => $configuration->webhook_secret,
                    ]);
                }
            }
        } catch (QueryException) {
            // Database not available yet (notably during Composer discovery).
            // Keep the environment configuration and let normal runtime boot
            // load the database override once PostgreSQL is reachable.
        }

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
