<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Subscriptions\Http\Controllers\Admin\EconomicClassesController;
use App\Modules\Subscriptions\Http\Controllers\Admin\SubscriptionPlansController;
use App\Modules\Subscriptions\Http\Controllers\User\PlansController;
use App\Modules\Subscriptions\Http\Controllers\User\SubscriptionsController;
use Illuminate\Support\Facades\Route;

// docs/03-abonnements-et-classes-economiques-wasplex.md §24: API
// utilisateur — self-service, aucune capacité spéciale requise au-delà
// d'une session authentifiée valide.
Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('subscriptions')
    ->group(function (): void {
        Route::get('/plans', [PlansController::class, 'index']);
        Route::get('/current', [SubscriptionsController::class, 'current']);
        Route::post('/', [SubscriptionsController::class, 'store']);
        Route::post('/payments/{payment}/refresh', [SubscriptionsController::class, 'refreshPayment']);
        Route::post('/{subscription}/renew', [SubscriptionsController::class, 'renew']);
        Route::post('/{subscription}/upgrade', [SubscriptionsController::class, 'upgrade']);
        Route::post('/{subscription}/cancel', [SubscriptionsController::class, 'cancel']);
        Route::get('/quota', [SubscriptionsController::class, 'quota']);
        Route::get('/history', [SubscriptionsController::class, 'history']);
    });

// docs/03 §25: API administration — MFA récente + capacités dédiées,
// même discipline que les routes admin du Grand Livre (P002).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/subscriptions/plans', [SubscriptionPlansController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.plans.manage');
        Route::post('/subscriptions/plans', [SubscriptionPlansController::class, 'store'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.plans.manage');
        Route::patch('/subscriptions/plans/{planVersion}', [SubscriptionPlansController::class, 'update'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.plans.manage');
        Route::post('/subscriptions/plans/{planVersion}/publish', [SubscriptionPlansController::class, 'publish'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.plans.manage');
        Route::post('/subscriptions/plans/{planVersion}/suspend', [SubscriptionPlansController::class, 'suspend'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.plans.manage');

        Route::get('/economic-classes', [EconomicClassesController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.classes.manage');
        Route::patch('/economic-classes/{economicClass}', [EconomicClassesController::class, 'update'])
            ->middleware(EnsureCapability::class.':admin.subscriptions.classes.manage');
    });
