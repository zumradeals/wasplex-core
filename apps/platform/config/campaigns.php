<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Audience — seuil minimal de segment
    |--------------------------------------------------------------------------
    |
    | docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md §11 :
    | "Un segment trop petit doit être élargi, fusionné, retardé, ou refusé."
    | Aucune valeur chiffrée n'est donnée dans le corpus — ce seuil est une
    | valeur de départ raisonnable, ajustable par le fondateur, pas une
    | décision produit figée.
    |
    */

    'minimum_segment_size' => (int) env('CAMPAIGNS_MINIMUM_SEGMENT_SIZE', 20),

    /*
    |--------------------------------------------------------------------------
    | Devis — durée de validité
    |--------------------------------------------------------------------------
    |
    | docs/13 §42 : "date d'expiration" fait partie du devis, sans valeur
    | chiffrée donnée dans le corpus.
    |
    */

    'quote_validity_hours' => (int) env('CAMPAIGNS_QUOTE_VALIDITY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Revue administrative — séparation demandeur/décideur
    |--------------------------------------------------------------------------
    |
    | docs/chantiers/P007-CHANTIER.md §2 : optionnelle. Si activée,
    | l'administrateur qui a demandé une correction ne peut pas être celui
    | qui approuve ou rejette la resoumission correspondante.
    |
    */

    'review_require_distinct_decider' => filter_var(env('CAMPAIGNS_REVIEW_REQUIRE_DISTINCT_DECIDER', false), FILTER_VALIDATE_BOOLEAN),

];
