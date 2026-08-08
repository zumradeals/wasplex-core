<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Admin\AccountsController;
use App\Modules\Identity\Http\Controllers\Admin\CapabilityGrantsController;
use App\Modules\Identity\Http\Controllers\Api\AuthController;
use App\Modules\Identity\Http\Controllers\Api\MeController;
use App\Modules\Identity\Http\Controllers\Api\MeMfaController;
use App\Modules\Identity\Http\Controllers\Api\MeSessionsController;
use App\Modules\Identity\Http\Controllers\Api\MeSpacesController;
use App\Modules\Identity\Http\Controllers\Api\OrganizationInvitationsController;
use App\Modules\Identity\Http\Controllers\Api\OrganizationMembersController;
use App\Modules\Identity\Http\Controllers\Api\OrganizationsController;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth', EnsureSessionNotRevoked::class])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);

    Route::get('/me/spaces', [MeSpacesController::class, 'index']);
    Route::post('/me/spaces/{userSpace}/switch', [MeSpacesController::class, 'switch']);

    Route::get('/me/sessions', [MeSessionsController::class, 'index']);
    Route::delete('/me/sessions/{session}', [MeSessionsController::class, 'destroy']);

    Route::post('/me/mfa', [MeMfaController::class, 'store']);
    Route::put('/me/mfa', [MeMfaController::class, 'update']);
    Route::post('/me/mfa/verify', [MeMfaController::class, 'verify']);

    Route::get('/organizations', [OrganizationsController::class, 'index']);
    Route::post('/organizations', [OrganizationsController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationsController::class, 'show']);

    Route::get('/organizations/{organization}/members', [OrganizationMembersController::class, 'index'])
        ->middleware(EnsureCapability::class.':organization.manage.self,organization:organization');
    Route::delete('/organizations/{organization}/members/{membership}', [OrganizationMembersController::class, 'destroy'])
        ->middleware(EnsureCapability::class.':organization.manage.self,organization:organization');

    Route::post('/organizations/{organization}/invitations', [OrganizationInvitationsController::class, 'store'])
        ->middleware(EnsureCapability::class.':organization.manage.self,organization:organization');
    Route::post('/organizations/invitations/{invitation}/accept', [OrganizationInvitationsController::class, 'accept']);

    Route::prefix('admin')
        ->middleware([EnsureRecentMfa::class])
        ->group(function (): void {
            Route::get('/capabilities', [CapabilityGrantsController::class, 'index'])
                ->middleware(EnsureCapability::class.':admin.audit.view');
            Route::post('/capabilities', [CapabilityGrantsController::class, 'store'])
                ->middleware(EnsureCapability::class.':admin.capabilities.grant');
            Route::delete('/capabilities/{grant}', [CapabilityGrantsController::class, 'destroy'])
                ->middleware(EnsureCapability::class.':admin.capabilities.revoke');

            Route::get('/accounts', [AccountsController::class, 'index'])
                ->middleware(EnsureCapability::class.':admin.accounts.view');
            Route::get('/accounts/{account}', [AccountsController::class, 'show'])
                ->middleware(EnsureCapability::class.':admin.accounts.view');
            Route::post('/accounts/{account}/restrict', [AccountsController::class, 'restrict'])
                ->middleware(EnsureCapability::class.':admin.accounts.restrict');
            Route::post('/accounts/{account}/unrestrict', [AccountsController::class, 'unrestrict'])
                ->middleware(EnsureCapability::class.':admin.accounts.restrict');
        });
});
