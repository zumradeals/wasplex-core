<?php

declare(strict_types=1);

// Seuils techniques par défaut, ajustables par déploiement — pas une
// décision produit figée (docs/chantiers/P010-CHANTIER.md §2.3 : docs/16
// §18 reste qualitatif, aucun seuil chiffré n'y est spécifié).
return [
    // FeedPanel.vue envoie un heartbeat toutes les 400 ms — un intervalle
    // plus court qu'une fraction de cela dénote une automatisation plutôt
    // qu'un rythme d'interface utilisateur réel.
    'heartbeat_min_interval_ms' => (int) env('FEED_HEARTBEAT_MIN_INTERVAL_MS', 250),

    // Marge au-delà de laquelle une durée déclarée par le client, même
    // après bornage par l'horloge serveur, est considérée comme une
    // tentative de sur-déclaration plutôt qu'une imprécision réseau.
    'overclaim_tolerance_ms' => (int) env('FEED_OVERCLAIM_TOLERANCE_MS', 500),

    // Nombre de signaux de risque accumulés sur une même livraison avant
    // mise en attente (docs/16 §20 : preuve douteuse -> hold -> revue).
    'risk_hold_threshold' => (int) env('FEED_RISK_HOLD_THRESHOLD', 2),

    // Une livraison "started" sans heartbeat récent au-delà de ce multiple
    // de sa durée requise est considérée bloquée (client disparu) par la
    // commande de reprise.
    'stuck_delivery_timeout_multiplier' => (int) env('FEED_STUCK_DELIVERY_TIMEOUT_MULTIPLIER', 5),
];
