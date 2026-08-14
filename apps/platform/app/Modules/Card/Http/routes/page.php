<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['web', 'auth', EnsureSessionNotRevoked::class])
    ->get('/services/wasplex', fn () => Inertia::render('Card/CardPage'));
