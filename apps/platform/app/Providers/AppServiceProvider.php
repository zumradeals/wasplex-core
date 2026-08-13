<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $provider = 'App\\Modules\\Card\\Infrastructure\\Providers\\CardServiceProvider';
        $this->app->register($provider);
    }

    public function boot(): void
    {
        //
    }
}
