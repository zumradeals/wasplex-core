# RAPPORT — P012 : Reporting et dashboards (première verticale)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `6039330` (P011-B fusionné)
**Chantier :** `docs/chantiers/P012-CHANTIER.md`
**Spécifications :** `docs/18-reporting-statistiques-audit-observabilite-wasplex.md` §4-6, §18-19,
§24-26, §36, §108-109
**Statut :** ready_for_review

`docs/18` découpe le reporting en 10 phases (pipeline analytique, audit métier, agrégats
économiques, dashboards, observabilité technique, alertes/incidents, qualité analytique, rapports
programmés, administration, stabilisation). Ce chantier livre la « première verticale » de §109 :
reporting Studio annonceur et dashboard fondateur, construits en lecture directe sur les données
déjà réelles de P002/P004/P006/P009 — pas le pipeline analytique complet.

## 1. Réalisé

### 1.1. Deux lacunes concrètes comblées

- L'annonceur voit désormais la performance réelle de sa campagne (livraisons, attention
  qualifiée, budget consommé) au lieu de rien du tout après approbation.
- L'onglet « Tableau de bord » du fondateur, un placeholder vide depuis P001, affiche désormais
  une vue économique consolidée réelle (Grand Livre, campagnes, Feed, abonnements).

### 1.2. Quatre nouveaux contrats de lecture, un par source

- `Feed\Application\Contracts\FeedCampaignStatsContract` : livraisons par campagne ou globales
  (source : `feed_ad_deliveries`, docs/18 §6 : « Feed → AdDelivered + QualifiedAttention »).
- `Ledger\Application\Contracts\LedgerReportingContract` : équilibre débit/crédit (docs/18 §36) et
  solde net par type de compte — donne directement le Wallet utilisateur et annonceur en
  circulation sans dupliquer de logique de projection.
- `Subscriptions\Application\Contracts\SubscriptionsReportingContract` : abonnements actifs,
  revenu total.
- `Campaigns\Application\Contracts\CampaignsReportingContract` : rapport par campagne, comparaison
  par organisation, synthèse budgétaire globale — consomme `FeedCampaignStatsContract`.

Un nouveau module `Reporting` (léger, sans migration) assemble ces quatre contrats pour le
dashboard fondateur — il ne connaît jamais une classe concrète d'un autre module, uniquement les
interfaces (`CLAUDE.md` §6).

### 1.3. Bug de robustesse trouvé et corrigé en cours de capture

En rejouant un crédit Feed réel sans `php artisan reverb:start` (oubli lors de la première
tentative de capture), `AttentionService::complete()` a répondu 500 — pas à cause du crédit
lui-même, mais parce que `WalletBalanceChanged::dispatch()` (P011) propage une
`BroadcastException` quand Reverb est injoignable, faisant échouer toute la réponse HTTP alors
que la transaction Grand Livre était déjà commitée. `docs/chantiers/P011-CHANTIER.md` §2.4
affirmait déjà que la diffusion devait être « best-effort » sans jamais menacer le crédit réel —
l'implémentation ne le garantissait pas. Corrigé : `UserWalletQueryService::notifyBalanceChanged()`
capture désormais toute exception de diffusion, journalise un avertissement, et ne la laisse
jamais remonter à l'appelant. Testé avec un vrai broadcaster `pusher` pointé vers un port
injoignable (`tests/Feature/Feed/FeedRealtimeTest.php`), pas un mock — le crédit continue de
répondre 200.

## 2. Décisions explicites (voir `docs/chantiers/P012-CHANTIER.md` §3)

1. Pas de pipeline analytique générique — lecture directe des tables OLTP existantes.
2. Pas d'audit métier append-only dédié (l'audit de sécurité de compte, P001, reste distinct).
3. Pas d'observabilité technique ni d'alertes/incidents — infrastructure SRE séparée.
4. Pas de réconciliation analytique (différent du rapprochement financier de P011-B).
5. Export CSV simple pour le reporting Studio ; pas de système de rapports programmés génériques.
6. Dashboard fondateur à contenu fixe ; pas de widgets configurables.
7. Aucun reporting pour Fonds/Alertes/Santé/Carte/Live/Notifications/Support (modules non
   implémentés).

## 3. Contrats internes

Détaillés en §1.2 ci-dessus.

## 4. Migrations

Aucune.

## 5. API

- `GET /api/advertiser/campaigns/{campaign}/report`
- `GET /api/advertiser/campaigns/report` (comparaison par organisation)
- `GET /api/admin/dashboard/summary`

## 6. UI

- `CampaignPerformancePanel.vue` : section « Performance » dans `CampaignsPanel.vue`, visible pour
  une campagne approuvée ou suspendue.
- `AdminDashboardPanel.vue` : onglet « Tableau de bord » de `AdminShell.vue`, désormais réel.

## 7. Permissions

Aucune nouvelle capacité — `advertiser.campaign.view` (reporting Studio, déjà existante) et
`admin.dashboard.view` (dashboard fondateur, P001) suffisent.

## 8. Tests exécutés

- `php artisan test` (Pest 4) — **208 tests, 2566 assertions, aucune régression** (200 avant ce
  chantier + 7 nouveaux dans `tests/Feature/Reporting/ReportingTest.php` + 1 nouveau dans
  `tests/Feature/Feed/FeedRealtimeTest.php` pour le correctif de robustesse) :
  - rapport de campagne correct après un parcours complet réel (financement → approbation →
    diffusion → attention → Grand Livre) ;
  - zéros propres pour une campagne sans livraison (pas de division par zéro) ;
  - comparaison de campagnes par organisation ;
  - refus d'un annonceur consultant une campagne d'une autre organisation (404) ;
  - Grand Livre toujours équilibré (`docs/18` §36) ;
  - dashboard fondateur cohérent avec un scénario connu (Wallet utilisateur, campagnes, Feed) ;
  - refus sans `admin.dashboard.view` (403) ;
  - le crédit Feed continue de répondre 200 même quand la diffusion `wallet.balance.changed`
    échoue contre un broadcaster réellement injoignable.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- Preuve réelle (Playwright/Chromium contre serveur Laravel + Vite + **Reverb** locaux) : parcours
  complet réel (dépôt annonceur seedé via le vrai service d'enregistrement d'organisation, campagne
  financée et approuvée, candidat réel exécutant le parcours Feed jusqu'à la complétion via des
  appels `curl` indépendants du navigateur) — panneau Studio affichant 1 livraison, 100 %
  d'attention, 675 WP distribués/consommés sur un budget cible de 300 000 WP ; dashboard fondateur
  affichant en un coup d'œil : Grand Livre équilibré (3 transactions, 900 675 WP débit = crédit),
  675 WP de Wallet utilisateur en circulation, 599 325 WP de Wallet annonceur en circulation, 1
  campagne active, 675 WP consommés, 100 % d'attention Feed, 4 abonnements actifs — tous les
  chiffres se recoupent correctement entre les deux écrans et avec les données réellement seedées.

## 9. Fichiers modifiés/ajoutés

```text
apps/platform/app/Modules/Feed/Application/Contracts/FeedCampaignStatsContract.php        (nouveau)
apps/platform/app/Modules/Feed/Application/ValueObjects/FeedCampaignStats.php             (nouveau)
apps/platform/app/Modules/Feed/Application/Services/FeedCampaignStatsService.php          (nouveau)
apps/platform/app/Modules/Feed/Infrastructure/Providers/FeedServiceProvider.php           (modifié)
apps/platform/app/Modules/Ledger/Application/Contracts/LedgerReportingContract.php        (nouveau)
apps/platform/app/Modules/Ledger/Application/ValueObjects/LedgerGlobalSummary.php         (nouveau)
apps/platform/app/Modules/Ledger/Application/Services/LedgerReportingService.php          (nouveau)
apps/platform/app/Modules/Ledger/Infrastructure/Providers/LedgerServiceProvider.php       (modifié)
apps/platform/app/Modules/Subscriptions/Application/Contracts/SubscriptionsReportingContract.php (nouveau)
apps/platform/app/Modules/Subscriptions/Application/ValueObjects/SubscriptionsSummary.php (nouveau)
apps/platform/app/Modules/Subscriptions/Application/Services/SubscriptionsReportingService.php (nouveau)
apps/platform/app/Modules/Subscriptions/Infrastructure/Providers/SubscriptionsServiceProvider.php (modifié)
apps/platform/app/Modules/Campaigns/Application/Contracts/CampaignsReportingContract.php  (nouveau)
apps/platform/app/Modules/Campaigns/Application/ValueObjects/CampaignReport.php           (nouveau)
apps/platform/app/Modules/Campaigns/Application/ValueObjects/CampaignsBudgetSummary.php   (nouveau)
apps/platform/app/Modules/Campaigns/Application/Services/CampaignReportingService.php     (nouveau)
apps/platform/app/Modules/Campaigns/Http/Controllers/Advertiser/CampaignReportingController.php (nouveau)
apps/platform/app/Modules/Campaigns/Http/routes/api.php                                   (modifié)
apps/platform/app/Modules/Campaigns/Infrastructure/Providers/CampaignsServiceProvider.php (modifié)
apps/platform/app/Modules/Reporting/**                                                    (nouveau module)
apps/platform/app/Modules/Wallet/Application/Services/UserWalletQueryService.php          (modifié — correctif robustesse)
apps/platform/bootstrap/providers.php                                                     (modifié)
apps/platform/resources/js/Components/CampaignPerformancePanel.vue                        (nouveau)
apps/platform/resources/js/Components/CampaignsPanel.vue                                  (modifié)
apps/platform/resources/js/Components/AdminDashboardPanel.vue                             (nouveau)
apps/platform/resources/js/Pages/Identity/AdminShell.vue                                  (modifié)
apps/platform/tests/Feature/Reporting/ReportingTest.php                                   (nouveau, 7 tests)
apps/platform/tests/Feature/Feed/FeedRealtimeTest.php                                     (modifié, 1 test ajouté)
docs/chantiers/P012-CHANTIER.md, P012-RAPPORT.md                                          (nouveaux)
```

## 10. Limites restantes

- Pas de pipeline analytique, d'audit métier transverse, d'observabilité technique,
  d'alertes/incidents, de réconciliation analytique, de rapports programmés génériques ni de
  widgets configurables (voir §2 pour la justification de chaque report).
- Aucun reporting pour les modules non encore implémentés (Fonds, Alertes, Santé, Carte, Live,
  Notifications, Support).
- Le dashboard fondateur additionne les soldes toutes devises confondues (le dépôt n'opère
  aujourd'hui qu'en WP) — à revisiter si une seconde devise est introduite.

## 11. État Git

`php artisan test` : 208/208. `pint --test` : vert. Frontend : format/lint/types/build verts.
Répertoire de travail propre après commit. Prêt pour push et PR.

## 12. Chantier suivant recommandé

P011-C — Retraits utilisateur (`docs/06` §22), conditionné à un chantier KYC minimal préalable ; ou
P013 — Stabilisation première verticale (`docs/ROADMAP-INDEX.md`).
