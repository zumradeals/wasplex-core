<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Wallet\Http\Controllers\User\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('me/wallet')
    ->group(function (): void {
        Route::get('/', [WalletController::class, 'show']);
        Route::get('/history', [WalletController::class, 'history']);
        Route::post('/deposits', [WalletController::class, 'createDeposit']);
        Route::post('/deposits/{deposit}/refresh', [WalletController::class, 'refreshDeposit']);
        Route::post('/transfers/recipient', [WalletController::class, 'resolveRecipient']);
        Route::post('/transfers', [WalletController::class, 'transfer']);
    });
