<?php

declare(strict_types=1);

use App\Modules\Health\Http\Controllers\Professional\ProfessionalHealthAccessController;
use App\Modules\Health\Http\Controllers\User\HealthController;
use App\Modules\Health\Http\Controllers\User\HealthProfessionalAccessRequestsController;
use App\Modules\Identity\Http\Middleware\EnsureActiveProfessionalOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('health')
    ->group(function (): void {
        Route::get('/me', [HealthController::class, 'show']);
        Route::put('/me/capsule', [HealthController::class, 'updateCapsule']);
        Route::put('/me/consents/emergency-capsule', [HealthController::class, 'updateEmergencyConsent']);
        Route::post('/me/representatives', [HealthController::class, 'addRepresentative']);
        Route::post('/me/representatives/{representativeId}/suspend', [HealthController::class, 'suspendRepresentative']);
        Route::get('/me/access-requests', [HealthProfessionalAccessRequestsController::class, 'index']);
        Route::post('/me/access-requests/{accessRequest}/decision', [HealthProfessionalAccessRequestsController::class, 'decide']);
    });

Route::middleware(['auth', EnsureSessionNotRevoked::class, EnsureActiveProfessionalOrganization::class])
    ->prefix('professional/health')
    ->group(function (): void {
        Route::get('/access-requests', [ProfessionalHealthAccessController::class, 'index'])
            ->middleware(EnsureCapability::class.':professional.health.access.request,organization:professional_organization_id');
        Route::post('/access-requests', [ProfessionalHealthAccessController::class, 'store'])
            ->middleware(EnsureCapability::class.':professional.health.access.request,organization:professional_organization_id');
        Route::get('/access-requests/{accessRequest}/capsule', [ProfessionalHealthAccessController::class, 'capsule'])
            ->middleware(EnsureCapability::class.':professional.health.access.request,organization:professional_organization_id');
        Route::post('/emergency-capsule', [ProfessionalHealthAccessController::class, 'emergencyCapsule'])
            ->middleware(EnsureCapability::class.':professional.health.emergency-capsule.request,organization:professional_organization_id');
    });
