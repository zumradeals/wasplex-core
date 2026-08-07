<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Providers;

use App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract;
use App\Modules\Campaigns\Application\Contracts\CampaignEnvelopeContract;
use App\Modules\Campaigns\Application\Contracts\CampaignsReportingContract;
use App\Modules\Campaigns\Application\Services\ApprovedCampaignAudienceService;
use App\Modules\Campaigns\Application\Services\CampaignEnvelopeService;
use App\Modules\Campaigns\Application\Services\CampaignReportingService;
use App\Modules\Campaigns\Console\SeedPriceCatalogCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApprovedCampaignAudienceContract::class, ApprovedCampaignAudienceService::class);
        $this->app->bind(CampaignEnvelopeContract::class, CampaignEnvelopeService::class);
        $this->app->bind(CampaignsReportingContract::class, CampaignReportingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedPriceCatalogCommand::class,
            ]);
        }

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
