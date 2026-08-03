<?php

namespace App\Modules\Ledger\Infrastructure\Providers;

use App\Modules\Ledger\Application\Contracts\LedgerContract;
use App\Modules\Ledger\Application\Contracts\LedgerQueryContract;
use App\Modules\Ledger\Application\Services\LedgerPoster;
use App\Modules\Ledger\Application\Services\LedgerQueryService;
use App\Modules\Ledger\Console\Commands\BootstrapLedger;
use Illuminate\Support\ServiceProvider;

final class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LedgerContract::class, LedgerPoster::class);
        $this->app->bind(LedgerQueryContract::class, LedgerQueryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapLedger::class]);
        }
    }
}
