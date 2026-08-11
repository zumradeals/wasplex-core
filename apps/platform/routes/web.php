<?php

use App\Http\Controllers\TechnicalPageController;
use App\Modules\Campaigns\Http\Controllers\Public\PublicCampaignController;
use Illuminate\Support\Facades\Route;

// La racine publique est la Landing Wasplex (voir app/Modules/Identity/Http/routes/web.php).
// Cette page de vérification du socle P000 reste disponible pour le diagnostic opérationnel.
Route::get('/status', TechnicalPageController::class)->name('technical');

// Une campagne n'est rendue ici que si elle est approuvée, dans sa fenêtre de
// diffusion et explicitement ouverte au public. Le contrôleur retourne 404
// dans tous les autres cas.
Route::get('/c/{slug}', [PublicCampaignController::class, 'show'])->name('campaign.public.show');
