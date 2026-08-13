<?php

declare(strict_types=1);

use App\Modules\Card\Http\Controllers\CardsController;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('cards')
    ->group(function (): void {
        Route::get('/', [CardsController::class, 'index']);
        Route::post('/', [CardsController::class, 'store']);
        Route::post('/{card}/qr', [CardsController::class, 'qr']);
        Route::post('/{card}/suspend', [CardsController::class, 'suspend']);
    });
