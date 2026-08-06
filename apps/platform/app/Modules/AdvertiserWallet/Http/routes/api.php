<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Http\Controllers\Admin\SupervisedDepositsController;
use App\Modules\AdvertiserWallet\Http\Controllers\Advertiser\DepositsController;
use App\Modules\AdvertiserWallet\Http\Controllers\Advertiser\WalletController;
use App\Modules\Identity\Http\Middleware\EnsureActiveAdvertiserOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
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

// docs/13-studio-annonceur-wasplex.md §28: dépôt supervisé — un
// administrateur crée puis (un second) approuve, MFA récente requise
// comme pour toute action financière admin (même discipline que les
// corrections du Grand Livre, P002).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/advertiser-wallet')
    ->group(function (): void {
        Route::post('/deposits', [SupervisedDepositsController::class, 'store'])
            ->middleware(EnsureCapability::class.':admin.advertiser-wallet.supervised-deposit');
        Route::post('/deposits/{deposit}/approve', [SupervisedDepositsController::class, 'approve'])
            ->middleware(EnsureCapability::class.':admin.advertiser-wallet.supervised-deposit');
        Route::post('/deposits/{deposit}/reject', [SupervisedDepositsController::class, 'reject'])
            ->middleware(EnsureCapability::class.':admin.advertiser-wallet.supervised-deposit');
    });
