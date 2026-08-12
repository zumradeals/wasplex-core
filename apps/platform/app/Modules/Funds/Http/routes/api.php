<?php

declare(strict_types=1);

use App\Modules\Funds\Http\Controllers\Admin\AdminFundPartnerDashboardController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundQuotesController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundsController;
use App\Modules\Funds\Http\Controllers\Professional\ProfessionalFundQuotesController;
use App\Modules\Funds\Http\Controllers\User\FundsController;
use App\Modules\Identity\Http\Middleware\EnsureActiveProfessionalOrganization;
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

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureActiveProfessionalOrganization::class])
    ->prefix('professional/funds')
    ->group(function (): void {
        Route::get('/quote-requests', [ProfessionalFundQuotesController::class, 'index'])
            ->middleware(EnsureCapability::class.':professional.funds.quote.view,organization:professional_organization_id');
        Route::post('/quote-requests/{quoteRequest}/quote', [ProfessionalFundQuotesController::class, 'submit'])
            ->middleware(EnsureCapability::class.':professional.funds.quote.respond,organization:professional_organization_id');
        Route::post('/quote-requests/{quoteRequest}/decline', [ProfessionalFundQuotesController::class, 'decline'])
            ->middleware(EnsureCapability::class.':professional.funds.quote.respond,organization:professional_organization_id');
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

        Route::get('/partner-dashboard', [AdminFundPartnerDashboardController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.funds.view');
        Route::get('/partners', [AdminFundQuotesController::class, 'partners'])
            ->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/wishes/{wish}/quote-requests', [AdminFundQuotesController::class, 'requestQuotes'])
            ->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/wishes/{wish}/quotes/{quote}/select', [AdminFundQuotesController::class, 'selectQuote'])
            ->middleware(EnsureCapability::class.':admin.funds.review');
    });
