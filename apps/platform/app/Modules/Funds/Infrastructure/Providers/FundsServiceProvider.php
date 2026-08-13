<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Providers;

use App\Modules\Funds\Console\FundIntegrityCheckCommand;
use App\Modules\Funds\Console\SeedFundProgramsCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FundsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');
        $this->commands([FundIntegrityCheckCommand::class, SeedFundProgramsCommand::class]);

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
