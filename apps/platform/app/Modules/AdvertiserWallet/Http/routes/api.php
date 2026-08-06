<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Http\Controllers\Advertiser\DepositsController;
use App\Modules\AdvertiserWallet\Http\Controllers\Advertiser\WalletController;
use App\Modules\AdvertiserWallet\Http\Middleware\EnsureActiveAdvertiserOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureActiveAdvertiserOrganization::class])
    ->prefix('advertiser')
    ->group(function (): void {
        Route::get('/wallet', [WalletController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.wallet.view,organization:advertiser_organization_id');

        Route::get('/wallet/deposits', [DepositsController::class, 'index'])
            ->middleware(EnsureCapability::class.':advertiser.wallet.view,organization:advertiser_organization_id');
        Route::post('/wallet/deposits', [DepositsController::class, 'store'])
            ->middleware(EnsureCapability::class.':advertiser.wallet.deposit.create,organization:advertiser_organization_id');
        Route::get('/wallet/deposits/{deposit}', [DepositsController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.wallet.view,organization:advertiser_organization_id');
    });
