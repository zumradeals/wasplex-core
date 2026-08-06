# RAPPORT — P004 : Configurations, plans et classes économiques

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `f784543` (P003 + P001-C fusionnés)
**Chantier :** `docs/chantiers/P004-CHANTIER.md`
**Spécification :** `docs/03-abonnements-et-classes-economiques-wasplex.md`
**Statut :** ready_for_review

Ce rapport remplace l'ancienne version pré-réinitialisation (branche `codex/p004-economic-configuration`,
conservée dans l'historique Git à titre d'audit mais ne décrivant plus l'état réel du dépôt).

---

## 1. Objectif

Construire le socle des classes économiques (FREE/PREMIUM/GOLD/PLATINUM), des plans commerciaux
versionnés, du cycle d'abonnement utilisateur complet (souscription, paiement GeniusPay,
renouvellement, upgrade immédiat, downgrade programmé, expiration), et du compteur de quota
publicitaire mensuel — infrastructure de configuration prête à être consommée par le futur
Super Moteur (P008/P009), sans qu'aucun appelant réel n'existe encore.

## 2. Réalisé

### 2.1. Extraction du contrat de paiement partagé

`PaymentProviderContract`/`GeniusPayAdapter` (construits dans `AdvertiserWallet` en P003) ont été
déplacés vers `app/Shared/Payments/` — un abonnement payé par GeniusPay a besoin exactement du
même contrat qu'un dépôt annonceur. Décision documentée dans `P004-CHANTIER.md` §3 : ce n'est pas
un service métier global (interdit par CLAUDE.md §6), uniquement une préoccupation technique
transverse (§10). `AdvertiserWallet` a été mis à jour de façon purement mécanique
(`createDeposit`→`createPayment`, `fetchDepositStatus`→`fetchPaymentStatus`, value objects
renommés) sans aucun changement de comportement — vérifié par la suite de tests P003 intégrale,
toujours verte.

### 2.2. Les 12 tables exactes de docs/03 §22

`economic_classes`, `economic_class_versions`, `subscription_plans`, `subscription_plan_versions`,
`plan_economic_class_links`, `user_subscriptions`, `subscription_cycles`,
`subscription_quota_counters`, `subscription_events`, `subscription_payments`,
`subscription_refunds`, `subscription_entitlements` — aucune table supplémentaire, même discipline
que les 7 tables fermées du Grand Livre (P002).

### 2.3. Catalogue économique (`subscriptions:seed-catalog`, idempotent)

Quotas et poids **exacts** de la décision finale §28 : Gratuit 120/10 %, Premium 300/20 %,
Gold 600/35 %, Platine 900/35 % (somme = 100 %, vérifié par test). Coefficients de ciblage repris
de l'exemple illustratif §13 (1,00/1,15/1,35/1,60) — administrables et versionnés, jamais codés en
dur dans la logique métier.

**Décision explicite : aucun prix n'a été inventé pour les plans payants.** Aucun montant FCFA
pour Premium/Gold/Platine n'existe dans `docs/` — en inventer un aurait été une décision produit
silencieuse (CLAUDE.md §2). Seul le plan Gratuit est seedé `published` (prix 0, réellement
gratuit) ; Premium/Gold/Platine sont seedés `draft` à prix 0, structurellement complets mais
jamais publiés automatiquement. Un admin doit fixer le vrai prix via
`PATCH /api/admin/subscriptions/plans/{id}` puis publier explicitement.

### 2.4. `SubscriptionQuotaContract` (consommation/restauration/remise à zéro)

Contrat interne autonome (`consume`, `restore`, `currentCounter`), testé sans aucun appelant réel
puisque P008/P009 n'existent pas. Idempotence par clé fournie par l'appelant (colonne dédiée sur
`subscription_events`, contrainte unique). Cycle mensuel civil en UTC — la nuance "timezone du
pays principal de l'utilisateur" de §8 est différée car Identity ne stocke pas encore de fuseau
horaire par compte (limite documentée ci-dessous). Récupération de course gérée via savepoint +
capture de violation d'unicité, même discipline que P002.

### 2.5. `SubscriptionService` — cycle de vie complet

Souscription (`subscribe`), renouvellement (`renew`), upgrade immédiat (`upgrade`), downgrade
programmé (`downgrade` + `applyScheduledDowngrades`), expiration (`expireOverdue`), annulation
(`cancel`). Les plans gratuits (prix 0) activent immédiatement sans jamais appeler le prestataire.
Les plans payants suivent exactement la discipline P003 : paiement créé → webhook signé →
**revérification serveur obligatoire** → activation — jamais depuis le corps du webhook seul ni
une redirection navigateur.

**Bug trouvé et corrigé par les tests** : lors d'un upgrade, seul `quota_consumed` était ajusté ;
`quota_allocated` du cycle en cours restait celui de l'ancienne classe. Corrigé pour réallouer
explicitement le cycle courant au nouveau quota de classe, conformément à l'exemple exact de
`docs/03` §17 (Premium 300, déjà consommé 80, passage Gold 600 → 520 restants) — désormais couvert
par un test dédié.

**Bug d'accès de relation Eloquent découvert** : `$model->economic_class` (snake_case) ne résout
**pas** la relation `economicClass()` — contrairement à la sérialisation JSON qui, elle,
convertit bien les clés de relation en snake_case (deux mécanismes Eloquent distincts, vérifié
empiriquement). Toutes les lectures PHP directes utilisent désormais le nom de méthode exact
(`economicClass`, jamais `economic_class`).

### 2.6. API utilisateur et administration (docs/03 §24-25)

Toutes les routes de la spécification, à l'identique. **Décision d'interprétation** : `docs/03`
§24 ne liste qu'un point d'entrée `upgrade`, sans route de downgrade dédiée, bien que §18 décrive
un comportement de déclassement distinct. Interprété comme "changer de plan" : le serveur compare
le quota de la classe cible à celui de la classe actuelle et choisit lui-même entre upgrade
immédiat payant (`SubscriptionService::upgrade`) et downgrade programmé gratuit
(`SubscriptionService::downgrade`) — documenté dans le contrôleur et ce rapport plutôt que
silencieux. De même, `{id}` dans les routes admin de plans désigne un `subscription_plan_versions`
id (c'est là que vit tout l'état versionné mutable), la spec ne désambiguïsant pas plan vs
plan-version.

Capacités ajoutées au fondateur : `admin.subscriptions.plans.manage`,
`admin.subscriptions.classes.manage`.

### 2.7. UI

- `SubscriptionPanel.vue` intégré dans "Mon Espace" (UserShell) : abonnement actuel avec barre de
  progression du quota, comparatif des plans publiés, bouton de souscription. État d'erreur
  explicite distinct de l'état vide (même discipline que le Wallet annonceur, P003).
- `AdminSubscriptionsPanel.vue` intégré dans un nouvel onglet "Abonnements" (AdminShell) : classes
  économiques avec validation du total des poids (100 %), table des plans avec publier/suspendre.
- Captures : voir section 5.

### 2.8. Automatisation

`subscriptions:apply-scheduled-downgrades` et `subscriptions:expire-overdue`, planifiées
quotidiennement (`routes/console.php`) — aucun scheduler temps réel n'existe dans ce bac à sable,
les commandes sont testées directement (appel synchrone dans les tests).

## 3. Décisions explicites (résumé)

1. Extraction du contrat de paiement vers `app/Shared/Payments/` (§2.1).
2. Aucun prix inventé pour les plans payants — seed en `draft` à 0, jamais publiés automatiquement (§2.3).
3. Cycle mensuel en UTC, pas encore par timezone-pays (limite, §2.4).
4. Pas de route HTTP dédiée au downgrade — le endpoint `upgrade` fait office de "changer de plan" et route en interne (§2.6).
5. `{id}` des routes admin de plans = plan-version id, pas plan id (§2.6).
6. Aucune écriture au Grand Livre pour la recette d'abonnement — non demandé par `docs/03`, non documenté dans `docs/06` (limite, périmètre exclu du chantier).
7. Cancel n'interrompt pas l'accès immédiatement (aligné sur la doctrine "aucun retrait rétroactif" de §18-19, appliqué par extension à l'annulation faute de spécification explicite dans le corpus).

## 4. Tests exécutés

- `composer test` (Pest 4) — **99 tests, 536 assertions, aucune régression** (69 avant ce
  chantier + 30 nouveaux : 4 catalogue, 7 quota, 9 cycle de vie, 6 admin, 3 utilisateur, 1 isolation
  cross-compte).
- Couverture explicite des tests obligatoires `docs/03` §26 : quotas 120/300/600/900 ;
  préchargement sans consommation (le contrat ne consomme que sur appel explicite) ; consommation
  réelle ; gain indépendant du quota (aucun moteur de gain n'existe, testé comme non couplé) ;
  remise à zéro idempotente ; upgrade avec quota préservé exactement selon l'exemple §17 ;
  downgrade programmé puis appliqué ; expiration ; épuisement du quota (`QuotaExhaustedException`) ;
  poids à 100 % ; historique immuable (`subscription_events` append-only).
- `composer lint:check` (Pint) — corrigé un écart de style automatiquement, puis vert.
- `composer types:check` (Larastan) — non exécuté, paquet non installable dans ce bac à sable
  réseau restreint (limite documentée depuis P000).
- `npm run format:check` / `lint:check` / `types:check` / `build` — tous verts.
- `migrate:fresh` → `migrate:rollback --step=12` → `migrate` — aller-retour propre.
- Toutes les interactions GeniusPay simulées via `Http::fake()` — aucun appel réseau réel.
- Parcours navigateur (Playwright/Chromium) : inscription → souscription Gratuit → barre de quota
  visible ; connexion fondateur → MFA → onglet Abonnements → classes/poids/plans visibles.

## 5. Captures

- Mon Espace, avant souscription (aucun abonnement actif, plan Gratuit affiché).
- Mon Espace, après souscription (abonnement actif, 0/120 publicités, 120 restantes).
- Administration, onglet Abonnements (classes économiques + validation des poids à 100 % +
  table des plans avec Gratuit publié et Premium/Gold/Platine en brouillon).

## 6. Fichiers modifiés/ajoutés

```text
app/Shared/Payments/                                            (nouveau, extrait de AdvertiserWallet)
app/Modules/AdvertiserWallet/...                                (modifié — renommage mécanique)
app/Modules/Subscriptions/                                      (nouveau module complet)
  Database/Migrations/ (12 fichiers)
  Infrastructure/Models/ (12 modèles)
  Infrastructure/Providers/SubscriptionsServiceProvider.php
  Application/Services/ (SubscriptionService, SubscriptionQuotaService, webhook handler, exceptions)
  Http/Controllers/User/ (PlansController, SubscriptionsController)
  Http/Controllers/Admin/ (SubscriptionPlansController, EconomicClassesController)
  Http/Controllers/Webhook/SubscriptionPaymentWebhookController.php
  Http/routes/ (api.php, webhook.php)
  Console/ (SeedEconomicCatalogCommand, ApplyScheduledDowngradesCommand, ExpireOverdueSubscriptionsCommand)
app/Modules/Identity/Console/SeedFounderCommand.php             (modifié — capacités P004)
routes/console.php                                              (modifié — schedule)
bootstrap/providers.php                                          (modifié)
resources/js/Components/SubscriptionPanel.vue                    (nouveau)
resources/js/Components/AdminSubscriptionsPanel.vue               (nouveau)
resources/js/Pages/Identity/UserShell.vue                        (modifié)
resources/js/Pages/Identity/AdminShell.vue                        (modifié)
tests/Feature/Subscriptions/ (5 fichiers, 30 tests)
docs/chantiers/P004-CHANTIER.md, P004-RAPPORT.md                 (réécrits)
```

## 7. Migrations, API, événements, permissions

- **Migrations** : 12 tables (§2.2), aucune modification de tables existantes hors renommages
  internes du contrat de paiement (pas de changement de schéma AdvertiserWallet).
- **API** : voir §2.6, exactement `docs/03` §24-25 avec les deux interprétations documentées.
- **Événements** : `SubscriptionSelected/PaymentReceived/Activated/Renewed/Upgraded/
  DowngradeScheduled/Expired/Suspended`, `AdQuotaConsumed/Restored/Reset` — tous en base
  (`subscription_events`), append-only.
- **Permissions** : `admin.subscriptions.plans.manage`, `admin.subscriptions.classes.manage`
  (nouvelles), API utilisateur en self-service (aucune capacité spéciale, session valide
  suffisante).

## 8. Limites restantes

- Cycle de quota en UTC, pas encore par fuseau horaire du pays principal du compte (`docs/03` §8) —
  Identity ne stocke pas ce fuseau pour l'instant.
- Aucun renouvellement automatique par mandat récurrent — seul le renouvellement manuel existe ;
  un prélèvement récurrent nécessiterait un contrat que `docs/19` ne spécifie pas.
- Aucune écriture au Grand Livre pour la recette d'abonnement — non demandé explicitement.
- `validateWeights` somme tous les poids courants globalement, sans distinguer pays/devise — correct
  tant qu'une seule version par classe existe (cas actuel), à revoir si le multi-pays est activé.
- Premium/Gold/Platine restent en brouillon tant que le fondateur n'a pas fixé de vrai prix.
- `subscriptions:apply-scheduled-downgrades`/`expire-overdue` sont planifiées mais aucun worker de
  scheduler réel ne tourne dans ce bac à sable — testées par appel direct uniquement.

## 9. État Git

`composer test` : 99/99. `lint:check` : vert. Frontend : format/lint/types/build verts. Prêt pour
commit, push et PR.

## 10. Chantier suivant recommandé

P005 — Studio Annonceur, marques et financement.
