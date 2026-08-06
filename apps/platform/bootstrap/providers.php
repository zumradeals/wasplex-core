<?php

use App\Modules\AdvertiserStudio\Infrastructure\Providers\AdvertiserStudioServiceProvider;
use App\Modules\AdvertiserWallet\Infrastructure\Providers\AdvertiserWalletServiceProvider;
use App\Modules\Campaigns\Infrastructure\Providers\CampaignsServiceProvider;
use App\Modules\Feed\Infrastructure\Providers\FeedServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Ledger\Infrastructure\Providers\LedgerServiceProvider;
use App\Modules\Matching\Infrastructure\Providers\MatchingServiceProvider;
use App\Modules\Reconciliation\Infrastructure\Providers\ReconciliationServiceProvider;
use App\Modules\SmartProfile\Infrastructure\Providers\SmartProfileServiceProvider;
use App\Modules\Subscriptions\Infrastructure\Providers\SubscriptionsServiceProvider;
use App\Modules\Wallet\Infrastructure\Providers\WalletServiceProvider;
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
    CampaignsServiceProvider::class,
    SmartProfileServiceProvider::class,
    MatchingServiceProvider::class,
    WalletServiceProvider::class,
    FeedServiceProvider::class,
    ReconciliationServiceProvider::class,
];
