<?php

use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Platform\Infrastructure\Providers\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    PlatformServiceProvider::class,
];
