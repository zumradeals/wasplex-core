# P017 — Chantier : Administration centrale, lot 2 (Utilisateurs, Wallet & Grand livre, Annonceurs & campagnes, Permissions)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `c7da8d1` (main, après fusion P016)
**Déclencheur :** deuxième maquette fournie par le fondateur
(`design_handoff_admin_part2/Wasplex Admin.html` + `README.md`).

## Objectif

Refondre 4 écrans supplémentaires de l'Administration centrale, en poursuivant P016 :

1. **Utilisateurs** (nouveau).
2. **Wallet & Grand livre** (remplace `ledger` + fusionne `AdminReconciliationPanel.vue`).
3. **Annonceurs & campagnes** (fusionne `AdminAdvertisersPanel.vue` +
   `AdminCampaignReviewsPanel.vue` + `AdminAdvertisingPricingPanel.vue`).
4. **Permissions** (remplace `capabilities`, langage rôles plutôt que codes bruts).

Les écrans Vue d'ensemble, Ciblage publicitaire, Informations de profil (P016) ne sont pas
retouchés.

## Directive du fondateur — différence importante avec P016

Contrairement à P016 (« aucun backend, même minime »), la consigne explicite cette fois est :
**« je veux un résultat fidèle à la maquette même si pas encore codé côté backend, on le fera
plus tard si besoin »**. Ceci autorise à construire des écrans visuellement complets et
fonctionnels même quand le backend actuel ne les supporte pas encore, **à condition de ne
jamais fabriquer une donnée présentée comme réelle** (comptes, montants, noms inventés). La
règle CLAUDE.md « ne jamais fabriquer de donnée financière » reste absolue ; ce qui change,
c'est la tolérance à livrer des **contrôles d'action non branchés** (bouton visible, message
« Bientôt disponible » au clic) plutôt que masquer entièrement une fonctionnalité prévue par la
maquette.

Audit exhaustif du backend réalisé avant codage (recherche complète du dépôt) :

| Élément de la maquette | Statut réel | Décision |
|---|---|---|
| Liste/recherche de comptes (Utilisateurs) | **Absent** — aucune route `/admin/accounts` n'existe | Nouveau : `Admin/AccountsController@index/show` (lecture seule, capacité `admin.accounts.view`) — identifiants réels (`AccountIdentifier`), statut, date d'inscription réels. |
| Présence « en ligne » | Champ réel `AccountSession.last_active_at` existe mais jamais exposé à l'admin | Nouveau calcul réel dans `AccountsController` : en ligne = session non révoquée avec `last_active_at` récent (5 min). |
| Solde wallet d'un compte quelconque | Service réel `UserWalletQueryService::balanceMinor()` existe mais jamais appelé pour un compte autre que soi-même | Réutilisé tel quel via le contrat existant `UserWalletContract` — réel, aucune fabrication. |
| Abonnement d'un compte quelconque | Absent — aucune méthode de contrat par compte | Ajout d'une méthode à `SubscriptionsReportingContract` existant (`activePlanCodeForAccount`) — extension minime d'un contrat déjà utilisé en cross-module (précédent P012). |
| Espace annonceur d'un compte | Dérivable via les organisations dont le compte est membre (données Identity natives) | Réel : nom de l'organisation de type « annonceur » dont le compte est membre, si elle existe. |
| Vérification d'identité (KYC) | **Absent du tout** — aucun champ, aucun concept dans le code | « Bientôt disponible » — donnée sensible, jamais fabriquée même visuellement. |
| Restriction ciblée par capacité (Connexion / Retraits / Publier / Gagner des WP) | Colonnes `restricted_at`/`suspended_at` existent sur `accounts` mais ne sont **ni lues ni écrites nulle part** ; aucune notion de restriction *par capacité* n'existe | Interrupteurs visibles, conformes à la maquette, mais **non persistés** (`Bientôt disponible` au clic) — décision explicite de ne pas concevoir seul un mécanisme de sécurité aussi sensible sans validation du fondateur. |
| Bouton « Restreindre le compte » (global, pas par capacité) | Colonne `restricted_at` existe, jamais utilisée | **Réel** : nouveau `POST /admin/accounts/{id}/restrict` et `/unrestrict`, réutilise la colonne déjà présente en base (aucune migration requise). |
| Retraits en attente (Wallet & Grand livre, onglet « À valider ») | **Absent** — confirmé en P016, aucune notion de retrait dans le code | Onglet visible et fonctionnel dans sa structure, mais vide avec message honnête (« Aucune demande de retrait — fonctionnalité de retrait pas encore construite ») au lieu de cartes fictives. |
| Rôles nommés regroupant des capacités | **Absent** — uniquement des `CapabilityGrant` individuels, jamais de notion de rôle | Rôles définis **côté frontend uniquement** (bundles de codes de capacité réels), qui déclenchent plusieurs appels réels à `POST /admin/capabilities` (un par capacité du rôle) — aucune capacité inventée, uniquement des combinaisons des 34 codes déjà utilisés dans le code. |
| `/admin/ledger/accounts`, `/admin/ledger/corrections` | Réels, déjà fonctionnels, mais jamais appelés par aucun écran actuel | Branchés dans Wallet & Grand livre (onglet Grand livre). |
| `POST /admin/advertisers/{id}/restore` | Réel, jamais appelé | Ajouté au panneau Annonceurs & campagnes fusionné. |

## Décisions de fusion / architecture

- **Wallet & Grand livre** : nouveau composant unique remplaçant la section `ledger` +
  `AdminReconciliationPanel.vue`. 3 onglets : « À valider » (retraits — vide/honnête pour
  l'instant), « Toutes les opérations » (transactions Ledger + entrées de rapprochement
  fusionnées dans un même tableau, export CSV existant), « Grand livre » (comptes + corrections,
  endpoints réels jusque-là non branchés). Le dépôt supervisé annonceur
  (`createSupervisedDeposit`, déjà réel) est déplacé ici depuis Annonceurs & campagnes, comme
  suggéré par le handoff.
- **Annonceurs & campagnes** : nouveau composant unique remplaçant
  `AdminAdvertisersPanel.vue` + `AdminCampaignReviewsPanel.vue`. Présenté comme dossier de
  campagne (file d'attente + détail visuel). La modération des médias créatifs
  (`decideAsset`) et le catalogue de prix (`AdminAdvertisingPricingPanel.vue`) restent
  accessibles comme sections secondaires de cet écran (pas de perte de fonctionnalité).
- **Permissions** : remplace `capabilities`. Rôles humains en façade, capacités réelles en
  arrière-plan (voir tableau ci-dessus). Un mode avancé optionnel garde l'attribution/révocation
  individuelle de capacité pour les cas non couverts par un rôle.
- **Utilisateurs** : nouvel écran, nouveau contrôleur `Admin/AccountsController` (lecture) +
  actions de restriction globale réelles (voir tableau). Les 4 interrupteurs de restriction
  ciblée restent visuels uniquement pour l'instant.

## Nouvelle capacité

- `admin.accounts.view` : consultation de la liste/fiche compte (Utilisateurs).
- `admin.accounts.restrict` : restreindre/restaurer un compte (bouton global uniquement).

Ajoutées aux capacités du fondateur (`SeedFounderCommand`).

## Tests prévus

- Tests Pest pour `AccountsController` (liste, recherche, détail, restrict/unrestrict,
  autorisation par capacité).
- Test Pest pour la nouvelle méthode de `SubscriptionsReportingContract`.
- `npm run types:check`, `npx eslint .`, `npx prettier --check`.
- Parcours Playwright réel sur les 4 écrans avec captures.

## Limites assumées (à documenter dans le rapport final)

- Retraits : écran présent, non fonctionnel (aucun retrait n'existe dans le produit).
- Restriction ciblée par capacité (4 interrupteurs) : visuelle uniquement, non persistée.
- Vérification d'identité (KYC) : « Bientôt disponible », aucun champ réel.
- Présence en ligne : calculée en temps réel à l'affichage, pas de mise à jour live
  (pas de websocket dédié pour cet écran).
