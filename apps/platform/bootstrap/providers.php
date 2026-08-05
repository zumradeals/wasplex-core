<?php

use App\Modules\AdvertiserWallet\Infrastructure\Providers\AdvertiserWalletServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Infrastructure\Providers\LedgerServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    LedgerServiceProvider::class,
    AdvertiserWalletServiceProvider::class,
];
