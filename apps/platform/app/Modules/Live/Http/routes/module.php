<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureActiveAdvertiserOrganization;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Live\Http\Controllers\CreatorLiveController;
use App\Modules\Live\Http\Controllers\LiveController;
use App\Modules\Live\Http\Controllers\LiveInteractionController;
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

        Route::get('/{live}/comments', [LiveInteractionController::class, 'viewerIndex']);
        Route::post('/{live}/comments', [LiveInteractionController::class, 'viewerComment'])
            ->middleware('throttle:30,1');
        Route::post('/{live}/reactions', [LiveInteractionController::class, 'viewerReaction'])
            ->middleware('throttle:120,1');
        Route::post('/{live}/comments/{comment}/report', [LiveInteractionController::class, 'report'])
            ->middleware('throttle:20,1');
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

            Route::get('/{live}/comments', [LiveInteractionController::class, 'hostIndex'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/comments', [LiveInteractionController::class, 'hostComment'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/reactions', [LiveInteractionController::class, 'hostReaction'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::patch('/{live}/interactions', [LiveInteractionController::class, 'settings'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/comments/{comment}/pin', [LiveInteractionController::class, 'pin'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/comments/{comment}/hide', [LiveInteractionController::class, 'hide'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::post('/{live}/participants/{account}/silence', [LiveInteractionController::class, 'silence'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
            Route::delete('/{live}/participants/{account}/silence', [LiveInteractionController::class, 'unsilence'])
                ->middleware(EnsureCapability::class.':advertiser.campaign.manage,organization:advertiser_organization_id');
        });
});
