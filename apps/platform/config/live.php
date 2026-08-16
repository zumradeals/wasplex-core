<?php

declare(strict_types=1);

return [
    // Protection anti-réidentification pour les estimations sponsorisées.
    // Cette valeur reste distincte du module Campaigns même si le seuil
    // initial est volontairement aligné sur la première verticale publicitaire.
    'minimum_segment_size' => (int) env('LIVE_MINIMUM_SEGMENT_SIZE', 20),

    // P018-D : une place libérée est proposée temporairement au premier membre
    // de la file. Aucun gain n'est promis avant acceptation de l'offre.
    'reward_offer_seconds' => (int) env('LIVE_REWARD_OFFER_SECONDS', 45),
];
