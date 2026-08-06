# P010 — Antifraude, preuves renforcées et reprise

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `feccc7a` (P009 fusionné)
**Dépendances :** P009 (Feed, attention, crédit automatique)
**Spécifications :** `docs/16-moderation-securite-antifraude-globale-wasplex.md` (§17-20, §23-24),
`docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md` (§18, §21, §39-40)

## Rappel du point de départ (état réel du dépôt après P009)

`docs/chantiers/P009-CHANTIER.md` §2.4 avait explicitement différé l'antifraude complète :

> « Ce chantier valide la progression de façon simple et honnête : le serveur borne la durée
> visible déclarée par le temps réel écoulé depuis le début de la session […], sans conserver un
> journal détaillé de chaque heartbeat. Documenté comme limite explicite, pas comme une
> antifraude complète. »

Aujourd'hui, `AttentionService::heartbeat()` **borne silencieusement** une durée déclarée
excessive (`max(0, min($visibleDurationMs, $realElapsedMs))`) sans jamais l'enregistrer ni la
signaler — un dépassement n'a aucune conséquence au-delà du plafonnage du jour même. Il n'existe
aucune protection contre un rythme d'appel anormal (rafale de heartbeats), aucun état
intermédiaire entre « crédité » et « aucun gain » (`docs/16` §20 exige pourtant *preuve douteuse
→ hold → revue*), et la commande de reprise (`feed:release-expired-deliveries`) ne traite que les
réservations expirées — jamais une livraison `started` bloquée indéfiniment (client disparu,
crash navigateur) ni une éventuelle incohérence entre une livraison `completed` et l'absence de
`ledger_transaction_id` qui devrait pourtant toujours l'accompagner.

## 1. Objectif

```text
heartbeat suspect détecté (rythme anormal, dépassement du temps réel)
→ signal enregistré et compté sur la livraison
→ seuil atteint : mise en attente (hold), aucun crédit décidé
→ revue administrative : libérer (crédit normal) ou rejeter (aucun gain)
→ livraison bloquée ou orpheline : reprise (libère l'enveloppe, journalise, jamais de perte silencieuse)
```

Aucun crédit ne doit précéder une preuve jugée valide (`docs/16` §20 : « Aucun crédit définitif
ne doit précéder la validation »).

## 2. Décisions de réduction explicites

`docs/16` décrit un moteur de risque générique bien plus large (comptes, KYC, retraits,
modération de contenu, fermes de visionnage multi-appareils, score de risque par compte/appareil).
Comme pour le Super Moteur de P009, construire cette généralisation maintenant serait une
abstraction prématurée (`docs/CLAUDE.md` §5/§25) : un seul domaine consommateur réel existe
(l'attention publicitaire du Feed). Ce chantier se limite donc à :

1. **Pas de moteur de risque générique** (`risk_signal_definitions`/`risk_evaluations`/
   `risk_decisions`/`risk_scores` de `docs/16` §72). Les signaux sont détectés et comptés
   directement sur `feed_ad_deliveries`, avec des noms qui pourront être généralisés le jour où un
   deuxième domaine (Fonds, Live) en aura réellement besoin.
2. **Pas de fingerprinting d'appareil ni de détection de fermes multi-comptes** (`docs/16` §15,
   §16, §19). Rien dans P001-P009 ne collecte de `device_session_id`, d'empreinte d'appareil ou de
   signal réseau — en introduire un maintenant serait une nouvelle collecte de données sans
   décision fondateur explicite sur la vie privée (`docs/CLAUDE.md` §11/§15). Seuls les signaux
   calculables avec les données déjà existantes (rythme des heartbeats, écart entre durée déclarée
   et temps réel serveur) sont implémentés.
3. **Deux signaux concrets seulement**, faute de seuils chiffrés dans les spécifications (`docs/16`
   §18 reste qualitatif : « heartbeats impossibles », « vidéo accélérée », sans nombre) :
   - `heartbeat_rate_abuse` : deux heartbeats reçus plus vite que l'intervalle plausible du client
     (`FeedPanel.vue` envoie toutes les 400 ms) ;
   - `overclaimed_duration` : la durée déclarée dépasse le temps réel écoulé au-delà d'une
     tolérance technique (au-delà de ce que l'horloge serveur autorise).
   Les seuils numériques (`config/feed.php`) sont des valeurs techniques par défaut, ajustables,
   **pas une décision produit figée** — documenté explicitement plutôt que présenté comme final.
4. **Pas de détection de comportement inter-livraisons** (répétition parfaite, cadence
   identique sur plusieurs publicités) — non implémentable honnêtement sans données historiques
   suffisantes ni seuils spécifiés ; resterait un chantier ultérieur si le besoin est confirmé.
5. **Mise en attente (`held`) directement sur `feed_ad_deliveries`**, avec une seule table
   compagnon `feed_ad_delivery_holds` portant les champs exigés par `docs/16` §23 (montant, motif,
   durée, dossier, statut, issue) — pas d'entité générique `financial_holds` séparée pour d'autres
   domaines qui n'existent pas encore.
6. **Pas de moteur de règles versionnées** pour les seuils antifraude — `config/feed.php` est
   administrable par déploiement, pas par une interface d'édition de règles (ce serait de nouveau
   le moteur de risque générique).
7. **Revue administrative simple** (« libérer » / « rejeter » sur une livraison), sans file
   d'enquête complète façon `CampaignReviewCase` (P007) — le volume actuel ne le justifie pas.
8. **Reprise toujours manuelle**, cohérent avec P002-P009 : la commande existante
   `feed:release-expired-deliveries` est étendue (réservations expirées **et** livraisons
   `started` bloquées **et** vérification d'intégrité des livraisons `completed` sans transaction
   Grand Livre associée) plutôt que remplacée — aucun scheduler/Horizon introduit.
9. **Pas de retenue sur un retrait** — les retraits n'existent pas encore (P011). Une retenue ne
   bloque ici que le crédit initial d'un gain publicitaire.

## 3. Modèle de données

### `Feed` (étendu)

- `feed_ad_deliveries` — nouvelles colonnes :
  - `last_heartbeat_at` (nullable timestamp) — recommandé par `docs/07` §17.
  - `risk_signal_count` (unsigned integer, défaut 0).
  - `last_risk_signal_code` (nullable string).
  - statut : ajout de `held` et `rejected` à la contrainte CHECK existante
    (`reserved/started/completed/abandoned/expired/held/rejected`).
- `feed_ad_delivery_holds` (nouvelle table, append-then-resolve, propriété exclusive du module
  Feed) :
  - `id`, `feed_ad_delivery_id` (FK cascade), `amount_minor` (montant du gain suspendu),
    `reason_code` (motif — dernier signal ayant déclenché la mise en attente),
    `status` (`created/under_review/released/rejected/expired`, sous-ensemble de `docs/16` §24
    pertinent ici — `active`/`captured`/`compensated` omis, non applicables sans retrait),
    `opened_at`, `resolved_at` (nullable), `resolved_by` (nullable, compte admin),
    `resolution_note` (nullable, texte court — l'issue), `evidence` (jsonb — durée déclarée, durée
    réelle, nombre de signaux, dernier code).

## 4. Contrats internes (nouveaux ou étendus)

- Aucun nouveau contrat cross-module — la détection et la mise en attente restent internes au
  module Feed (aucun autre module n'a besoin de connaître un signal de risque publicitaire).
- `AttentionService` étendu : la détection de signaux et l'ouverture d'une retenue se produisent
  dans `heartbeat()` et `complete()`, réutilisant les contrats déjà consommés en P009
  (`CampaignEnvelopeContract`, `AdvertiserWalletReservationContract`, `UserWalletContract`) sans
  modification de leur surface.

## 5. Décision de mise en attente et de revue

```text
heartbeat() :
  intervalle depuis last_heartbeat_at < heartbeat_min_interval_ms
    → signal heartbeat_rate_abuse
  durée déclarée − durée réelle bornée > overclaim_tolerance_ms
    → signal overclaimed_duration

complete() :
  progress_percent < 100 → rejeté (422, inchangé depuis P009)
  risk_signal_count ≥ risk_hold_threshold
    → statut held, ouverture d'une retenue (feed_ad_delivery_holds), aucune capture, aucun crédit
  sinon → capture normale (inchangée depuis P009)

Revue administrative (capacité admin.feed.risk.review) :
  libérer  → capture normale (enveloppe + Grand Livre + Wallet), retenue → released
  rejeter  → libération de l'enveloppe sans gain (comme un abandon), retenue → rejected
```

Sans retenue ouverte au moment de `complete()`, le comportement de P009 est inchangé — aucune
régression pour le parcours honnête.

## 6. Reprise (commande étendue)

`feed:release-expired-deliveries` couvre désormais :

1. réservations `reserved`/`started` expirées (comportement P009, inchangé) ;
2. livraisons `started` sans heartbeat récent au-delà de
   `stuck_delivery_timeout_multiplier × required_duration_ms` (client disparu) — enveloppe
   libérée, statut `expired` ;
3. vérification d'intégrité : toute livraison `completed` sans `ledger_transaction_id` est
   impossible par construction (transaction atomique P009 §2.4/§20 du présent chantier) mais est
   activement recherchée et journalisée en erreur critique si trouvée — filet de sécurité,
   jamais un correctif silencieux (`docs/CLAUDE.md` §16).

## 7. API et capacités

Utilisateur (self-service) : aucun changement d'API — la détection est transparente au client
(`heartbeat`/`complete` gardent la même forme, `delivery.status` peut désormais valoir `held`, le
Feed traite cela comme « pas de gain immédiat », cohérent avec l'esprit du gain jamais garanti
avant preuve).

Administration (MFA récente + capacité dédiée `admin.feed.risk.review`) :

```text
GET  /api/admin/feed/holds
POST /api/admin/feed/holds/{id}/release
POST /api/admin/feed/holds/{id}/reject
```

## 8. Tests obligatoires

Rythme de heartbeats anormal déclenche un signal ; dépassement de durée déclarée déclenche un
signal ; seuil de signaux atteint → mise en attente sans crédit ni capture ; libération d'une
retenue crédite normalement (une seule fois, idempotent) ; rejet d'une retenue libère l'enveloppe
sans aucun gain ; un parcours honnête (P009 inchangé) n'accumule aucun signal et complète
normalement ; reprise libère une livraison `started` bloquée au-delà du délai ; reprise détecte
(sans les corriger silencieusement) les livraisons `completed` sans transaction associée ;
capacité admin requise pour la file de retenues et les actions de revue.

## 9. Critères de fin

Migrations + rollback propre, tests Pest verts (y compris les scénarios de non-régression P009),
Pint vert, qualité frontend verte (nouvel écran admin de revue des retenues), captures Playwright
d'un parcours réel de mise en attente et de revue, rapport, `docs/ROADMAP-INDEX.md` mis à jour, PR
en brouillon, CI verte, merge, resynchronisation de branche.
