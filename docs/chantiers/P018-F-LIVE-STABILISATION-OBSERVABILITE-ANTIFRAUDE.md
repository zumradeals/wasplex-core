# P018-F — Stabilisation production, observabilité et antifraude avancée du Live rémunéré

## Objectif

P018-F durcit le parcours P018-D/E sans créer une seconde comptabilité. Le Ledger reste la source de vérité et les récompenses Live continuent à consommer `advertiser.live.budget.reserved` selon le partage exact 50/50 : une récompense membre `R` consomme `2R`, crédite `R` au membre et reconnaît `R` pour Wasplex.

Le lot traite les incidents réels de production : signaux d'attention anormaux, auto-rémunération annonceur, revue manuelle, coupure opérationnelle des nouveaux gains, clôture économique avec dossiers en attente et visibilité admin.

## Invariants

1. Le client ne transmet jamais une durée payable. Il transmet seulement `visible` et `media_connected`; le serveur mesure le temps.
2. Une place rémunérée active et une session spectateur `watching` restent obligatoires.
3. Un annonceur ou un membre actif de son organisation ne peut jamais gagner de WP sur son propre Live, en `observe` comme en `enforce`.
4. Un bloc a exactement un état économique : `captured` (payé), `held` (réservé, en revue) ou `rejected` (refusé).
5. Les blocs `captured + held` comptent dans le plafond global `funded_blocks`.
6. Une décision admin ne peut pas payer deux fois le même hold.
7. La clôture d'un Live ne libère jamais les fonds nécessaires aux holds encore `pending`.
8. Aucun détail antifraude nominatif n'est exposé au Studio annonceur.
9. Les membres voient un état compréhensible sans les règles permettant de contourner la détection.

## Modes antifraude

`observe` journalise les signaux mais ne retient pas automatiquement un gain probabiliste. `enforce` place le prochain bloc complet en revue lorsqu'un signal `high` ou `critical` est actif. L'interdit d'auto-rémunération reste absolu dans les deux modes.

```dotenv
LIVE_REWARDS_ENABLED=true
LIVE_REWARD_ANTIFRAUD_MODE=observe
LIVE_REWARD_RISK_REPEAT_THRESHOLD=3
LIVE_REWARD_HEARTBEAT_INTERVAL_MS=3000
LIVE_REWARD_HEARTBEAT_MIN_INTERVAL_MS=1000
LIVE_REWARD_HEARTBEAT_MAX_GAP_MS=8000
```

## Signaux exploités

P018-F n'utilise que des faits déjà possédés par Wasplex : session authentifiée, `device_id` de cette session, horloge serveur, place rémunérée, session spectateur, appartenance active à l'organisation annonceur et présence qualifiée récente sur un autre Live rémunéré.

Signaux : `heartbeat_rate_abuse`, `heartbeat_gap`, `session_changed`, `device_changed`, `concurrent_rewarded_live`, `advertiser_self_reward`.

Aucun eye-tracking, reconnaissance faciale, caméra obligatoire, fingerprinting supplémentaire ou inférence de « comptes liés » n'est introduit.

## Revue admin

Endpoints protégés par session valide, MFA récente et capacités explicites :

- `GET /api/admin/live/dashboard` — `admin.live.dashboard.view`
- `GET /api/admin/live/holds` — `admin.live.risk.review`
- `POST /api/admin/live/holds/{hold}/release`
- `POST /api/admin/live/holds/{hold}/reject`

La migration clone les capacités Live à partir des grants Feed antifraude déjà actifs, afin de conserver le même cercle de confiance sans rendre le droit implicite.

Un `release` recontrôle l'auto-rémunération avant le capture Ledger et utilise une clé d'idempotence propre au hold. Un `reject` ne crée aucun WP et relance le règlement si le Live est déjà terminé.

## Clôture économique

Pour une réservation :

```text
montant réservé
= brut capturé
+ brut des holds pending
+ montant déjà libéré
+ montant encore libre
```

À la fin du Live, seul le montant encore libre est relâché. Après la dernière décision humaine, la réservation passe `released` uniquement lorsque `brut capturé + total libéré = montant initial réservé`.

## Kill switch

`LIVE_REWARDS_ENABLED=false` empêche les nouveaux blocs d'avancer ou d'être capturés. Les écritures Ledger historiques ne sont jamais modifiées et le Live vidéo reste regardable.

## Observabilité

Le dashboard admin agrège Lives sponsorisés actifs, places rémunérées, blocs payés/retenus/refusés, WP membres, part Wasplex, brut consommé, montants en revue, holds, signaux 24 h, mode antifraude et kill switch.

Le rapport Studio annonceur reste agrégé : aucune identité membre ni preuve antifraude n'est exposée.

## UX membre

L'overlay Live distingue les WP réellement acquis des WP « en vérification ». Le heartbeat est envoyé depuis `LiveInteractionsPanel`, monté seulement quand la salle LiveKit est connectée. `visibilitychange` coupe/reprend immédiatement la continuité d'attention.

## Déploiement conseillé

1. Déployer en `LIVE_REWARD_ANTIFRAUD_MODE=observe`.
2. Vérifier le dashboard et le volume de signaux réels.
3. Ajuster uniquement les seuils démontrés par les données.
4. Passer à `enforce` lorsque le bruit est acceptable.
5. Garder `LIVE_REWARDS_ENABLED` comme coupe-circuit incident.
