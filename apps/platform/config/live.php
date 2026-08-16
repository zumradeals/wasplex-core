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

    // P018-E : le navigateur envoie un signal de présence, jamais une durée à
    // payer. Le serveur mesure lui-même le temps entre heartbeats qualifiés.
    'reward_heartbeat_interval_ms' => (int) env('LIVE_REWARD_HEARTBEAT_INTERVAL_MS', 3000),

    // Des appels plus rapprochés sont ignorés et enregistrés comme signal de
    // risque ; ils ne permettent jamais d'accélérer un bloc.
    'reward_heartbeat_min_interval_ms' => (int) env('LIVE_REWARD_HEARTBEAT_MIN_INTERVAL_MS', 1000),

    // Une interruption réseau/onglet trop longue casse le bloc incomplet.
    'reward_heartbeat_max_gap_ms' => (int) env('LIVE_REWARD_HEARTBEAT_MAX_GAP_MS', 8000),
];
