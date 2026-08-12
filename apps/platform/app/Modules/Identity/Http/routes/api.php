<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Admin\AccountsController;
use App\Modules\Identity\Http\Controllers\Admin\CapabilityGrantsController;
use App\Modules\Identity\Http\Controllers\Admin\IdentityVerificationsController;
use App\Modules\Identity\Http\Controllers\Admin\ProfessionalSpacesController as AdminProfessionalSpacesController;
use App\Modules\Identity\Http\Controllers\Api\AuthController;
use App\Modules\Identity\Http\Controllers\Api\MeController;
use App\Modules\Identity\Http\Controllers\Api\MeIdentityVerificationController;
use App\Modules\Identity\Http\Controllers\Api\MeMfaController;
use App\Modules\Identity\Http\Controllers\Api\MePasswordController;
use App\Modules\Identity\Http\Controllers\Api\MeSessionsController;
use App\Modules\Identity\Http\Controllers\Api\MeSpacesController;
use App\Modules\Identity\Http\Controllers\Api\OrganizationInvitationsController;
use App\Modules\Identity\Http\Controllers\Api\OrganizationMembersController;
use App\Modules\Identity\Http\Controllers\Api\OrganizationsController;
use App\Modules\Identity\Http\Controllers\Api\ProfessionalSpacesController;
use App\Modules\Identity\Http\Controllers\Api\ProfessionalWorkspaceController;
use App\Modules\Identity\Http\Middleware\EnsureActiveProfessionalOrganization;
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
    Route::put('/me/password', [MePasswordController::class, 'update']);

    Route::get('/me/spaces', [MeSpacesController::class, 'index']);
    Route::post('/me/spaces/{userSpace}/switch', [MeSpacesController::class, 'switch']);

    Route::get('/me/sessions', [MeSessionsController::class, 'index']);
    Route::delete('/me/sessions/{session}', [MeSessionsController::class, 'destroy']);

    Route::post('/me/mfa', [MeMfaController::class, 'store']);
    Route::put('/me/mfa', [MeMfaController::class, 'update']);
    Route::post('/me/mfa/verify', [MeMfaController::class, 'verify']);

    Route::get('/me/identity-verification', [MeIdentityVerificationController::class, 'show']);
    Route::post('/me/identity-verification', [MeIdentityVerificationController::class, 'store']);

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

    Route::get('/professional-spaces', [ProfessionalSpacesController::class, 'index']);
    Route::post('/professional-spaces', [ProfessionalSpacesController::class, 'store']);

    Route::prefix('professional')
        ->middleware([EnsureActiveProfessionalOrganization::class])
        ->group(function (): void {
            Route::get('/workspace', [ProfessionalWorkspaceController::class, 'show'])
                ->middleware(EnsureCapability::class.':professional.space.access,organization:professional_organization_id');
            Route::post('/service-points', [ProfessionalWorkspaceController::class, 'storeServicePoint'])
                ->middleware(EnsureCapability::class.':professional.service-points.manage,organization:professional_organization_id');
            Route::post('/roles', [ProfessionalWorkspaceController::class, 'assignRole'])
                ->middleware(EnsureCapability::class.':professional.team.manage,organization:professional_organization_id');
        });

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

            Route::get('/identity-verifications', [IdentityVerificationsController::class, 'index'])
                ->middleware(EnsureCapability::class.':admin.identity-verifications.view');
            Route::get('/identity-verifications/{verification}', [IdentityVerificationsController::class, 'show'])
                ->middleware(EnsureCapability::class.':admin.identity-verifications.view');
            Route::get('/identity-verifications/{verification}/documents/{kind}', [IdentityVerificationsController::class, 'document'])
                ->middleware(EnsureCapability::class.':admin.identity-verifications.view');
            Route::post('/identity-verifications/{verification}/approve', [IdentityVerificationsController::class, 'approve'])
                ->middleware(EnsureCapability::class.':admin.identity-verifications.decide');
            Route::post('/identity-verifications/{verification}/reject', [IdentityVerificationsController::class, 'reject'])
                ->middleware(EnsureCapability::class.':admin.identity-verifications.decide');

            Route::get('/professional-spaces', [AdminProfessionalSpacesController::class, 'index'])
                ->middleware(EnsureCapability::class.':admin.professional-spaces.view');
            Route::get('/professional-spaces/{professionalSpace}', [AdminProfessionalSpacesController::class, 'show'])
                ->middleware(EnsureCapability::class.':admin.professional-spaces.view');
            Route::post('/professional-spaces/{professionalSpace}/decision', [AdminProfessionalSpacesController::class, 'decide'])
                ->middleware(EnsureCapability::class.':admin.professional-spaces.decide');
        });
});
