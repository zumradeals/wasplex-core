<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Providers;

use App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract;
use App\Modules\AdvertiserStudio\Application\Contracts\CampaignModerationContract;
use App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract;
use App\Modules\AdvertiserStudio\Application\Services\BrandDirectoryService;
use App\Modules\AdvertiserStudio\Application\Services\CampaignModerationService;
use App\Modules\AdvertiserStudio\Application\Services\CreativeAssetDirectoryService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AdvertiserStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BrandDirectoryContract::class, BrandDirectoryService::class);
        $this->app->bind(CampaignModerationContract::class, CampaignModerationService::class);
        $this->app->bind(CreativeAssetDirectoryContract::class, CreativeAssetDirectoryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');
        $this->mergeConfigFrom(config_path('advertiser_studio.php'), 'advertiser_studio');

        Route::middleware('web')
            ->prefix('api')
            ->group(__DIR__.'/../../Http/routes/api.php');
    }
}
