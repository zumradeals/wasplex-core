# P002 — RAPPORT DE CHANTIER

**Chantier :** P002 — Grand Livre minimal
**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `0b15b515f7a8d3ca7a2670697242590ed1efec1c` (P001-B fusionné sur `main`)
**Statut :** `ready_for_review` — en attente d'autorisation du fondateur avant P003

## Contexte

Ce rapport remplace intégralement l'ancien rapport P002 (branche
`codex/p002-ledger-core`, code aujourd'hui supprimé lors de la
réinitialisation du dépôt). Il documente la reconstruction de P002 à partir
de `docs/chantiers/P002-CHANTIER.md`, sur la base de l'identité (P001) et de
l'identité visuelle officielle (P001-B) déjà fusionnées.

## Objectif

Établir la source de vérité financière append-only de Wasplex en double
entrée, avant toute projection Wallet, réservation ou opération de paiement.

## Précision de conception : respect littéral de la liste de migrations

`docs/chantiers/P002-CHANTIER.md` fixe une liste explicite et fermée de 7
tables (`ledger_account_types`, `ledger_journals`, `ledger_accounts`,
`ledger_transactions`, `ledger_entries`, `ledger_transaction_links`,
`ledger_idempotency_keys`). J'ai respecté cette liste littéralement plutôt
que d'ajouter des tables supplémentaires pour deux besoins fonctionnels
explicitement demandés par ailleurs dans le chantier :

- **« audit des consultations et journalisation des divergences »** : au
  lieu d'une 8ᵉ table (`ledger_audit_events`), qui aurait dupliqué un
  mécanisme déjà établi et qui aurait fait porter à Ledger une table
  supplémentaire hors de la liste fixée, j'utilise le canal de logs
  structurés déjà mis en place en P000 (`config/logging.php`, canal
  `structured`, `storage/logs/wasplex.json.log`). `LedgerAuditLogger`
  journalise chaque consultation admin et chaque déséquilibre détecté.
- **séparation de tâches propose/approve des corrections** (capacités
  `wallet.correction.propose`/`wallet.correction.approve`) : au lieu d'une
  table `ledger_correction_requests` dédiée, le workflow réutilise
  `ledger_transactions.status` (`pending_approval` → `posted`/`rejected`)
  avec les colonnes `created_by`/`approved_by`/`approved_at` déjà prévues
  sur cette même table.

## Réalisé

- module `App\Modules\Ledger` (Domain/Application/Infrastructure/Http/
  Database/Console/Events), 7 migrations de tables + 1 migration de
  triggers PostgreSQL ;
- catalogue comptable : 9 familles de comptes (`docs/06 §7` : ASSET,
  LIABILITY_USER, REVENUE, EXPENSE, RESERVATION, CLEARING, PARTNER, FONDS,
  REFUND), 1 journal principal, 2 comptes système (`wasplex.suspense`,
  `wasplex.rounding`), seedés par la commande idempotente
  `ledger:seed-catalog` ;
- comptes (`ledger_accounts`) résolus/créés à la volée par
  `LedgerAccountResolver`, identifiés par `(code, owner_type, owner_id)` —
  `owner_type`/`owner_id` référencent un compte Identity **par valeur**,
  jamais par contrainte FK inter-module (docs/CLAUDE.md §6) ;
- `LedgerPostingContract`/`LedgerPostingService` : seul point d'entrée
  interne pour écrire dans le Grand Livre — vérifie l'équilibre débit =
  crédit par devise, la positivité des montants, la cohérence de devise,
  calcule une empreinte SHA-256 du contenu économique, gère l'idempotence
  (rejeu identique → même transaction ; contenu différent → conflit 409 ;
  course entre deux appels concurrents → récupération de la transaction
  gagnante après échec de contrainte unique) ;
- `LedgerCompensationContract`/`LedgerCompensationService` : `proposeReversal`
  (transaction `pending_approval`, écritures inversées, lien `reversal` vers
  l'originale), `approve` (poste définitivement, refuse l'auto-approbation
  du proposeur), `reject` (statut `rejected`, jamais publié) ;
- `LedgerQueryService` : consultation en lecture seule (liste/détail des
  transactions, liste des comptes) — aucun calcul de solde/projection
  (explicitement hors périmètre) ;
- immutabilité à deux niveaux : applicative (aucune méthode du module
  n'expose de mutation sur une ligne publiée) et PostgreSQL (triggers
  `BEFORE UPDATE OR DELETE` sur les 4 tables d'écritures, avec exception
  levée dès que la transaction parente est `posted`, tout en laissant les
  lignes `pending_approval` mutables tant qu'elles ne sont pas publiées) ;
- événements `LedgerTransactionPosted`, `LedgerTransactionReversed`,
  `LedgerImbalanceDetected`, dispatchés après commit (ou avant rejet pour le
  déséquilibre, puisqu'aucune transaction ne peut alors avoir été commise) ;
- API admin (`/api/admin/ledger/*`), gardée par `EnsureRecentMfa` +
  `EnsureCapability` (réutilisation directe des middlewares Identity,
  génériques par conception) : consultation (`wallet.ledger.view` pour la
  liste, `wallet.audit.view` pour le détail — deux niveaux distincts et
  intentionnels) et corrections (`wallet.correction.propose`/`approve`) ;
  **invariant respecté : aucune route HTTP de posting générique** — la
  seule façon de créer une transaction normale est le contrat PHP interne,
  réservé aux futurs modules (P003+) ;
- onglet « Grand Livre » dans `AdminShell` : liste réelle des transactions +
  détail des écritures au clic, consommant l'API ci-dessus (pas de
  placeholder — la consultation en lecture seule est explicitement dans le
  périmètre P002) ;
- capacités `wallet.*` ajoutées à `identity:seed-founder` (Identity ne
  connaît pas leur signification, seulement leur code — le système de
  capacités reste agnostique du domaine).

## Exclus (respecté)

Wallet, solde projeté et historique utilisateur ; réservations, captures et
libérations ; dépôts, retraits, transferts et prestataires externes ;
budget publicitaire et gain utilisateur ; interface financière utilisateur ;
outbox et notifications temps réel.

## Migrations

8 fichiers dans `app/Modules/Ledger/Database/Migrations/` (préfixe
`2026_08_05_1900xx`) : les 7 tables listées ci-dessus, plus une migration de
triggers d'immutabilité (aucune table supplémentaire — uniquement des
fonctions et triggers PL/pgSQL). Aller/retour vérifié
(`migrate:fresh` → `migrate:rollback --step=8` → `migrate`).

## API (6 routes, toutes sous `/api/admin/ledger`)

`GET /transactions` (`wallet.ledger.view`), `GET /transactions/{id}`
(`wallet.audit.view`), `GET /accounts` (`wallet.ledger.view`),
`POST /corrections` (`wallet.correction.propose`),
`POST /corrections/{id}/approve` et `POST /corrections/{id}/reject`
(`wallet.correction.approve`). Toutes exigent une session authentifiée non
révoquée et une preuve MFA récente (15 minutes), comme la console
d'administration Identity.

## Événements

`LedgerTransactionPosted`, `LedgerTransactionReversed`,
`LedgerImbalanceDetected` — événements Laravel synchrones (pas d'outbox,
explicitement exclue de ce chantier).

## Permissions

`wallet.ledger.view`, `wallet.correction.propose`, `wallet.correction.approve`,
`wallet.audit.view` — accordées au fondateur via `identity:seed-founder`,
comme les capacités `admin.*` de P001.

## Invariants vérifiés

- aucune transaction déséquilibrée n'est commise (testé) ;
- aucune écriture n'utilise de nombre flottant (`Money`, montants entiers
  stricts, `amount_minor > 0` contraint aussi au niveau PostgreSQL) ;
- toutes les écritures d'une transaction partagent devise (testé —
  mélange de devises refusé) ;
- le Grand Livre ne stocke aucun solde d'autorité (aucune colonne
  `balance` nulle part) ;
- un rejeu idempotent retourne la transaction initiale (testé) ;
- une clé réutilisée avec un contenu différent est refusée (testé, 409) ;
- transactions, écritures et liens publiés ne sont ni modifiés ni supprimés
  — vérifié directement en base (tentatives `UPDATE`/`DELETE` SQL brutes
  rejetées par PostgreSQL, pas seulement par le code applicatif) ;
- une correction inverse les sens dans une nouvelle transaction (testé) ;
- les clients HTTP ne disposent d'aucune route de posting générique
  (vérifié par l'absence de route correspondante).

## Difficulté résolue : ordre des opérations dans l'approbation d'une correction

Premier essai de `LedgerCompensationService::approve()` : je mettais à jour
le statut de la transaction en `posted` **avant** de mettre à jour
`posted_at` sur ses écritures. Le trigger PostgreSQL sur `ledger_entries`
vérifie le statut **courant** de la transaction parente via une
sous-requête — et à l'intérieur d'une même transaction PostgreSQL, cette
sous-requête voit déjà l'écriture non commise faite juste avant (« lecture
de ses propres écritures »). Résultat : la mise à jour des écritures
échouait avec « entries of a posted transaction cannot be modified »,
alors même que l'intention était légitime (une transition encore en cours
de la même opération d'approbation). Corrigé en inversant l'ordre : les
écritures sont mises à jour **avant** que la transaction elle-même ne
passe à `posted`. Ce bug a été détecté directement par
`LedgerCorrectionTest` (`composer test`), pas par relecture manuelle.

## Difficulté résolue : middleware perdu par un second appel `->middleware()`

La route admin Ledger était enregistrée avec
`Route::middleware([...])->prefix(...)->middleware([...])->group(...)` —
un premier appel à `->middleware(['auth', EnsureSessionNotRevoked::class])`
suivi d'un second `->middleware([EnsureRecentMfa::class])`. Le second appel
**remplace** le premier sur le registrar fluide de Laravel au lieu de le
fusionner : `route:list -v` a confirmé que seuls `web` et `EnsureRecentMfa`
étaient réellement appliqués, `auth` et `EnsureSessionNotRevoked` ayant
disparu. Conséquence en test : `EnsureRecentMfa` ne trouvait jamais
`account_session` sur la requête (jamais posé par `EnsureSessionNotRevoked`,
qui n'était plus dans la pile) et renvoyait systématiquement
`401 MFA_REQUIRED`, même après une vérification MFA réussie. Corrigé en
fusionnant tous les middlewares dans un seul appel
`->middleware(['auth', EnsureSessionNotRevoked::class, EnsureRecentMfa::class])`.

## Tests exécutés

| Contrôle | Résultat |
|---|---|
| `composer test` (Pest 4) | Réussi — 55 tests, 256 assertions (31 Identity + 24 Ledger), aucune régression |
| `composer lint:check` (Pint) | Réussi |
| `composer types:check` (Larastan) | **Non exécuté** — paquet non installable dans ce bac à sable réseau restreint (limite inchangée depuis P000) |
| `npm run format:check` / `lint:check` / `types:check` / `build` | Réussis |
| `migrate:fresh` → `migrate:rollback --step=8` → `migrate` | Réussi, aller/retour propre |
| Vérification directe PostgreSQL des triggers d'immutabilité (`UPDATE`/`DELETE` SQL bruts sur des lignes publiées) | Réussi — rejetés avec le message d'exception attendu |
| Parcours navigateur (Playwright/Chromium) : fondateur → enrôlement MFA → `/admin` → onglet Grand Livre (liste + détail des écritures) | Réussi, captures ci-dessous |

Détail des 24 tests Pest (`tests/Feature/Ledger/`) :
- `LedgerCatalogSeedCommandTest` — seed + idempotence (2 tests) ;
- `LedgerPostingTest` — équilibre, devise, positivité, rejeu idempotent,
  conflit de clé, course simulée entre deux appels concurrents (6 tests) ;
- `LedgerImmutabilityTest` — triggers PostgreSQL sur transactions/écritures/
  clés d'idempotence, mutabilité conservée tant que `pending_approval` (5 tests) ;
- `LedgerCorrectionTest` — proposition, approbation par un tiers, refus
  d'auto-approbation, rejet, double décision refusée, original introuvable (6 tests) ;
- `LedgerAdminApiTest` — non authentifié, MFA manquante, capacité manquante,
  consultation autorisée, parcours complet propose→approve avec deux
  comptes admin distincts (5 tests).

## Captures

Deux captures prises via Chromium/Playwright, parcours réel de bout en
bout (connexion fondateur → enrôlement MFA → console d'administration) :

1. `/admin` — onglet **Grand Livre**, liste des transactions (type, statut,
   devise, date de comptabilisation).
2. Même onglet — détail des écritures d'une transaction sélectionnée
   (compte, sens, montant), démontrant l'équilibre débit/crédit.

## Fichiers modifiés/créés (résumé)

- `app/Modules/Ledger/` : module complet (migrations, modèles, value
  objects, contrats, services, exceptions, événements, contrôleurs, routes,
  provider, commande console) ;
- `app/Modules/Identity/Console/SeedFounderCommand.php` : ajout des 4
  capacités `wallet.*` ;
- `bootstrap/providers.php` : `LedgerServiceProvider` ;
- `resources/js/Pages/Identity/AdminShell.vue` : onglet Grand Livre ;
- `tests/Pest.php` : généralisation de `grantFounderAccessForTests()`
  (déplacée depuis `MfaTest.php` pour être réutilisable sans dépendre de
  l'ordre de chargement des fichiers de test) et ajout des factories
  `ledgerSuspense()`/`ledgerUserAvailable()` ;
- `tests/Feature/Identity/MfaTest.php` : référence désormais le helper
  partagé (comportement inchangé) ;
- `tests/Feature/Ledger/` : 5 fichiers de tests.

## Limites restantes

1. Larastan/PHPStan toujours non installable dans ce bac à sable réseau
   restreint (même blocage documenté depuis P000).
2. Le test de concurrence (« deux workers ») simule la course en appelant
   `post()` deux fois séquentiellement avec la même clé — cela exerce le
   même chemin de code que la récupération après violation de contrainte
   unique, mais ne lance pas deux threads/processus réellement en
   parallèle (Pest s'exécute de façon synchrone). Documenté comme
   limitation assumée, cohérent avec l'approche adoptée en P001 pour des
   contraintes similaires.
3. Aucun compte utilisateur réel n'est créé par ce chantier — les comptes
   Ledger observés dans les captures sont créés à la demande par les
   tests/démonstrations, pas par un flux métier (qui arrivera avec P003+).
4. Pas d'outbox : les événements sont dispatchés de façon synchrone
   in-process, conformément au périmètre exclu de P002. Les futurs
   chantiers consommateurs devront ajouter leur propre mécanisme fiable
   s'ils ont besoin de garanties de livraison plus fortes.

## Risques

- Le bug d'ordre d'écriture dans `approve()` (voir ci-dessus) illustre un
  piège général avec les triggers d'immutabilité conditionnels : toute
  future modification de ce service doit continuer à mettre à jour les
  lignes dépendantes **avant** de faire passer leur transaction parente à
  `posted`.
- Le bug de middleware perdu (voir ci-dessus) peut resurgir dans tout futur
  module qui chaîne `->middleware()` plusieurs fois sur le même
  enregistrement de route ; le réflexe correct est un seul appel avec un
  tableau fusionné.

## Décisions ouvertes pour le fondateur

- Valider l'interprétation retenue pour « audit des consultations » (logs
  structurés plutôt qu'une table dédiée) et pour le workflow propose/approve
  (statuts sur `ledger_transactions` plutôt qu'une table de requêtes de
  correction) — les deux découlent du respect littéral de la liste de
  migrations fixée par le chantier.
- Confirmer que P003 (Wallet annonceur) peut consommer
  `LedgerPostingContract` tel quel, sans ajustement de son contrat.

## Commit final proposé

```text
P002: Grand Livre minimal en double entrée (comptes, transactions, écritures, corrections)
```

## Chantier suivant recommandé

**P003 — Wallet annonceur** (dépôt, solde disponible, réservation,
historique), après autorisation explicite du fondateur.
