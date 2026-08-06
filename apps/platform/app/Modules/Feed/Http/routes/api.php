<?php

declare(strict_types=1);

use App\Modules\Feed\Http\Controllers\Admin\FeedDashboardController;
use App\Modules\Feed\Http\Controllers\User\FeedDeliveriesController;
use App\Modules\Feed\Http\Controllers\User\FeedSessionsController;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

// docs/08-feed-principal-wasplex.md §67 : self-service, aucune capacité
// spéciale requise au-delà d'une session authentifiée valide.
Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('feed')
    ->group(function (): void {
        Route::post('/sessions', [FeedSessionsController::class, 'store']);
        Route::get('/next', [FeedDeliveriesController::class, 'next']);

        Route::post('/deliveries/{delivery}/start', [FeedDeliveriesController::class, 'start']);
        Route::post('/deliveries/{delivery}/heartbeat', [FeedDeliveriesController::class, 'heartbeat']);
        Route::post('/deliveries/{delivery}/complete', [FeedDeliveriesController::class, 'complete']);
        Route::post('/deliveries/{delivery}/abandon', [FeedDeliveriesController::class, 'abandon']);
        Route::get('/deliveries/{delivery}/why', [FeedDeliveriesController::class, 'why']);

        Route::post('/campaigns/{campaign}/like', [FeedDeliveriesController::class, 'like']);
        Route::post('/campaigns/{campaign}/save', [FeedDeliveriesController::class, 'save']);
        Route::post('/campaigns/{campaign}/share', [FeedDeliveriesController::class, 'share']);
        Route::get('/campaigns/{campaign}/comments', [FeedDeliveriesController::class, 'comments']);
        Route::post('/campaigns/{campaign}/comments', [FeedDeliveriesController::class, 'storeComment']);
    });

// MFA récente + capacité dédiée, même discipline que les autres domaines
// admin (P002, P004, P006, P007, P008).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/feed')
    ->group(function (): void {
        Route::get('/dashboard', [FeedDashboardController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.feed.dashboard.view');
    });
