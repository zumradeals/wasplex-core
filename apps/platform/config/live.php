<?php

declare(strict_types=1);

return [
    // Protection anti-réidentification pour les estimations sponsorisées.
    // Cette valeur reste distincte du module Campaigns même si le seuil
    // initial est volontairement aligné sur la première verticale publicitaire.
    'minimum_segment_size' => (int) env('LIVE_MINIMUM_SEGMENT_SIZE', 20),
];
