<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Reporting\Http\Controllers\Admin\FounderDashboardController;
use Illuminate\Support\Facades\Route;

// docs/18-reporting-statistiques-audit-observabilite-wasplex.md §18 :
// capacité déjà créée en P001, réutilisée telle quelle (aucune nouvelle
// capacité pour ce premier tableau de bord fixe).
Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class, EnsureCapability::class.':admin.dashboard.view'])
    ->prefix('admin/dashboard')
    ->group(function (): void {
        Route::get('/summary', [FounderDashboardController::class, 'show']);
    });
