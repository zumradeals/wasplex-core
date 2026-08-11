<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\SmartProfile\Http\Controllers\Admin\ConsentPurposesController;
use App\Modules\SmartProfile\Http\Controllers\Admin\ProfileTaxonomiesController;
use App\Modules\SmartProfile\Http\Controllers\User\ConsentsController;
use App\Modules\SmartProfile\Http\Controllers\User\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('me')
    ->group(function (): void {
        Route::get('/smart-profile', [ProfileController::class, 'index']);
        Route::patch('/smart-profile/country', [ProfileController::class, 'updateCountry']);
        Route::post('/smart-profile/{taxonomy}', [ProfileController::class, 'declare']);
        Route::delete('/smart-profile/{taxonomy}', [ProfileController::class, 'withdraw']);

        Route::get('/consents', [ConsentsController::class, 'index']);
        Route::post('/consents/{purpose}/grant', [ConsentsController::class, 'grant']);
        Route::post('/consents/{purpose}/withdraw', [ConsentsController::class, 'withdraw']);
        Route::get('/consents/history', [ConsentsController::class, 'history']);
    });

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/smartprofile')
    ->group(function (): void {
        Route::get('/taxonomies', [ProfileTaxonomiesController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.taxonomies.manage');
        Route::post('/taxonomies', [ProfileTaxonomiesController::class, 'store'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.taxonomies.manage');
        Route::patch('/taxonomies/{taxonomy}', [ProfileTaxonomiesController::class, 'update'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.taxonomies.manage');
        Route::post('/taxonomies/{taxonomy}/activate', [ProfileTaxonomiesController::class, 'activate'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.taxonomies.manage');
        Route::post('/taxonomies/{taxonomy}/suspend', [ProfileTaxonomiesController::class, 'suspend'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.taxonomies.manage');

        Route::get('/consent-purposes', [ConsentPurposesController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.consents.manage');
        Route::post('/consent-purposes', [ConsentPurposesController::class, 'store'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.consents.manage');
        Route::patch('/consent-purposes/versions/{version}', [ConsentPurposesController::class, 'update'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.consents.manage');
        Route::post('/consent-purposes/versions/{version}/publish', [ConsentPurposesController::class, 'publish'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.consents.manage');
        Route::post('/consent-purposes/{purpose}/suspend', [ConsentPurposesController::class, 'suspend'])
            ->middleware(EnsureCapability::class.':admin.smartprofile.consents.manage');
    });
