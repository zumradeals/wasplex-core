# RAPPORT — P010 : Antifraude, preuves renforcées et reprise

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `feccc7a` (P009 fusionné)
**Chantier :** `docs/chantiers/P010-CHANTIER.md`
**Spécifications :** `docs/16-moderation-securite-antifraude-globale-wasplex.md` (§17-20, §23-24),
`docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md` (§18, §21, §39-40)
**Statut :** ready_for_review

Ce chantier tient la promesse explicitement différée par P009 (`docs/chantiers/P009-CHANTIER.md`
§2.4) : le Feed ne se contente plus de borner silencieusement une durée d'attention excessive —
il détecte, journalise, met en attente et fait revoir par un humain toute publicité dont la preuve
d'attention est douteuse, exactement selon le flux `docs/16` §20 : *preuve valide → capture →
crédit* / *preuve douteuse → hold → revue* / *preuve invalide → libération → rejet*.

## 1. Objectif

```text
heartbeat suspect détecté (rythme anormal, dépassement du temps réel)
→ signal enregistré et compté sur la livraison
→ seuil atteint : mise en attente (hold), aucun crédit décidé
→ revue administrative : libérer (crédit normal) ou rejeter (aucun gain)
→ livraison bloquée ou orpheline : reprise (libère l'enveloppe, journalise, jamais de perte silencieuse)
```

## 2. Réalisé

### 2.1. Détection de signaux (`AttentionService::heartbeat()`, étendu)

Deux signaux calculables avec les données déjà collectées, faute de seuils chiffrés dans les
spécifications qualitatives (`docs/16` §18) :

- `heartbeat_rate_abuse` : deux heartbeats reçus plus vite que l'intervalle plausible du client
  (`FeedPanel.vue` envoie toutes les 400 ms — seuil par défaut 250 ms, `config/feed.php`).
- `overclaimed_duration` : la durée déclarée dépasse le temps réel écoulé côté serveur au-delà
  d'une tolérance technique (500 ms par défaut).

Chaque signal incrémente `feed_ad_deliveries.risk_signal_count` et enregistre
`last_risk_signal_code` — un signal seul n'a aucune conséquence immédiate ; seule l'accumulation
compte au moment de `complete()`.

### 2.2. Mise en attente (`AttentionService::complete()`, étendu)

Si `risk_signal_count` atteint le seuil (`config('feed.risk_hold_threshold')`, 2 par défaut) au
moment où la livraison atteindrait normalement `completed`, elle passe à `held` à la place :
aucune capture d'enveloppe, aucune écriture au Grand Livre, aucun crédit Wallet. Une ligne
`feed_ad_delivery_holds` est créée avec le montant suspendu, le motif, l'horodatage d'ouverture et
les preuves (nombre de signaux, durées observées) — conforme à `docs/16` §23 (montant, motif,
durée, dossier, statut, issue).

### 2.3. Revue administrative (`FeedRiskReviewService`, nouveau)

Séparé d'`AttentionService` comme `CampaignReviewService` l'est de `CampaignService` (P007) : le
parcours utilisateur en direct et la décision administrative sont deux responsabilités
distinctes.

- `listQueue()` : retenues en attente, avec la livraison associée.
- `release()` : capture l'enveloppe, poste la transaction Grand Livre, crédite le Wallet — flux
  identique à la complétion normale de P009, exécuté cette fois sur décision humaine.
- `reject()` : libère l'enveloppe sans aucun gain, comme un abandon.

Les deux actions sont atomiques (une seule transaction DB) et exigent la capacité
`admin.feed.risk.review` + MFA récente, même discipline que P002/P004/P006/P007/P008.

### 2.4. Reprise étendue (`feed:release-expired-deliveries`)

La commande existante couvre désormais trois cas, plutôt que d'être remplacée :

1. réservations `reserved` expirées (comportement P009, inchangé) ;
2. livraisons `started` sans heartbeat récent au-delà de
   `stuck_delivery_timeout_multiplier × required_duration_ms` (client disparu — le délai est
   proportionnel à la durée propre de chaque publicité, pas à la TTL fixe de réservation) ;
3. filet de sécurité : toute livraison `completed` sans `ledger_transaction_id` associé est
   activement recherchée et journalisée en erreur critique (`Log::critical`) — impossible par
   construction grâce à la transaction atomique de P009, mais vérifiée plutôt que supposée.

### 2.5. UI

- `AdminFeedRiskPanel.vue` (nouveau) : file des retenues en langage clair (motif, gain suspendu,
  preuves), boutons « Libérer le gain » / « Rejeter sans gain ». Émet un événement `resolved` que
  `AdminShell.vue` utilise pour rafraîchir immédiatement le tableau de bord Feed voisin (bug
  d'affichage détecté et corrigé pendant les captures — voir §6).
- `FeedPanel.vue` : un état `held` affiche « Vérification en cours » au lieu d'un gain — jamais un
  « +0 WP » qui laisserait croire à un crédit manqué.

### 2.6. Correctifs découverts en construisant ce chantier

- **Bug réel dans `AttentionService::start()` (résidu de débogage P009)** : l'appel
  `$this->quota->consume($accountId, 1, "feed-quota:{$delivery->id}\n", FILE_APPEND)` passait deux
  arguments invalides (`"\n"` dans la clé d'idempotence, `FILE_APPEND` en 4ᵉ argument ignoré par
  PHP) — un résidu de l'instrumentation `file_put_contents` utilisée pour déboguer P009, jamais
  entièrement nettoyé. Sans effet fonctionnel observable (PHP ignore silencieusement les arguments
  surnuméraires), mais corrigé en construisant cette antifraude sur la même méthode.
- **Précision temporelle insuffisante pour une preuve renforcée** : `started_at` et la nouvelle
  colonne `last_heartbeat_at` étaient en précision seconde (`timestamp(0)`), et le format de
  sauvegarde par défaut d'Eloquent (`Y-m-d H:i:s`, `Grammar::getDateFormat()`) tronque de toute
  façon les fractions de seconde à l'écriture quelle que soit la précision de colonne. Une
  antifraude fondée sur des écarts de quelques centaines de millisecondes était donc sans objet.
  Corrigé par une migration élevant les deux colonnes à `timestamp(3)` et en surchargeant
  `FeedAdDelivery::getDateFormat()` pour préserver les millisecondes à l'écriture — détecté par un
  test qui échouait de façon contre-intuitive (le signal ne se déclenchait jamais) avant d'être
  diagnostiqué par une reproduction directe en base.

## 3. Décisions explicites (voir `docs/chantiers/P010-CHANTIER.md` §2, 9 décisions numérotées)

1. Pas de moteur de risque générique (`risk_signal_definitions`/`risk_evaluations`/
   `risk_decisions` de `docs/16` §72) — signaux détectés et comptés directement sur
   `feed_ad_deliveries`, un seul domaine consommateur réel existant.
2. Pas de fingerprinting d'appareil ni de détection de fermes multi-comptes (`docs/16` §15/§16/
   §19) — aucune collecte de `device_session_id` dans P001-P009, en introduire une maintenant
   serait une nouvelle collecte de données sans décision fondateur explicite sur la vie privée.
3. Deux signaux seulement, faute de seuils chiffrés dans les spécifications qualitatives — valeurs
   par défaut techniques ajustables (`config/feed.php`), pas une décision produit figée.
4. Pas de détection de comportement inter-livraisons (répétition parfaite, cadence identique) —
   non implémentable honnêtement sans seuils spécifiés.
5. Retenue directement liée à `feed_ad_deliveries` via `feed_ad_delivery_holds`, pas une entité
   générique `financial_holds` pour des domaines qui n'existent pas encore.
6. Pas de moteur de règles versionnées pour les seuils antifraude.
7. Revue administrative simple (libérer/rejeter), sans file d'enquête complète façon
   `CampaignReviewCase`.
8. Reprise toujours manuelle (aucun scheduler/Horizon introduit), cohérent avec P002-P009.
9. Pas de retenue sur un retrait (les retraits n'existent pas encore, P011).

## 4. Contrats internes

Aucun nouveau contrat cross-module — la détection et la revue restent internes au module Feed.
`FeedRiskReviewService` réutilise les contrats déjà consommés par `AttentionService` en P009
(`CampaignEnvelopeContract`, `AdvertiserWalletReservationContract`, `UserWalletContract`) sans
modification de leur surface.

## 5. API et capacités

Utilisateur : aucun changement de forme (`heartbeat`/`complete` inchangés, `delivery.status` peut
désormais valoir `held` ou `rejected`).

Administration (MFA récente + capacité `admin.feed.risk.review`, nouvelle) :

```text
GET  /api/admin/feed/holds
POST /api/admin/feed/holds/{id}/release
POST /api/admin/feed/holds/{id}/reject
```

## 6. Tests exécutés

- `php artisan test` (Pest 4) — **184 tests, 2084 assertions, aucune régression** (175 avant ce
  chantier + 9 nouveaux dans `tests/Feature/Feed/FeedAntifraudTest.php`).
- Couverture explicite des scénarios obligatoires (§8 du chantier) : rythme de heartbeat anormal
  déclenche un signal ; dépassement de durée déclarée déclenche un signal ; seuil de signaux
  atteint → mise en attente sans crédit ni capture (enveloppe restant `reserved`, jamais capturée
  ni libérée tant que la revue n'a pas tranché) ; libération d'une retenue crédite normalement, une
  seule fois ; rejet d'une retenue libère l'enveloppe sans aucun gain ; reprise libère une livraison
  `started` bloquée au-delà du délai proportionnel à sa durée ; reprise laisse intacte une
  livraison encore plausiblement active ; reprise journalise (sans corriger) une anomalie
  d'intégrité simulée (livraison `completed` sans transaction) ; capacité admin requise pour la
  file de retenues.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts (un cas `possibly null`
  supplémentaire détecté par `vue-tsc` dans `FeedPanel.vue::completeDelivery()` après ajout de la
  branche `held` — corrigé par le même motif de variable locale que P009).
- `migrate:rollback --step=2` → `migrate` : aller-retour propre sur les 2 nouvelles migrations.
- Parcours navigateur réel (Playwright/Chromium) contre serveur Laravel + Vite locaux, données
  seedées via les mêmes services applicatifs que la suite de tests : le seuil de rythme de
  heartbeat a été temporairement resserré (`FEED_HEARTBEAT_MIN_INTERVAL_MS=450`, au-dessus de la
  cadence réelle de 400 ms du client) **uniquement pour cette capture**, afin que le rythme
  d'interface normal déclenche honnêtement le signal via le vrai navigateur plutôt qu'un appel API
  simulé — la valeur par défaut livrée (250 ms) n'a pas changé. Résultat : lecture réelle → « 
  Vérification en cours » affiché au lieu d'un gain → file de retenues admin affichant la preuve
  réelle (19 signaux accumulés, motif « Rythme de heartbeat anormal ») → libération admin → Wallet
  crédité (675 WP) et tableau de bord Feed mis à jour immédiatement, chiffres identiques
  utilisateur/admin.
- **Bug UI trouvé et corrigé pendant les captures** : après une libération, `AdminFeedPanel`
  (tableau de bord) restait affiché avec les anciens chiffres (« held : 1 », « 0 WP ») tant que la
  page n'était pas rechargée — chaque panneau chargeait ses données indépendamment sans notifier
  son voisin. Corrigé en exposant `load()` sur `AdminFeedPanel` et en le déclenchant depuis
  `AdminShell.vue` sur l'événement `resolved` émis par `AdminFeedRiskPanel`. Revérifié par une
  seconde capture montrant la mise à jour immédiate.

## 7. Captures

- Feed, notice de vérification après une lecture au rythme anormal, aucun gain affiché
  (`01-feed-hold-notice.png`).
- Administration, file des retenues antifraude avec preuves réelles
  (`02-admin-feed-holds-queue.png`).
- Administration, après libération : tableau de bord et file mis à jour dans le même chargement
  de page (`03-admin-feed-holds-released.png`).

Fichiers transmis directement au fondateur (non versionnés dans le dépôt, cohérent avec la
pratique des chantiers précédents).

## 8. Fichiers modifiés/ajoutés

```text
apps/platform/app/Modules/Feed/
  Database/Migrations/2026_08_06_150000_add_risk_fields_to_feed_ad_deliveries_table.php (nouveau)
  Database/Migrations/2026_08_06_150001_create_feed_ad_delivery_holds_table.php         (nouveau)
  Infrastructure/Models/FeedAdDelivery.php                                              (modifié)
  Infrastructure/Models/FeedAdDeliveryHold.php                                          (nouveau)
  Application/Services/AttentionService.php                                             (modifié)
  Application/Services/FeedRiskReviewService.php                                        (nouveau)
  Application/Services/FeedHoldNotFoundException.php                                    (nouveau)
  Application/Services/HoldNotResolvableException.php                                   (nouveau)
  Console/ReleaseExpiredDeliveriesCommand.php                                           (modifié)
  Http/Controllers/Admin/FeedRiskReviewController.php                                   (nouveau)
  Http/routes/api.php                                                                   (modifié)
apps/platform/app/Modules/Identity/Console/SeedFounderCommand.php     (modifié : capacité risk.review)
apps/platform/config/feed.php                                         (nouveau)
apps/platform/resources/js/Components/AdminFeedRiskPanel.vue          (nouveau)
apps/platform/resources/js/Components/AdminFeedPanel.vue              (modifié : load() exposé)
apps/platform/resources/js/Components/FeedPanel.vue                   (modifié : état held)
apps/platform/resources/js/Pages/Identity/AdminShell.vue              (modifié)
apps/platform/tests/Feature/Feed/FeedAntifraudTest.php                (nouveau, 9 tests)
docs/chantiers/P010-CHANTIER.md, P010-RAPPORT.md                      (nouveaux)
```

## 9. Migrations, événements, permissions

- **Migrations** : 2 nouvelles (extension `feed_ad_deliveries` + création
  `feed_ad_delivery_holds`).
- **Événements** : aucun événement outbox distinct — la retenue est une ligne d'état auditée,
  cohérent avec la décision P009 §2.5 (livraison = session d'attention fusionnées).
- **Permissions** : `admin.feed.risk.review` (nouvelle, accordée explicitement au fondateur).

## 10. Limites restantes

- Aucune détection de fermes de visionnage ou de comportement multi-comptes (`docs/16` §16/§19) —
  nécessiterait une collecte de données d'appareil hors périmètre de ce chantier.
- Seuils numériques (rythme, tolérance, seuil de mise en attente) sont des valeurs techniques par
  défaut, non validées par une analyse de faux positifs en production réelle.
- Reprise toujours manuelle — aucun scheduler ne l'exécute automatiquement.
- Revue administrative simple, sans dossier d'enquête ni historique de décisions passées par
  compte.

## 11. État Git

`php artisan test` : 184/184. `pint --test` : vert. Frontend : format/lint/types/build verts.
Migration round-trip vérifié (2 migrations). Répertoire de travail propre après commit. Prêt pour
push et PR.

## 12. Chantier suivant recommandé

P011 — Temps réel, rapprochement et retraits utilisateur.
