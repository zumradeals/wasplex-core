<?php

use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Infrastructure\Providers\LedgerServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    LedgerServiceProvider::class,
];
