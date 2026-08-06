<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Reconciliation\Http\Controllers\Admin\ReconciliationController;
use Illuminate\Support\Facades\Route;

// MFA récente + capacité dédiée, même discipline que les autres domaines
// admin (P002, P004, P006, P007, P008, P009).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class, EnsureCapability::class.':admin.reconciliation.review'])
    ->prefix('admin/reconciliation')
    ->group(function (): void {
        Route::post('/runs', [ReconciliationController::class, 'store']);
        Route::get('/runs', [ReconciliationController::class, 'runs']);
        Route::get('/entries', [ReconciliationController::class, 'entries']);
        Route::get('/entries/export', [ReconciliationController::class, 'export']);
    });
