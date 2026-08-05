<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AdminShellController;
use App\Modules\Identity\Http\Controllers\AdvertiserShellController;
use App\Modules\Identity\Http\Controllers\GuestPagesController;
use App\Modules\Identity\Http\Controllers\UserShellController;
use App\Modules\Identity\Http\Middleware\EnsureCapability;
use App\Modules\Identity\Http\Middleware\EnsureRecentMfa;
use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Identity\Http\Middleware\EnsureSpaceMembership;
use Illuminate\Support\Facades\Route;

Route::get('/login', [GuestPagesController::class, 'login'])->name('login');
Route::get('/register', [GuestPagesController::class, 'register'])->name('register');

Route::middleware(['auth', EnsureSessionNotRevoked::class])->group(function (): void {
    Route::get('/app', [UserShellController::class, 'index'])->name('app.home');

    Route::get('/studio', [AdvertiserShellController::class, 'index'])
        ->middleware(EnsureSpaceMembership::class.':advertiser')
        ->name('studio.home');

    Route::get('/admin/mfa-challenge', [AdminShellController::class, 'mfaChallenge'])->name('admin.mfa-challenge');

    Route::get('/admin', [AdminShellController::class, 'index'])
        ->middleware([EnsureCapability::class.':admin.dashboard.view', EnsureRecentMfa::class])
        ->name('admin.home');
});
