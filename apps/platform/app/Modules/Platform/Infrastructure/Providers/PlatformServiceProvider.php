<?php

namespace App\Modules\Platform\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // P000 intentionally contains no business bindings.
    }
}
