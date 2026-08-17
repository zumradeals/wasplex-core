<?php

declare(strict_types=1);

$antifraudMode = strtolower((string) env('FEED_ANTIFRAUD_MODE', 'observe'));

if (! in_array($antifraudMode, ['observe', 'enforce'], true)) {
    $antifraudMode = 'observe';
}

// Seuils techniques par défaut, ajustables par déploiement — pas une
// décision produit figée (docs/chantiers/P010-CHANTIER.md §2.3 : docs/16
// §18 reste qualitatif, aucun seuil chiffré n'y est spécifié).
return [
    // Par défaut, l'antifraude observe et journalise les signaux sans bloquer
    // le gain d'une vue complète. Passer explicitement à "enforce" réactive
    // les retenues automatiques pour revue manuelle.
    'antifraud_mode' => $antifraudMode,

    // Le Feed public invité affiche un gain POTENTIEL uniquement dans le
    // navigateur. Cette valeur n'est jamais écrite dans le Ledger et ne
    // consomme jamais le budget annonceur.
    'guest_simulated_reward_minor' => max(0, (int) env('FEED_GUEST_SIMULATED_REWARD_MINOR', 30)),

    // FeedPanel.vue envoie un heartbeat toutes les 400 ms — un intervalle
    // plus court qu'une fraction de cela dénote une automatisation plutôt
    // qu'un rythme d'interface utilisateur réel.
    'heartbeat_min_interval_ms' => (int) env('FEED_HEARTBEAT_MIN_INTERVAL_MS', 250),

    // Marge au-delà de laquelle une durée déclarée par le client, même
    // après bornage par l'horloge serveur, est considérée comme une
    // tentative de sur-déclaration plutôt qu'une imprécision réseau.
    'overclaim_tolerance_ms' => (int) env('FEED_OVERCLAIM_TOLERANCE_MS', 500),

    // L'événement ended arrive au bord exact du média alors que l'horloge
    // d'attention est échantillonnée côté navigateur.
    'media_end_tolerance_ms' => (int) env('FEED_MEDIA_END_TOLERANCE_MS', 1200),

    // En mode observation, les signaux restent enregistrés mais le seuil de
    // retenue devient volontairement inatteignable. En mode enforce, on
    // retrouve le seuil configurable historique.
    'risk_hold_threshold' => $antifraudMode === 'enforce'
        ? (int) env('FEED_RISK_HOLD_THRESHOLD', 2)
        : PHP_INT_MAX,

    // Une livraison "started" sans heartbeat récent au-delà de ce multiple
    // de sa durée requise est considérée bloquée (client disparu) par la
    // commande de reprise.
    'stuck_delivery_timeout_multiplier' => (int) env('FEED_STUCK_DELIVERY_TIMEOUT_MULTIPLIER', 5),
];
