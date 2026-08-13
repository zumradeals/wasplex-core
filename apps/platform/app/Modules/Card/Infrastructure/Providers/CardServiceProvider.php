<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class CardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../../Http/routes/module.php');
    }
}
