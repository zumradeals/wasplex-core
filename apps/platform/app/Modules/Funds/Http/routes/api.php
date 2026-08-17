<?php

declare(strict_types=1);

use App\Modules\Funds\Http\Controllers\Admin\AdminFundCollectionsController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundCollectionWavesController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundPartnerDashboardController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundPilotageController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundQuotesController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundRealizationsController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundsController;
use App\Modules\Funds\Http\Controllers\Admin\AdminFundWarrantyController;
use App\Modules\Funds\Http\Controllers\Professional\ProfessionalFundOrdersController;
use App\Modules\Funds\Http\Controllers\Professional\ProfessionalFundQuotesController;
use App\Modules\Funds\Http\Controllers\Professional\ProfessionalFundWarrantyController;
use App\Modules\Funds\Http\Controllers\User\FundCollectionObligationsController;
use App\Modules\Funds\Http\Controllers\User\FundPilotageController;
use App\Modules\Funds\Http\Controllers\User\FundRealizationStatusController;
use App\Modules\Funds\Http\Controllers\User\FundsController;
use App\Modules\Funds\Http\Controllers\User\FundWarrantyController;
use App\Modules\Identity\Http\Middleware\EnsureActiveProfessionalOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('funds')
    ->group(function (): void {
        Route::get('/', [FundsController::class, 'overview']);
        Route::get('/collection-obligations', [FundCollectionObligationsController::class, 'index']);
        Route::get('/pilotage', [FundPilotageController::class, 'index']);
        Route::post('/rehabilitations/{case}/start', [FundPilotageController::class, 'startRehabilitation']);
        Route::get('/realizations', [FundRealizationStatusController::class, 'index']);
        Route::post('/orders/{order}/confirm-delivery', [FundRealizationStatusController::class, 'confirmDelivery']);
        Route::post('/orders/{order}/disputes', [FundRealizationStatusController::class, 'dispute']);
        Route::post('/orders/{order}/warranty-claims', [FundWarrantyController::class, 'claim']);
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
        Route::get('/orders', [ProfessionalFundOrdersController::class, 'index'])
            ->middleware(EnsureCapability::class.':professional.funds.order.view,organization:professional_organization_id');
        Route::post('/orders/{order}/proofs', [ProfessionalFundOrdersController::class, 'submitProof'])
            ->middleware(EnsureCapability::class.':professional.funds.milestone.submit,organization:professional_organization_id');
        Route::post('/orders/{order}/delivered', [ProfessionalFundOrdersController::class, 'markDelivered'])
            ->middleware(EnsureCapability::class.':professional.funds.delivery.confirm,organization:professional_organization_id');
        Route::post('/orders/{order}/warranty', [ProfessionalFundWarrantyController::class, 'register'])
            ->middleware(EnsureCapability::class.':professional.funds.warranty.manage,organization:professional_organization_id');
        Route::post('/orders/{order}/warranty-claims/{claimId}/response', [ProfessionalFundWarrantyController::class, 'respond'])
            ->middleware(EnsureCapability::class.':professional.funds.warranty.manage,organization:professional_organization_id');
    });

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/funds')
    ->group(function (): void {
        Route::get('/', [AdminFundsController::class, 'dashboard'])->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/programs', [AdminFundsController::class, 'storeProgram'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::patch('/programs/{program}', [AdminFundsController::class, 'updateProgram'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::delete('/programs/{program}', [AdminFundsController::class, 'destroyProgram'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/programs/{program}/versions', [AdminFundsController::class, 'storeVersion'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/program-versions/{version}/publish', [AdminFundsController::class, 'publishVersion'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/programs/{program}/status', [AdminFundsController::class, 'setProgramStatus'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/categories', [AdminFundsController::class, 'storeCategory'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::patch('/categories/{category}', [AdminFundsController::class, 'updateCategory'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/wishes/{wish}/review', [AdminFundsController::class, 'reviewWish'])->middleware(EnsureCapability::class.':admin.funds.review');

        Route::get('/pilotage', [AdminFundPilotageController::class, 'index'])->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/wishes/{wish}/queue', [AdminFundPilotageController::class, 'queue'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/programs/{program}/queue/release-next', [AdminFundPilotageController::class, 'releaseNext'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/wishes/{wish}/reserve-allocation', [AdminFundPilotageController::class, 'reserve'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/reserve-allocations/{allocation}/release', [AdminFundPilotageController::class, 'releaseReserve'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/rehabilitations/detect', [AdminFundPilotageController::class, 'detectRehabilitations'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/rehabilitations/{case}/complete', [AdminFundPilotageController::class, 'completeRehabilitation'])->middleware(EnsureCapability::class.':admin.funds.manage');

        Route::get('/partner-dashboard', [AdminFundPartnerDashboardController::class, 'index'])->middleware(EnsureCapability::class.':admin.funds.view');
        Route::get('/partners', [AdminFundQuotesController::class, 'partners'])->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/wishes/{wish}/quote-requests', [AdminFundQuotesController::class, 'requestQuotes'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/wishes/{wish}/quotes/{quote}/select', [AdminFundQuotesController::class, 'selectQuote'])->middleware(EnsureCapability::class.':admin.funds.review');

        Route::get('/collections', [AdminFundCollectionsController::class, 'index'])->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/wishes/{wish}/collection-snapshot', [AdminFundCollectionsController::class, 'create'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/collections/{snapshot}/execute', [AdminFundCollectionsController::class, 'execute'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/collections/{snapshot}/waves', [AdminFundCollectionWavesController::class, 'store'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/collections/{snapshot}/waves/{waveNumber}/execute', [AdminFundCollectionWavesController::class, 'execute'])->middleware(EnsureCapability::class.':admin.funds.review');

        Route::get('/realizations', [AdminFundRealizationsController::class, 'index'])->middleware(EnsureCapability::class.':admin.funds.view');
        Route::post('/wishes/{wish}/order', [AdminFundRealizationsController::class, 'create'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/orders/{order}/milestones', [AdminFundRealizationsController::class, 'addMilestone'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/orders/{order}/milestones/{milestoneId}/approve', [AdminFundRealizationsController::class, 'approveMilestone'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/orders/{order}/milestones/{milestoneId}/ledger-post', [AdminFundRealizationsController::class, 'postMilestone'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/orders/{order}/milestones/{milestoneId}/settlement', [AdminFundRealizationsController::class, 'confirmSettlement'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/orders/{order}/reserve', [AdminFundRealizationsController::class, 'authorizeReserve'])->middleware(EnsureCapability::class.':admin.funds.manage');
        Route::post('/orders/{order}/disputes/{disputeId}/resolve', [AdminFundRealizationsController::class, 'resolveDispute'])->middleware(EnsureCapability::class.':admin.funds.review');
        Route::post('/orders/{order}/warranty-claims/{claimId}/resolve', [AdminFundWarrantyController::class, 'resolve'])->middleware(EnsureCapability::class.':admin.funds.review');
    });
