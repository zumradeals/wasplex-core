<?php

declare(strict_types=1);

use App\Modules\Alerts\Http\Controllers\Admin\AlertsReviewController;
use App\Modules\Alerts\Http\Controllers\User\AlertsController;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('alerts')
    ->group(function (): void {
        Route::get('/mine', [AlertsController::class, 'mine']);
        Route::get('/public', [AlertsController::class, 'publicFeed']);
        Route::get('/feed-configuration', [AlertsController::class, 'feedConfiguration']);
        Route::post('/', [AlertsController::class, 'store']);
    });

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/alerts')
    ->group(function (): void {
        Route::get('/', [AlertsReviewController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.alerts.review');
        Route::post('/{alert}/publish', [AlertsReviewController::class, 'publish'])
            ->middleware(EnsureCapability::class.':admin.alerts.review');
        Route::post('/{alert}/reject', [AlertsReviewController::class, 'reject'])
            ->middleware(EnsureCapability::class.':admin.alerts.review');
        Route::put('/feed-settings', [AlertsReviewController::class, 'updateFeedSettings'])
            ->middleware(EnsureCapability::class.':admin.alerts.configuration.manage');
    });
