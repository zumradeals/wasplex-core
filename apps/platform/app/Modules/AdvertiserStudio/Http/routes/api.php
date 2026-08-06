<?php

declare(strict_types=1);

use App\Modules\AdvertiserStudio\Http\Controllers\Admin\AdvertisersController;
use App\Modules\AdvertiserStudio\Http\Controllers\Admin\BrandsController as AdminBrandsController;
use App\Modules\AdvertiserStudio\Http\Controllers\Admin\CreativeModerationController;
use App\Modules\AdvertiserStudio\Http\Controllers\Advertiser\AssetsController;
use App\Modules\AdvertiserStudio\Http\Controllers\Advertiser\BrandsController;
use App\Modules\AdvertiserStudio\Http\Controllers\Advertiser\ProfileController;
use App\Modules\Identity\Http\Middleware\EnsureActiveAdvertiserOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

// docs/13-studio-annonceur-wasplex.md §88: API Studio — self-service,
// scopée à l'espace annonceur actif du compte (même middleware que le
// Wallet annonceur, P003).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureActiveAdvertiserOrganization::class])
    ->prefix('advertiser')
    ->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.profile.manage,organization:advertiser_organization_id');
        Route::patch('/profile', [ProfileController::class, 'update'])
            ->middleware(EnsureCapability::class.':advertiser.profile.manage,organization:advertiser_organization_id');

        Route::get('/brands', [BrandsController::class, 'index'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');
        Route::post('/brands', [BrandsController::class, 'store'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');
        Route::get('/brands/{brand}', [BrandsController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');
        Route::patch('/brands/{brand}', [BrandsController::class, 'update'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');
        Route::put('/brands/{brand}/colors', [BrandsController::class, 'updateColors'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');
        Route::put('/brands/{brand}/typographies', [BrandsController::class, 'updateTypographies'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');
        Route::put('/brands/{brand}/guidelines', [BrandsController::class, 'updateGuidelines'])
            ->middleware(EnsureCapability::class.':advertiser.brand.manage,organization:advertiser_organization_id');

        Route::get('/assets', [AssetsController::class, 'index'])
            ->middleware(EnsureCapability::class.':advertiser.media.upload,organization:advertiser_organization_id');
        Route::post('/assets', [AssetsController::class, 'store'])
            ->middleware(EnsureCapability::class.':advertiser.media.upload,organization:advertiser_organization_id');
        Route::get('/assets/{asset}', [AssetsController::class, 'show'])
            ->middleware(EnsureCapability::class.':advertiser.media.upload,organization:advertiser_organization_id');
        Route::delete('/assets/{asset}', [AssetsController::class, 'destroy'])
            ->middleware(EnsureCapability::class.':advertiser.media.manage,organization:advertiser_organization_id');
    });

// docs/13 §93 — MFA récente + capacités dédiées, même discipline que les
// autres surfaces admin (Ledger P002, Abonnements P004).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/advertisers', [AdvertisersController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.advertisers.manage');
        Route::get('/advertisers/{advertiser}', [AdvertisersController::class, 'show'])
            ->middleware(EnsureCapability::class.':admin.advertisers.manage');
        Route::post('/advertisers/{advertiser}/verify', [AdvertisersController::class, 'verify'])
            ->middleware(EnsureCapability::class.':admin.advertisers.manage');
        Route::post('/advertisers/{advertiser}/restrict', [AdvertisersController::class, 'restrict'])
            ->middleware(EnsureCapability::class.':admin.advertisers.manage');
        Route::post('/advertisers/{advertiser}/restore', [AdvertisersController::class, 'restore'])
            ->middleware(EnsureCapability::class.':admin.advertisers.manage');

        Route::get('/brands', [AdminBrandsController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.brands.moderate');
        Route::post('/brands/{brand}/verify', [AdminBrandsController::class, 'verify'])
            ->middleware(EnsureCapability::class.':admin.brands.moderate');
        Route::post('/brands/{brand}/reject', [AdminBrandsController::class, 'reject'])
            ->middleware(EnsureCapability::class.':admin.brands.moderate');

        Route::get('/creative-assets', [CreativeModerationController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.brands.moderate');
        Route::post('/creative-assets/{asset}/decide', [CreativeModerationController::class, 'decide'])
            ->middleware(EnsureCapability::class.':admin.brands.moderate');
    });
