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

    /*
    |--------------------------------------------------------------------------
    | Livraison — durée requise par défaut
    |--------------------------------------------------------------------------
    |
    | docs/08-feed-principal-wasplex.md §20 : "Durée : 30 secondes" est un
    | exemple, pas une règle chiffrée. L'assistant campagne (P006) ne
    | collecte pas encore de durée créative — valeur de départ ajustable,
    | utilisée tant qu'aucune durée n'est fournie par la campagne.
    |
    */

    'default_ad_duration_ms' => (int) env('CAMPAIGNS_DEFAULT_AD_DURATION_MS', 15000),

    /*
    |--------------------------------------------------------------------------
    | Livraison — validité d'une réservation d'enveloppe
    |--------------------------------------------------------------------------
    |
    | docs/07 §15 : la réservation doit expirer si la publicité n'est jamais
    | démarrée ni complétée (docs/chantiers/P009-CHANTIER.md §2.8).
    |
    */

    'envelope_reservation_ttl_seconds' => (int) env('CAMPAIGNS_ENVELOPE_RESERVATION_TTL_SECONDS', 120),

];
