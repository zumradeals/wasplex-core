<?php

declare(strict_types=1);

use App\Modules\Alerts\Http\Controllers\Admin\AlertInstitutionalCasesController;
use App\Modules\Alerts\Http\Controllers\Admin\AlertsReviewController;
use App\Modules\Alerts\Http\Controllers\Professional\InstitutionalAlertsController;
use App\Modules\Alerts\Http\Controllers\User\AlertsController;
use App\Modules\Identity\Http\Middleware\EnsureActiveProfessionalOrganization;
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

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureActiveProfessionalOrganization::class])
    ->prefix('professional/alerts')
    ->group(function (): void {
        Route::get('/', [InstitutionalAlertsController::class, 'index'])
            ->middleware(EnsureCapability::class.':professional.alerts.institution.view,organization:professional_organization_id');
        Route::post('/{case}/transition', [InstitutionalAlertsController::class, 'transition'])
            ->middleware(EnsureCapability::class.':professional.alerts.institution.act,organization:professional_organization_id');
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
        Route::post('/{alert}/refer', [AlertInstitutionalCasesController::class, 'refer'])
            ->middleware(EnsureCapability::class.':admin.alerts.review');
        Route::put('/feed-settings', [AlertsReviewController::class, 'updateFeedSettings'])
            ->middleware(EnsureCapability::class.':admin.alerts.configuration.manage');
    });
