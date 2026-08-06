<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure\Providers;

use App\Modules\Reconciliation\Application\Services\ReconciliationService;
use App\Modules\Reconciliation\Console\RunReconciliationCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ReconciliationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Each source module (AdvertiserWallet, Subscriptions) tags its own
        // ReconcilablePaymentDirectoryContract implementation under
        // 'reconciliation.directories' in its own ServiceProvider — this
        // module only knows the tag name and the shared contract, never a
        // source module's concrete class (docs/chantiers/
        // P011-B-RAPPROCHEMENT.md §4).
        $this->app->when(ReconciliationService::class)
            ->needs('$directories')
            ->give(fn ($app) => iterator_to_array($app->tagged('reconciliation.directories')));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([RunReconciliationCommand::class]);
        }

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
