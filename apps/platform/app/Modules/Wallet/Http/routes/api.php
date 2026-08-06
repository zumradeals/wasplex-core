<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Wallet\Http\Controllers\User\WalletController;
use Illuminate\Support\Facades\Route;

// docs/chantiers/P009-CHANTIER.md §6 : self-service, aucune capacité
// spéciale requise au-delà d'une session authentifiée valide.
Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('me/wallet')
    ->group(function (): void {
        Route::get('/', [WalletController::class, 'show']);
        Route::get('/history', [WalletController::class, 'history']);
    });
