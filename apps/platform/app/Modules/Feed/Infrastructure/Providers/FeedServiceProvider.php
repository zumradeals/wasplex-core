<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Providers;

use App\Modules\Feed\Application\Contracts\FeedCampaignStatsContract;
use App\Modules\Feed\Application\Services\FeedCampaignStatsService;
use App\Modules\Feed\Console\ReleaseExpiredDeliveriesCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FeedCampaignStatsContract::class, FeedCampaignStatsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseExpiredDeliveriesCommand::class]);
        }

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
