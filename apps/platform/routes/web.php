<?php

use App\Modules\EconomicConfiguration\Http\Controllers\EconomicConfigurationAdminController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MfaController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Identity\Http\Controllers\ShellController;
use App\Modules\Identity\Http\Controllers\SpaceController;
use App\Modules\Wallet\Http\Controllers\WalletAdminController;
use App\Modules\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::inertia('/connexion', 'Auth/Login')->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::inertia('/inscription', 'Auth/Register')->name('register');
    Route::post('/inscription', RegisterController::class)->middleware('throttle:4,1')->name('register.store');
});

Route::middleware(['auth', 'identity.session'])->group(function (): void {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/espace', [ShellController::class, 'home'])->name('space.home');
    Route::post('/espaces/annonceur', [SpaceController::class, 'activateAdvertiser'])->middleware('capability:advertiser.space.activate')->name('spaces.advertiser.activate');
    Route::post('/espaces/{space}/activer', [SpaceController::class, 'switch'])->name('spaces.switch');

    Route::get('/securite/mfa', [MfaController::class, 'setupPage'])->name('security.mfa.setup');
    Route::post('/securite/mfa', [MfaController::class, 'begin'])->name('security.mfa.begin');
    Route::post('/securite/mfa/confirmer', [MfaController::class, 'confirm'])->name('security.mfa.confirm');
    Route::get('/securite/mfa/verification', [MfaController::class, 'challengePage'])->name('security.mfa.challenge');
    Route::post('/securite/mfa/verification', [MfaController::class, 'verify'])->middleware('throttle:6,1')->name('security.mfa.verify');

    Route::get('/mon-espace', [ShellController::class, 'user'])->middleware(['space.kind:user', 'capability:account.self.manage'])->name('user.dashboard');
    Route::get('/wallet', [WalletController::class, 'show'])->middleware(['space.kind:user', 'capability:wallet.view.self'])->name('wallet.show');
    Route::post('/wallet/depot', [WalletController::class, 'storeDeposit'])->middleware(['space.kind:user', 'capability:wallet.deposit.create.self', 'throttle:6,1'])->name('wallet.deposit.store');

    Route::get('/studio', [ShellController::class, 'advertiser'])->middleware(['space.kind:advertiser', 'capability:advertiser.space.view'])->name('studio.dashboard');
    Route::get('/studio/wallet', [WalletController::class, 'show'])->middleware(['space.kind:advertiser', 'capability:advertiser.wallet.view'])->name('studio.wallet');
    Route::post('/studio/wallet/depot', [WalletController::class, 'storeDeposit'])->middleware(['space.kind:advertiser', 'capability:advertiser.wallet.fund', 'throttle:6,1'])->name('studio.wallet.deposit.store');

    Route::get('/administration', [ShellController::class, 'admin'])->middleware(['space.kind:administration', 'mfa.recent', 'capability:admin.dashboard.view'])->name('admin.dashboard');
    Route::get('/administration/wallet', [WalletAdminController::class, 'page'])->middleware(['space.kind:administration', 'mfa.recent', 'capability:wallet.deposit.review'])->name('admin.wallet');

    Route::prefix('/administration/economie')
        ->name('admin.economy.')
        ->middleware(['space.kind:administration', 'mfa.recent'])
        ->group(function (): void {
            Route::get('/', [EconomicConfigurationAdminController::class, 'index'])
                ->middleware('capability:economic.configuration.view')
                ->name('index');
            Route::post('/simuler', [EconomicConfigurationAdminController::class, 'simulate'])
                ->middleware('capability:economic.configuration.manage')
                ->name('simulate');
            Route::post('/classes/{economicClass}/versions', [EconomicConfigurationAdminController::class, 'store'])
                ->middleware('capability:economic.configuration.manage')
                ->name('versions.store');
            Route::post('/versions/{version}/approuver', [EconomicConfigurationAdminController::class, 'approve'])
                ->middleware('capability:economic.configuration.approve')
                ->name('versions.approve');
            Route::post('/versions/{version}/publier', [EconomicConfigurationAdminController::class, 'publish'])
                ->middleware('capability:economic.configuration.publish')
                ->name('versions.publish');
            Route::post('/versions/{version}/suspendre', [EconomicConfigurationAdminController::class, 'suspend'])
                ->middleware('capability:economic.configuration.suspend')
                ->name('versions.suspend');
        });
});
