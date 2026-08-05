<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Ledger\Http\Controllers\Admin\LedgerAccountsController;
use App\Modules\Ledger\Http\Controllers\Admin\LedgerCorrectionsController;
use App\Modules\Ledger\Http\Controllers\Admin\LedgerTransactionsController;
use Illuminate\Support\Facades\Route;

/**
 * Admin-only, read-mostly surface (docs/chantiers/P002-CHANTIER.md
 * invariant: "les clients HTTP ne disposent d'aucune route de posting" —
 * there is deliberately no generic POST /transactions route here; the
 * internal LedgerPostingContract is the only way to create a normal
 * posting, reserved to other backend modules).
 */
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/ledger')
    ->group(function (): void {
        Route::get('/transactions', [LedgerTransactionsController::class, 'index'])
            ->middleware(EnsureCapability::class.':wallet.ledger.view');
        Route::get('/transactions/{transaction}', [LedgerTransactionsController::class, 'show'])
            ->middleware(EnsureCapability::class.':wallet.audit.view');

        Route::get('/accounts', [LedgerAccountsController::class, 'index'])
            ->middleware(EnsureCapability::class.':wallet.ledger.view');

        Route::post('/corrections', [LedgerCorrectionsController::class, 'store'])
            ->middleware(EnsureCapability::class.':wallet.correction.propose');
        Route::post('/corrections/{transaction}/approve', [LedgerCorrectionsController::class, 'approve'])
            ->middleware(EnsureCapability::class.':wallet.correction.approve');
        Route::post('/corrections/{transaction}/reject', [LedgerCorrectionsController::class, 'reject'])
            ->middleware(EnsureCapability::class.':wallet.correction.approve');
    });
