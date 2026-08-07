<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Infrastructure\Providers;

use App\Modules\Ledger\Application\Contracts\LedgerReportingContract;
use App\Modules\Ledger\Application\Services\LedgerCompensationContract;
use App\Modules\Ledger\Application\Services\LedgerCompensationService;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Application\Services\LedgerPostingService;
use App\Modules\Ledger\Application\Services\LedgerReportingService;
use App\Modules\Ledger\Console\SeedLedgerCatalogCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LedgerPostingContract::class, LedgerPostingService::class);
        $this->app->bind(LedgerCompensationContract::class, LedgerCompensationService::class);
        $this->app->bind(LedgerReportingContract::class, LedgerReportingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedLedgerCatalogCommand::class,
            ]);
        }
    }
}
