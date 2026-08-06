<?php

use App\Modules\AdvertiserStudio\Infrastructure\Providers\AdvertiserStudioServiceProvider;
use App\Modules\AdvertiserWallet\Infrastructure\Providers\AdvertiserWalletServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Infrastructure\Providers\LedgerServiceProvider;
use App\Modules\Subscriptions\Infrastructure\Providers\SubscriptionsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Payments\PaymentsServiceProvider;

return [
    AppServiceProvider::class,
    PaymentsServiceProvider::class,
    IdentityServiceProvider::class,
    LedgerServiceProvider::class,
    AdvertiserWalletServiceProvider::class,
    SubscriptionsServiceProvider::class,
    AdvertiserStudioServiceProvider::class,
];
