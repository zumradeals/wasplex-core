<?php

use App\Http\Controllers\TechnicalPageController;
use Illuminate\Support\Facades\Route;

// La racine publique est la Landing Wasplex (voir app/Modules/Identity/Http/routes/web.php).
// Cette page de vérification du socle P000 reste disponible pour le diagnostic opérationnel.
Route::get('/status', TechnicalPageController::class)->name('technical');
