<?php

use App\Modules\Identity\Http\Controllers\AccountSessionController;
use App\Modules\Identity\Http\Controllers\AdminController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\MfaController;
use App\Modules\Identity\Http\Controllers\OrganizationController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Identity\Http\Controllers\SpaceController;
use App\Modules\Ledger\Http\Controllers\LedgerAdminController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'data' => [
            'service' => 'wasplex-platform',
            'status' => 'ok',
            'time' => now()->toIso8601String(),
            'trace_id' => request()->attributes->get('trace_id'),
        ],
    ]);
})->name('api.health');

Route::prefix('auth')->middleware('guest')->group(function (): void {
    Route::post('/register', RegisterController::class)->middleware('throttle:4,1');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'identity.session'])->group(function (): void {
    Route::post('/auth/logout', [LoginController::class, 'destroy']);
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update'])
        ->middleware('capability:account.self.manage');
    Route::get('/me/spaces', [MeController::class, 'spaces']);
    Route::post('/me/spaces/advertiser', [SpaceController::class, 'activateAdvertiser'])
        ->middleware('capability:advertiser.space.activate');
    Route::post('/me/spaces/{space}/switch', [SpaceController::class, 'switch']);
    Route::get('/me/sessions', [AccountSessionController::class, 'index'])
        ->middleware('capability:account.sessions.manage');
    Route::delete('/me/sessions/{accountSession}', [AccountSessionController::class, 'destroy'])
        ->middleware('capability:account.sessions.manage');

    Route::post('/me/mfa/setup', [MfaController::class, 'begin']);
    Route::post('/me/mfa/confirm', [MfaController::class, 'confirm']);
    Route::post('/me/mfa/verify', [MfaController::class, 'verify'])->middleware('throttle:6,1');

    Route::post('/organizations/{organization}/invitations', [OrganizationController::class, 'invite'])
        ->middleware('capability:organization.members.invite');
    Route::post('/invitations/{token}/accept', [OrganizationController::class, 'accept'])
        ->middleware('throttle:6,1');

    Route::prefix('admin')->middleware(['space.kind:administration', 'mfa.recent'])->group(function (): void {
        Route::get('/accounts', [AdminController::class, 'accounts'])
            ->middleware('capability:admin.accounts.view');
        Route::get('/organizations', [AdminController::class, 'organizations'])
            ->middleware('capability:admin.organizations.view');
        Route::post('/accounts/{account}/capabilities', [AdminController::class, 'grant'])
            ->middleware('capability:admin.capabilities.grant');
        Route::delete('/capabilities/{grant}', [AdminController::class, 'revoke'])
            ->middleware('capability:admin.capabilities.revoke');

        Route::prefix('ledger')->group(function (): void {
            Route::get('/transactions', [LedgerAdminController::class, 'transactions'])
                ->middleware('capability:wallet.ledger.view');
            Route::get('/transactions/{transactionId}', [LedgerAdminController::class, 'transaction'])
                ->middleware('capability:wallet.audit.view');
            Route::get('/accounts', [LedgerAdminController::class, 'accounts'])
                ->middleware('capability:wallet.ledger.view');
        });
    });
});
