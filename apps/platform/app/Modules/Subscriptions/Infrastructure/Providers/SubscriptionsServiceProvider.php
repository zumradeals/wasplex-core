<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Providers;

use App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract;
use App\Modules\Subscriptions\Application\Contracts\SubscriptionsReportingContract;
use App\Modules\Subscriptions\Application\Services\EconomicClassCatalogService;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaContract;
use App\Modules\Subscriptions\Application\Services\SubscriptionQuotaService;
use App\Modules\Subscriptions\Application\Services\SubscriptionsReconcilablePaymentDirectory;
use App\Modules\Subscriptions\Application\Services\SubscriptionsReportingService;
use App\Modules\Subscriptions\Console\ApplyScheduledDowngradesCommand;
use App\Modules\Subscriptions\Console\ExpireOverdueSubscriptionsCommand;
use App\Modules\Subscriptions\Console\SeedEconomicCatalogCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SubscriptionQuotaContract::class, SubscriptionQuotaService::class);
        $this->app->bind(EconomicClassCatalogContract::class, EconomicClassCatalogService::class);
        $this->app->tag([SubscriptionsReconcilablePaymentDirectory::class], 'reconciliation.directories');
        $this->app->bind(SubscriptionsReportingContract::class, SubscriptionsReportingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        $this->commands([
            SeedEconomicCatalogCommand::class,
            ApplyScheduledDowngradesCommand::class,
            ExpireOverdueSubscriptionsCommand::class,
        ]);

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');

        Route::prefix('api')
            ->middleware(['throttle:geniuspay-webhook'])
            ->group(__DIR__.'/../../Http/routes/webhook.php');
    }
}
