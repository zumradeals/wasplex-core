<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Live\Http\Controllers\CreatorLiveController;
use App\Modules\Live\Http\Controllers\LiveController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web', 'auth', EnsureSessionNotRevoked::class])->group(function (): void {
    Route::get('/live', fn () => Inertia::render('Live/LivePage'));

    Route::prefix('api/lives')->group(function (): void {
        Route::get('/', [LiveController::class, 'index']);
        Route::get('/{live}', [LiveController::class, 'show']);
        Route::post('/{live}/join', [LiveController::class, 'join']);
        Route::post('/{live}/leave', [LiveController::class, 'leave']);
    });

    Route::prefix('api/creator/lives')->group(function (): void {
        Route::get('/', [CreatorLiveController::class, 'index']);
        Route::post('/', [CreatorLiveController::class, 'store']);
        Route::patch('/{live}', [CreatorLiveController::class, 'update']);
        Route::post('/{live}/schedule', [CreatorLiveController::class, 'schedule']);
        Route::post('/{live}/start', [CreatorLiveController::class, 'start']);
        Route::post('/{live}/pause', [CreatorLiveController::class, 'pause']);
        Route::post('/{live}/resume', [CreatorLiveController::class, 'resume']);
        Route::post('/{live}/end', [CreatorLiveController::class, 'end']);
    });
});
