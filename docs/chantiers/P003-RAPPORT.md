# P003 — RAPPORT DE CHANTIER

**Chantier :** P003 — Wallet annonceur
**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `800f78133b5924bdb17cf174d11b5c2e5e09187b` (P002 fusionné sur `main`)
**Statut :** `ready_for_review` — en attente d'autorisation du fondateur avant P004

## Contexte

Aucun `P003-CHANTIER.md` n'existait dans le corpus (la numérotation des
chantiers reconstruits saute de P002 à P004). Le périmètre a donc été
rédigé pour ce chantier (`docs/chantiers/P003-CHANTIER.md`) à partir de :

- `docs/06-wallet-et-grand-livre-wasplex.md` (structure des comptes, dépôt) ;
- `docs/13-studio-annonceur-wasplex.md` §24-30 et §89 (Wallet annonceur,
  rechargement, API) ;
- `docs/19-integrations-externes-wasplex.md` (contrats internes, adaptateur,
  webhooks, statuts normalisés) ;
- `docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md`, qui documente le
  **contrat réel** de l'API Marchand GeniusPay (base API, domaines de
  checkout, en-têtes `X-Webhook-*`, signature HMAC-SHA256 sur
  `timestamp.payload`, `status: null` possible en sandbox) — utilisé comme
  référence pour implémenter correctement l'intégration dès la première
  version, plutôt que de reproduire les erreurs que ce hotfix corrige.

Précision du fondateur (2026-08-05) : l'annonceur recharge effectivement
son Wallet depuis GeniusPay pour financer ses campagnes — l'intégration
GeniusPay faisait donc partie du périmètre de ce chantier dès le départ,
avec une exigence explicite de soin visuel (« usé de créativité »).

## Objectif

Donner à chaque organisation-annonceur un Wallet distinct du Wallet
personnel, alimenté par dépôt GeniusPay (sandbox), avec le Grand Livre
(P002) comme unique source de vérité.

## Réalisé

- module `App\Modules\AdvertiserWallet` (Domain/Application/Infrastructure/
  Http/Database/Events) : 3 migrations (`advertiser_wallets`,
  `advertiser_wallet_deposits`, `advertiser_wallet_deposit_events`) ;
- `PaymentProviderContract` (interface générique, docs/19 §6) implémenté par
  `GeniusPayAdapter` (sandbox exclusivement — refuse de démarrer avec un
  environnement différent de `sandbox` ou une clé contenant `live`, base API
  HTTPS obligatoire, allowlist stricte des domaines de checkout) ;
- cycle de vie du dépôt : `created` → `awaiting_payment` (checkout GeniusPay
  reçu, y compris quand le fournisseur renvoie `status: null` avec une URL
  de checkout — normalisé correctement, pas traité comme un échec) →
  `confirmed`/`credited` ou `rejected`/`expired` ;
- webhook GeniusPay (`POST /api/webhooks/geniuspay`, hors du groupe `web` —
  aucune session, aucun CSRF, un jeton de débit dédié `throttle:
  geniuspay-webhook`) : vérification de signature HMAC-SHA256 sur
  `timestamp.payload` avec tolérance d'horodatage (5 min), puis
  **revérification serveur systématique** via `fetchDepositStatus` avant
  tout crédit — jamais de confiance dans le corps du webhook seul ;
  idempotence stricte (dépôt déjà `credited`/`rejected`/`expired` → événement
  `duplicated`, aucune seconde écriture) ;
- crédit exclusivement via `LedgerPostingContract` (P002), avec une clé
  d'idempotence dérivée déterministe (`advertiser-deposit:{depositId}`) —
  double protection contre le double crédit (au niveau du Wallet et au
  niveau du Grand Livre) ;
- Wallet annonceur = projection pure (`AdvertiserWalletQueryService`) —
  solde disponible recalculé en sommant les écritures du Grand Livre pour
  le compte `advertiser.budget.available` de l'organisation, jamais stocké ;
- API annonceur (`/api/advertiser/wallet*`), gardée par une nouvelle chaîne
  d'autorisation : compte authentifié → session non révoquée → **espace
  actif = espace annonceur** (nouveau middleware
  `EnsureActiveAdvertiserOrganization`, résolu via `SpaceService` de
  Identity) → capacité (`advertiser.wallet.view` /
  `advertiser.wallet.deposit.create`), scopée à l'organisation ;
- onglet **Wallet annonceur** réel dans `AdvertiserShell` (remplace le
  placeholder P001) : carte de solde avec dégradé « valeur gagnée »
  (orange→or, docs/00 §6.3), montants en `tabular-nums`, sélection rapide de
  montant, ouverture du checkout GeniusPay dans un nouvel onglet, sondage
  du statut du dépôt en cours, effet de lueur dorée à la confirmation
  (`--shadow-wpx-reward`, docs/00 §13.2), historique des dépôts avec badges
  de statut colorés ;
- deux extensions additives et rétrocompatibles du Grand Livre (P002),
  documentées et testées : un nouvel `owner_type` `organization` sur
  `LedgerAccountReference`, et deux entrées supplémentaires au catalogue
  idempotent (`LIABILITY_ADVERTISER`, `wasplex.cash.clearing`) — le
  catalogue P002 était explicitement conçu pour grandir ainsi ;
- les deux capacités Wallet sont accordées automatiquement au créateur d'une
  organisation, au même titre que `organization.manage.self` (P001) — pas
  aux membres simplement invités, qui doivent les recevoir explicitement.

## Exclus (respecté)

Wallet personnel, transfert Wallet personnel → annonceur, retrait
annonceur, réservation/consommation de budget de campagne, tout
prestataire de paiement autre que GeniusPay, facturation/factures PDF,
dépôt supervisé administratif manuel, Studio Annonceur complet
(marques/médiathèque).

## Migrations

3 tables dans `app/Modules/AdvertiserWallet/Database/Migrations/`
(préfixe `2026_08_05_2000xx`) : `advertiser_wallets`,
`advertiser_wallet_deposits`, `advertiser_wallet_deposit_events`. Aller/
retour vérifié (`migrate:fresh` → `migrate:rollback --step=3` → `migrate`).

## API (5 routes)

`GET /api/advertiser/wallet`, `GET /api/advertiser/wallet/deposits`,
`POST /api/advertiser/wallet/deposits`,
`GET /api/advertiser/wallet/deposits/{deposit}` (toutes : auth + session +
espace annonceur actif + capacité scopée à l'organisation) ;
`POST /api/webhooks/geniuspay` (public, signé, hors session).

## Événements

Réutilise les événements du Grand Livre (P002) : `LedgerTransactionPosted`
au moment du crédit. Aucun événement métier dédié Wallet annonceur créé
dans ce chantier minimal (pas d'outbox/temps réel, hors périmètre).

## Permissions

`advertiser.wallet.view`, `advertiser.wallet.deposit.create` — accordées au
créateur de l'organisation (comme `organization.manage.self`), scopées à
cette organisation. Un membre invité n'y a pas accès tant qu'elles ne lui
sont pas explicitement accordées (testé).

## Invariants vérifiés

- le Wallet annonceur reste une projection du Grand Livre — aucune colonne
  de solde n'existe nulle part dans le module ;
- aucun crédit n'est possible depuis la redirection navigateur — seul un
  webhook signé, revérifié auprès de GeniusPay, déclenche un crédit
  (testé : un webhook falsifié est rejeté sans effet) ;
- le mode sandbox est obligatoire (l'adaptateur refuse de s'instancier avec
  un environnement ou une clé de production) ;
- toute commande externe possède une clé d'idempotence (dépôt, webhook via
  la clé Ledger dérivée) ;
- aucune donnée client GeniusPay n'est conservée — seules les références
  techniques (reference, statut brut, montant, devise) le sont.

## Difficulté résolue : middleware de rate limiting non défini

`throttle:api` (utilisé pour la route webhook) provoquait une
`MissingRateLimiterException` — ce projet, construit sans le
`RouteServiceProvider` par défaut de Laravel, ne définit aucun limiteur nommé
`api`. Corrigé en définissant un limiteur dédié `geniuspay-webhook` (120/min
par IP) directement dans `AdvertiserWalletServiceProvider::boot()`, plutôt
que de dépendre d'un limiteur global implicite.

## Difficulté résolue : portée de capacité hors route

`EnsureCapability` ne savait résoudre un périmètre (`scope_id`) que depuis
un paramètre de route Laravel littéral. Le Wallet annonceur détermine
l'organisation depuis **l'espace actif du compte** (via `SpaceService`), pas
depuis l'URL — il n'existe pas de `{organization}` dans
`/api/advertiser/wallet`. Étendu `EnsureCapability::resolveScope()` pour se
rabattre sur un attribut de requête du même nom quand aucun paramètre de
route ne correspond — extension mineure, rétrocompatible, du middleware déjà
conçu pour être réutilisé par tout module (Identity et Ledger l'utilisaient
déjà tel quel).

## Difficulté résolue : capture silencieuse d'un état d'erreur trompeur

Une première version du composant `AdvertiserWalletPanel.vue` n'avait pas
de gestion d'erreur sur le chargement initial : si l'appel API échouait
(ex. espace actif non-annonceur, capacité manquante), le composant affichait
silencieusement « 0 WP » et « Aucun dépôt », indiscernable d'un Wallet
réellement vide. Détecté en capturant une preuve d'écran où le solde credité
manuellement en base n'apparaissait pas — l'appel réseau échouait en
réalité avec `403 ADVERTISER_SPACE_REQUIRED` faute d'avoir basculé l'espace
actif au préalable. Corrigé en ajoutant un état d'erreur explicite et
visible (`loadError`) distinct de l'état « vide ».

## Tests exécutés

| Contrôle | Résultat |
|---|---|
| `composer test` (Pest 4) | Réussi — 65 tests, 352 assertions (31 Identity + 24 Ledger + 10 AdvertiserWallet), aucune régression |
| `composer lint:check` (Pint) | Réussi |
| `composer types:check` (Larastan) | **Non exécuté** — paquet non installable dans ce bac à sable réseau restreint (limite documentée depuis P000) |
| `npm run format:check` / `lint:check` / `types:check` / `build` | Réussis |
| `migrate:fresh` → `migrate:rollback --step=3` → `migrate` | Réussi, aller/retour propre |
| Parcours navigateur (Playwright/Chromium) : Wallet vide → formulaire de recharge → historique avec dépôt crédité (données injectées via le Grand Livre pour la démonstration visuelle, GeniusPay réel non joignable dans ce bac à sable) | Réussi, captures ci-dessous |

Détail des 10 tests Pest (`tests/Feature/AdvertiserWallet/`) :
- `DepositLifecycleTest` (6) : création avec `status: null` normalisé,
  crédit après webhook signé + revérification serveur, rejeu sans double
  crédit, signature invalide sans effet, référence inconnue sans effet,
  statut refusé sans crédit ;
- `AdvertiserWalletApiTest` (4) : non authentifié refusé, espace non-
  annonceur refusé, membre invité sans capacité refusé, isolation stricte
  entre deux organisations (liste, solde et détail d'un dépôt d'autrui
  tous inaccessibles).

Toutes les interactions GeniusPay sont simulées via `Http::fake()` avec les
formes de réponse exactes documentées dans le hotfix — aucun appel réseau
réel vers `geniuspay.ci` n'est fait, ni en test ni en local.

## Captures

Trois captures Playwright/Chromium, Studio Annonceur → onglet Wallet :

1. Wallet vide (compte fraîchement créé) — carte de solde dégradé orange→or,
   bouton « Recharger le Wallet ».
2. Formulaire de recharge ouvert, montant rapide 25 000 FCFA sélectionné,
   bouton dégradé bleu→cyan « Continuer vers GeniusPay ».
3. Wallet avec historique — solde 25 000 WP (crédité via le Grand Livre
   pour la démonstration), deux dépôts listés avec badges de statut
   (« Crédité » en vert, « En attente de paiement » en orange).

## Fichiers modifiés/créés (résumé)

- `app/Modules/AdvertiserWallet/` : module complet (34 fichiers — migrations,
  modèles, value objects, contrats, adaptateur GeniusPay, services,
  contrôleurs, routes, provider) ;
- `app/Modules/Ledger/` : `LedgerAccount::OWNER_TYPE_ORGANIZATION`,
  `LedgerAccountReference::forOrganization()`, catalogue étendu
  (`SeedLedgerCatalogCommand`) ;
- `app/Modules/Identity/` : `EnsureCapability::resolveScope()` (repli sur
  attribut de requête), `OrganizationRegistrationService` (capacités Wallet
  accordées au créateur) ;
- `config/services.php`, `.env.example`, `phpunit.xml` : configuration
  GeniusPay (sandbox uniquement) ;
- `resources/js/Components/AdvertiserWalletPanel.vue`,
  `resources/js/Pages/Identity/AdvertiserShell.vue` : onglet Wallet réel ;
- `resources/css/tokens.css` : `--shadow-wpx-reward` (docs/00 §13.2) ;
- `tests/Pest.php` : `createAdvertiserOrganization()`,
  `geniusPaySignedWebhook()` ;
- `tests/Feature/AdvertiserWallet/` : 2 fichiers de tests ;
- `tests/Feature/Ledger/LedgerCatalogSeedCommandTest.php` : compteurs mis à
  jour (10 types, 3 comptes système).

## Limites restantes

1. Larastan/PHPStan toujours non installable dans ce bac à sable réseau
   restreint (limite inchangée depuis P000).
2. Les chemins exacts de l'API GeniusPay au-delà de la base documentée
   (`/payments`, `/payments/{reference}`) sont une convention REST propre à
   cet adaptateur — le hotfix documente les en-têtes, la signature et les
   formes de réponse, pas la liste complète des chemins. À confirmer contre
   la documentation Marchand GeniusPay complète avant un premier paiement
   sandbox réel.
3. Aucun test exécuté contre le vrai bac à sable GeniusPay (réseau
   indisponible dans cet environnement) — uniquement `Http::fake()` avec les
   formes de réponse documentées. Une vérification sandbox réelle (comme
   celle réalisée pour le hotfix historique) reste à faire avant mise en
   production.
4. Pas de dépôt supervisé administratif (revue manuelle) — explicitement
   hors périmètre P003, cf. docs/13 §28.
5. Aucun relevé/export ni facturation — hors périmètre.

## Risques

- Le compte `wasplex.cash.clearing` (ASSET) reçoit tous les débits de dépôt
  sans distinction de prestataire ; si un second prestataire de paiement
  est ajouté plus tard, vérifier si un compte de clearing par prestataire
  devient nécessaire pour le rapprochement (docs/19 §25-26).
- Le sondage côté client (toutes les 3 s, 40 tentatives) est un mécanisme
  simple sans backoff ; à revisiter si le volume de dépôts simultanés
  augmente, ou remplacé par une diffusion temps réel dans un chantier
  dédié (P007 dans l'architecture générale).

## Décisions ouvertes pour le fondateur

- Confirmer les chemins d'API GeniusPay exacts (`/payments`,
  `/payments/{reference}`) contre la documentation Marchand officielle
  avant tout paiement sandbox réel.
- Valider le choix de rattacher le Wallet annonceur à l'organisation (pas à
  l'espace ni au compte individuel) — cohérent avec « le budget est partagé
  par l'équipe », mais implique que tout titulaire de capacité
  `advertiser.wallet.deposit.create` peut recharger pour toute l'équipe.

## Commit final proposé

```text
P003: Wallet annonceur alimenté par dépôt GeniusPay (sandbox)
```

## Chantier suivant recommandé

**P004 — Configurations, plans et classes**, après autorisation explicite
du fondateur.
