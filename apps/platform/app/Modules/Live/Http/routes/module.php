<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureActiveAdvertiserOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Live\Http\Controllers\CreatorLiveController;
use App\Modules\Live\Http\Controllers\LiveController;
use App\Modules\Live\Http\Controllers\LiveRealtimeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web', 'auth', EnsureSessionNotRevoked::class])->group(function (): void {
    Route::get('/live', fn () => Inertia::render('Live/LivePage'));

    Route::prefix('api/lives')->group(function (): void {
        Route::get('/', [LiveController::class, 'index']);
        Route::get('/{live}', [LiveController::class, 'show']);
        Route::post('/{live}/join', [LiveController::class, 'join']);
        Route::post('/{live}/leave', [LiveController::class, 'leave']);
        Route::post('/{live}/media-token', [LiveRealtimeController::class, 'viewerCredentials']);
        Route::post('/{live}/stage-request', [LiveRealtimeController::class, 'requestStage']);
        Route::delete('/{live}/stage-request', [LiveRealtimeController::class, 'withdrawStage']);
        Route::post('/{live}/stage-request/leave', [LiveRealtimeController::class, 'leaveStage']);
    });

    Route::prefix('api/advertiser/lives')
        ->middleware(EnsureActiveAdvertiserOrganization::class)
        ->group(function (): void {
            Route::get('/', [CreatorLiveController::class, 'index'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.view,organization:advertiser_organization_id');
            Route::post('/', [CreatorLiveController::class, 'store'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::patch('/{live}', [CreatorLiveController::class, 'update'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/schedule', [CreatorLiveController::class, 'schedule'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/start', [CreatorLiveController::class, 'start'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/pause', [CreatorLiveController::class, 'pause'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/resume', [CreatorLiveController::class, 'resume'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/end', [CreatorLiveController::class, 'end'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/media-token', [LiveRealtimeController::class, 'hostCredentials'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::get('/{live}/stage-requests', [LiveRealtimeController::class, 'stageRequests'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/stage-requests/{stageRequest}/approve', [LiveRealtimeController::class, 'approve'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/stage-requests/{stageRequest}/reject', [LiveRealtimeController::class, 'reject'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/stage-requests/{stageRequest}/lower', [LiveRealtimeController::class, 'lower'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        });
});
