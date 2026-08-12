<?php

declare(strict_types=1);

use App\Modules\Funds\Http\Controllers\Admin\AdminFundsController;
use App\Modules\Funds\Http\Controllers\User\FundsController;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('funds')
    ->group(function (): void {
        Route::get('/', [FundsController::class, 'overview']);
        Route::post('/membership', [FundsController::class, 'join']);
        Route::post('/membership/revoke-mandate', [FundsController::class, 'revokeMandate']);
        Route::post('/balance/fund', [FundsController::class, 'fundBalance']);
        Route::post('/wishes', [FundsController::class, 'storeWish']);
        Route::post('/wishes/{wish}/submit', [FundsController::class, 'submitWish']);
        Route::post('/wishes/{wish}/contributions', [FundsController::class, 'contributeWish']);
    });

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/funds')
    ->group(function (): void {
        Route::get('/', [AdminFundsController::class, 'dashboard'])
            ->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/programs', [AdminFundsController::class, 'storeProgram'])
            ->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/programs/{program}/versions', [AdminFundsController::class, 'storeVersion'])
            ->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/program-versions/{version}/publish', [AdminFundsController::class, 'publishVersion'])
            ->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/programs/{program}/status', [AdminFundsController::class, 'setProgramStatus'])
            ->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/categories', [AdminFundsController::class, 'storeCategory'])
            ->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::patch('/categories/{category}', [AdminFundsController::class, 'updateCategory'])
            ->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/wishes/{wish}/review', [AdminFundsController::class, 'reviewWish'])
            ->middleware(EnsureCapability::class.':admin.funds.review');
    });
