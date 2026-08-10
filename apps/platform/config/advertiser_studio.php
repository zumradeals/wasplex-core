<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bibliothèque créative — limites de validation technique
    |--------------------------------------------------------------------------
    |
    | docs/13-studio-annonceur-wasplex.md §21 exige une validation de
    | format/taille/durée/ratio, mais ne fixe aucune valeur chiffrée exacte.
    | Ces limites sont donc des valeurs de départ raisonnables, centralisées
    | ici (pas codées en dur dans le service) pour rester ajustables sans
    | changement de code — à réviser explicitement par le fondateur si
    | besoin, pas une décision produit figée.
    |
    */

    'disk' => env('ADVERTISER_STUDIO_DISK', 'public'),

    'image' => [
        'formats' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_size_kb' => 10240,
    ],

    'video' => [
        'formats' => ['mp4', 'mov', 'webm'],
        'max_size_kb' => 204800,
        'max_duration_seconds' => (int) env('ADVERTISER_STUDIO_VIDEO_MAX_DURATION_SECONDS', 60),
        'ffprobe_binary' => env('ADVERTISER_STUDIO_FFPROBE_BINARY', 'ffprobe'),
    ],

];
