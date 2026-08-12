<?php

declare(strict_types=1);

use App\Modules\Health\Http\Controllers\User\HealthController;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('health')
    ->group(function (): void {
        Route::get('/me', [HealthController::class, 'show']);
        Route::put('/me/capsule', [HealthController::class, 'updateCapsule']);
        Route::put('/me/consents/emergency-capsule', [HealthController::class, 'updateEmergencyConsent']);
        Route::post('/me/representatives', [HealthController::class, 'addRepresentative']);
        Route::post('/me/representatives/{representativeId}/suspend', [HealthController::class, 'suspendRepresentative']);
    });
