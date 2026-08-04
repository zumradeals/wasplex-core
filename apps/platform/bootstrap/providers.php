<?php

use App\Modules\AdvertiserStudio\Infrastructure\Providers\AdvertiserStudioServiceProvider;
use App\Modules\Campaign\Infrastructure\Providers\CampaignServiceProvider;
use App\Modules\EconomicConfiguration\Infrastructure\Providers\EconomicConfigurationServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Infrastructure\Providers\LedgerServiceProvider;
use App\Modules\Platform\Infrastructure\Providers\PlatformServiceProvider;
use App\Modules\Wallet\Infrastructure\Providers\WalletServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    LedgerServiceProvider::class,
    WalletServiceProvider::class,
    EconomicConfigurationServiceProvider::class,
    AdvertiserStudioServiceProvider::class,
    CampaignServiceProvider::class,
    PlatformServiceProvider::class,
];
