<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Matching\Http\Controllers\Admin\MatchingAuditController;
use App\Modules\Matching\Http\Controllers\Admin\MatchingConfigurationController;
use App\Modules\Matching\Http\Controllers\User\EligibleCampaignsController;
use Illuminate\Support\Facades\Route;

// docs/chantiers/P008-CHANTIER.md §7 : self-service, aucune capacité
// spéciale requise au-delà d'une session authentifiée valide.
Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('me')
    ->group(function (): void {
        Route::get('/eligible-campaigns', [EligibleCampaignsController::class, 'index']);
    });

// MFA récente + capacités dédiées, même discipline que les autres
// domaines admin (P002, P004, P006, P007).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])
    ->prefix('admin/matching')
    ->group(function (): void {
        Route::get('/configuration', [MatchingConfigurationController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.matching.configuration.manage');
        Route::post('/configuration', [MatchingConfigurationController::class, 'store'])
            ->middleware(EnsureCapability::class.':admin.matching.configuration.manage');
        Route::patch('/configuration/{configuration}', [MatchingConfigurationController::class, 'update'])
            ->middleware(EnsureCapability::class.':admin.matching.configuration.manage');
        Route::post('/configuration/{configuration}/publish', [MatchingConfigurationController::class, 'publish'])
            ->middleware(EnsureCapability::class.':admin.matching.configuration.manage');

        Route::get('/audit', [MatchingAuditController::class, 'index'])
            ->middleware(EnsureCapability::class.':admin.matching.audit.view');
    });
