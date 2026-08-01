<?php

use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MfaController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Identity\Http\Controllers\ShellController;
use App\Modules\Identity\Http\Controllers\SpaceController;
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
    Route::post('/espaces/annonceur', [SpaceController::class, 'activateAdvertiser'])
        ->middleware('capability:advertiser.space.activate')
        ->name('spaces.advertiser.activate');
    Route::post('/espaces/{space}/activer', [SpaceController::class, 'switch'])->name('spaces.switch');

    Route::get('/securite/mfa', [MfaController::class, 'setupPage'])->name('security.mfa.setup');
    Route::post('/securite/mfa', [MfaController::class, 'begin'])->name('security.mfa.begin');
    Route::post('/securite/mfa/confirmer', [MfaController::class, 'confirm'])->name('security.mfa.confirm');
    Route::get('/securite/mfa/verification', [MfaController::class, 'challengePage'])->name('security.mfa.challenge');
    Route::post('/securite/mfa/verification', [MfaController::class, 'verify'])->middleware('throttle:6,1')->name('security.mfa.verify');

    Route::get('/mon-espace', [ShellController::class, 'user'])
        ->middleware(['space.kind:user', 'capability:account.self.manage'])
        ->name('user.dashboard');

    Route::get('/studio', [ShellController::class, 'advertiser'])
        ->middleware(['space.kind:advertiser', 'capability:advertiser.space.view'])
        ->name('studio.dashboard');

    Route::get('/administration', [ShellController::class, 'admin'])
        ->middleware(['space.kind:administration', 'mfa.recent', 'capability:admin.dashboard.view'])
        ->name('admin.dashboard');
});
