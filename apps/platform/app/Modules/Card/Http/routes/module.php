<?php

declare(strict_types=1);

use App\Modules\Card\Http\Controllers\CardPaymentController;
use App\Modules\Card\Http\Controllers\CardQrCheckController;
use App\Modules\Card\Http\Controllers\CardsController;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', EnsureSessionNotRevoked::class])->group(function (): void {
    Route::prefix('api/cards')->group(function (): void {
        Route::get('/', [CardsController::class, 'index']);
        Route::post('/', [CardsController::class, 'store']);
        Route::get('/operations', [CardPaymentController::class, 'operations']);
        Route::get('/qr/check', CardQrCheckController::class);
        Route::post('/{card}/qr', [CardsController::class, 'qr']);
        Route::post('/{card}/receive-qr', [CardsController::class, 'receiveQr']);
        Route::post('/{card}/suspend', [CardsController::class, 'suspend']);
    });

    Route::prefix('api/card-scan')->group(function (): void {
        Route::post('/resolve', [CardPaymentController::class, 'resolve']);
        Route::post('/payment', [CardPaymentController::class, 'pay']);
    });
});

require __DIR__.'/page.php';
