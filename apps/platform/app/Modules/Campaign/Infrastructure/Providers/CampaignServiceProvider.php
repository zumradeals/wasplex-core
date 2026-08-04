<?php

namespace App\Modules\Campaign\Infrastructure\Providers;

use App\Modules\Campaign\Console\Commands\BootstrapCampaign;
use App\Modules\Campaign\Console\Commands\BootstrapCampaignReview;
use Illuminate\Support\ServiceProvider;

final class CampaignServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BootstrapCampaign::class,
                BootstrapCampaignReview::class,
            ]);
        }
    }
}
