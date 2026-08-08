# P017 — Rapport de chantier : Utilisateurs, Wallet & Grand livre, Annonceurs & campagnes, Permissions

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `c7da8d1` (P016 fusionné)
**Objectif :** voir `docs/chantiers/P017-CHANTIER.md`.

Consigne du fondateur pour ce chantier : *« je veux un résultat fidèle à la maquette même si pas
encore codé côté backend, on le fera plus tard si besoin »* — contrairement à P016 (frontend
strict, zéro backend), ce chantier autorise des ajouts backend **modestes, en lecture, et
réutilisant des contrats internes déjà en place**, chaque fois que c'est la seule façon de montrer
un contenu réel plutôt qu'une donnée inventée. Aucune donnée financière ou de gain n'est
fabriquée ; le Grand Livre reste en écriture append-only exclusivement via les circuits déjà
existants (aucune écriture directe de solde introduite dans ce chantier).

Réalisé en 4 lots, chacun commité et poussé séparément :

| Lot | Écran | Commit |
|---|---|---|
| 1/4 | Permissions (remplace « Capacités ») | `773121c` |
| 2/4 | Wallet & Grand livre (fusionne Rapprochement) | `8b32966` |
| 3/4 | Annonceurs & campagnes (fusionne Annonceurs + Revue de campagnes + Tarifs) | `253bea9` |
| 4/4 | Utilisateurs (nouvel écran) | *(ce commit)* |

---

## Lot 1/4 — Permissions

- **Nouveau** `resources/js/lib/adminRoles.ts` : 4 rôles humains (Support client, Responsable
  finances, Responsable publicité, Lecture seule), chacun défini comme un ensemble de codes de
  capacité réels déjà utilisés ailleurs dans le produit — aucune capacité inventée. `FOUNDER_CAPABILITIES`
  liste les capacités du fondateur ; `roleForCapabilities()` fait une correspondance par ensemble
  pour dériver un libellé d'affichage.
- **Nouveau** `Components/AdminPermissionsPanel.vue` : liste « Équipe d'administration » groupée
  par compte, formulaire « + Inviter une personne » (compte + rôle → N appels
  `POST /admin/capabilities`, un par capacité du rôle — aucun endpoint de lot invoqué), cartes
  « Rôles disponibles », bascule « Mode avancé » qui restitue l'ancien tableau brut
  capacité/portée/expiration pour les cas non couverts par les 4 rôles.
- Remplace la section `capabilities` de `AdminShell.vue`, qui contenait auparavant l'ensemble du
  code de gestion des capacités en ligne.

## Lot 2/4 — Wallet & Grand livre

- **Nouveau** `Components/AdminWalletLedgerPanel.vue`, 3 onglets :
  - **À valider** : état honnête — aucune fonctionnalité de retrait n'existe dans Wasplex à ce
    jour, message clair plutôt qu'une file vide déguisée en fonctionnalité.
  - **Toutes les opérations** : 3 cartes KPI (Dépôts/Retraits du jour → « Bientôt disponible »,
    aucune agrégation par jour n'existe côté Ledger ; Comptes équilibrés → réel via
    `/admin/dashboard/summary`), table fusionnant transactions réelles et écritures de
    rapprochement, action « Lancer un rapprochement » + export CSV, formulaire de dépôt supervisé
    déplacé depuis l'ancien panneau.
  - **Grand livre** : table réelle `/admin/ledger/accounts` et formulaires réels de correction
    (proposer/approuver/rejeter, `/admin/ledger/corrections`) — endpoints qui existaient déjà côté
    API mais n'étaient pas exposés dans l'interface.
- **Supprimé** `Components/AdminReconciliationPanel.vue` (fusionné, zéro référence restante
  vérifiée avant suppression).

## Lot 3/4 — Annonceurs & campagnes

- **Nouveau** `Components/AdminCampaignsPanel.vue` : colonne gauche « À examiner (N) » + campagnes
  actives avec suspension inline ; colonne droite dossier de campagne complet (marque, aperçu
  média 9:16, audience, budget, actions Approuver/Demander correction/Rejeter) ; sections
  rétractables « Voir les comptes annonceurs » (marques à vérifier, comptes, modération média,
  action `restore` précédemment inutilisée) et « Voir les tarifs publicitaires » (catalogue de
  prix administrable).
- **Supprimé** `Components/AdminAdvertisersPanel.vue`, `AdminAdvertisingPricingPanel.vue`,
  `AdminCampaignReviewsPanel.vue` (fusionnés, zéro référence restante vérifiée avant suppression).
- Bug de mise en page corrigé (deux liens de bascule collés sur une même ligne) — capturé et
  vérifié visuellement avant commit.

## Lot 4/4 — Utilisateurs (nouvel écran)

Premier écran admin de lecture sur un compte quelconque (jusqu'ici tous les autres endpoints
Identity ne lisaient que le compte connecté), plus une action de restriction globale réelle et
réversible.

### Backend ajouté

- **Nouveau** `AccountDirectoryService` (`Modules/Identity/Application/Services`) : `search()`
  (recherche par identifiant ou id), `detail()` (compte + statut en ligne calculé par fenêtre de
  5 min sur `AccountSession` + solde wallet via `UserWalletContract` + classe économique via
  `SubscriptionsReportingContract` + organisations natives Identity), `restrict()`/`unrestrict()`.
  Le nom de l'« espace annonceur » est dérivé exclusivement des tables natives Identity
  (`Organization`/`OrganizationMembership`, filtrées sur `type === 'advertiser'`) plutôt que du
  module AdvertiserStudio, pour respecter la frontière de module (CLAUDE.md §6) — conséquence :
  seul le nom de l'organisation est affiché, pas son statut de vérification.
- **Nouveau** `AccountsController` (`Modules/Identity/Http/Controllers/Admin`) : 4 routes sous
  `/api/admin/accounts`, chacune journalisée dans l'audit (`AccountViewed`, `AccountRestricted`,
  `AccountUnrestricted`) via `AuditLogger` déjà existant.
- `Account::$fillable` : ajout de `restricted_at` (colonne déjà en base depuis la migration
  d'origine, castée en datetime, mais jamais mass-assignable jusqu'ici) + méthode `isRestricted()`.
- `SubscriptionsReportingContract` (module Subscriptions, contrat déjà réel et déjà consommé en
  cross-module depuis P012) : nouvelle méthode `activeEconomicClassCodeForAccount(string $accountId): ?string`
  — pas un nouveau contrat inventé, une extension d'un contrat existant.
- `SeedFounderCommand` : ajout des capacités `admin.accounts.view` et `admin.accounts.restrict`.

### Nouvelles routes

```
GET  /api/admin/accounts                          admin.accounts.view
GET  /api/admin/accounts/{account}                admin.accounts.view
POST /api/admin/accounts/{account}/restrict        admin.accounts.restrict
POST /api/admin/accounts/{account}/unrestrict       admin.accounts.restrict
```

Toutes protégées par `EnsureCapability`, `EnsureRecentMfa` (fenêtre 15 min) et
`EnsureSessionNotRevoked` comme le reste des routes `/admin`.

### Frontend

- **Nouveau** `Components/AdminUsersPanel.vue` : colonne gauche recherche + liste de comptes
  (avatar initiales, libellé rouge si restreint) ; colonne droite fiche compte (badge Actif/Restreint,
  point vert « en ligne », bouton « Restreindre/Restaurer le compte » réel), 4 chiffres clés
  (Solde wallet réel, Abonnement réel, Espace annonceur réel ou « Aucun », Vérification identité
  → « Bientôt disponible », aucun module KYC n'existe), 5 onglets :
  - **Compte** : carte « Que peux-tu limiter sur ce compte ? » avec 4 interrupteurs
    (Connexion/Retraits/Publier des publicités/Gagner des WP). **Visuels uniquement** — au clic,
    notice transitoire « Bientôt disponible », rien n'est persisté. Une vraie application de ces
    4 restrictions granulaires demanderait une conception produit/sécurité non discutée à ce jour
    (propagation dans les middlewares d'auth, de retrait, de publication et de gain à travers
    plusieurs modules) ; seule la restriction globale du compte (bouton principal) est réelle.
  - **Wallet** : solde réel, historique détaillé → « Bientôt disponible » (aucun endpoint
    d'historique par compte n'existe côté admin).
  - **Abonnement** : classe économique réelle ou « Aucun abonnement actif ».
  - **Organisations** : liste réelle des adhésions natives Identity.
  - **Historique** : « Bientôt disponible » (aucun endpoint d'audit consultable par compte
    n'existe — `AuditLogger` écrit mais n'expose aucune lecture filtrée par ressource).
- **Nouveau** icône `users` dans `Components/AdminNavIcon.vue` (silhouette à deux personnes,
  fidèle à la maquette).
- `Pages/Identity/AdminShell.vue` : nouvelle entrée de navigation « Utilisateurs » (2ᵉ position,
  après Vue d'ensemble).

---

## Navigation finale (11 entrées, était 12 avant fusion + 1 nouvelle)

```
Vue d'ensemble · Utilisateurs · Permissions · Wallet & Grand livre · Abonnements ·
Annonceurs & campagnes · Informations de profil · Ciblage publicitaire · Feed ·
Organisations · Audit
```

(`Capacités` → `Permissions`, `Rapprochement` fusionné dans `Wallet & Grand livre`,
`Annonceurs` + `Revue de campagnes` + `Tarifs publicitaires` fusionnés dans
`Annonceurs & campagnes`, `Utilisateurs` nouvel écran.)

## Tests exécutés

- **Nouveaux tests Pest** :
  - `tests/Feature/Identity/AdminAccountsTest.php` (5 tests, 62 assertions) : recherche + détail
    avec capacité, refus sans capacité, dérivation réelle de l'organisation annonceur par
    adhésion native Identity, restriction/déblocage réel et réversible, séparation stricte
    `admin.accounts.view` ≠ `admin.accounts.restrict`.
  - `tests/Feature/Subscriptions/SubscriptionsReportingContractTest.php` (2 tests, 6 assertions) :
    `null` sans abonnement, code réel `FREE` pour un abonnement souscrit via le vrai
    `SubscriptionService::subscribe()`.
- **Suite complète** : `php artisan test` → **216/216 tests, 2636 assertions, 0 échec**
  (209 avant P017 + 7 nouveaux, aucune régression).
- **Qualité frontend** : `npm run types:check` (0 erreur), `npx eslint .` (0 erreur sur les
  fichiers touchés), `npx prettier --write` (1 fichier reformaté : `AdminUsersPanel.vue`).
- **Qualité backend** : `./vendor/bin/pint --test` sur tous les fichiers PHP touchés — 0 changement
  nécessaire.
- **Vérification visuelle Playwright** (Laravel `serve` + Vite dev, Chromium 1440×900, fondateur
  fraîchement seedé, MFA TOTP enrôlé et vérifié en direct) pour les 4 lots, captures dans le
  scratchpad de session : liste Permissions + mode avancé, 3 onglets Wallet & Grand livre, liste +
  dossier Annonceurs & campagnes, et pour Utilisateurs : liste, recherche, fiche compte (solde,
  abonnement, espace annonceur réels), notice « Bientôt disponible » au clic sur un interrupteur
  granulaire, bascule réelle Restreindre → badge « Restreint » + point en ligne conservé →
  Restaurer → retour à « Actif ».

## Limites assumées

- Retraits : aucune fonctionnalité de retrait n'existe dans Wasplex — onglet « À valider »
  honnête plutôt qu'une file vide déguisée.
- Les 4 interrupteurs de restriction granulaire (Connexion/Retraits/Publicités/Gains) sont
  visuels uniquement ; seule la restriction globale du compte est appliquée réellement.
- Aucune vérification d'identité (KYC) n'existe : « Bientôt disponible » assumé.
- Aucun historique d'audit consultable par compte n'existe côté API — onglet « Historique »
  honnête.
- « Espace annonceur » n'affiche que le nom de l'organisation (dérivé des tables natives
  Identity), pas son statut de vérification, pour respecter la frontière de module envers
  AdvertiserStudio.
- Dépôts/Retraits « du jour » (KPI Wallet & Grand livre) : aucune agrégation quotidienne n'existe
  côté Ledger — « Bientôt disponible » assumé depuis le lot 2.

## Décisions ouvertes pour le fondateur

- Faut-il concevoir et implémenter une vraie application des 4 restrictions granulaires
  (Connexion/Retraits/Publicités/Gains), et si oui selon quel mécanisme (flags par compte
  consultés dans quels middlewares/services, à travers quels modules) ?
- Faut-il un module KYC dédié, et à quel moment de la roadmap ?
- Faut-il exposer un historique d'audit filtré par ressource (compte) côté API admin ?

## État Git

Working tree propre après ce commit. Branche `claude/wasplex-reconstruction-7ujym7`, à jour avec
`origin/main` plus les 4 commits de ce chantier. Aucune PR n'a encore été ouverte pour P017 — sera
ouverte en brouillon après ce commit, dans l'attente d'une validation visuelle du fondateur avant
fusion (même méthode que P014/P015/P016).

## Chantier suivant recommandé

Selon la roadmap et les décisions ouvertes ci-dessus : conception du mécanisme réel de
restriction granulaire de compte, ou traitement d'un nouveau lot de maquettes si le fondateur en
fournit un.
