# P012 — Reporting et dashboards (première verticale)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `6039330` (P011-B fusionné)
**Dépendances :** P002 (Grand Livre), P006/P007 (Campagnes), P009 (Feed), P004 (Abonnements)
**Spécifications :** `docs/18-reporting-statistiques-audit-observabilite-wasplex.md` §4-6, §18-19,
§24-26, §36, §108-109

## 1. État réel constaté avant ce chantier

`docs/18` §108 découpe le module en 10 phases (registre d'événements analytique, audit métier,
agrégats économiques, dashboards métier, observabilité technique, alertes/incidents, qualité et
réconciliation analytique, rapports et exports, administration, stabilisation) — un système
d'observabilité et de business intelligence complet. Aucune de ces phases n'existe dans le dépôt.

Deux lacunes concrètes et visibles motivent ce premier chantier :

- L'annonceur ne voit **aucune donnée de performance** de sa campagne après approbation
  (`CampaignsPanel.vue` n'affiche que le statut) — alors que le budget est réellement consommé et
  que des livraisons réelles existent (`feed_ad_deliveries`).
- L'onglet « Tableau de bord » de `AdminShell.vue` est un **placeholder vide** (« bientôt
  disponible ») depuis P001 — aucune vue économique consolidée n'existe pour le fondateur, alors
  que chaque brique (Grand Livre, Wallet, campagnes, Feed, abonnements) existe déjà séparément.

## 2. Objectif

```text
Studio Annonceur : campagne approuvée et diffusée
→ livraisons réelles (Feed) + budget réellement consommé (Campaigns)
→ rapport de campagne (§24-26)

Fondateur : Grand Livre + Wallet + campagnes + Feed + abonnements
→ vue économique globale consolidée (§18-19), équilibre du Grand Livre vérifié (§36)
```

Correspond à la « première verticale à livrer » de `docs/18` §109 (financement → approbation →
diffusion → attention → Grand Livre → Wallet → **reporting Studio** → **dashboard fondateur**).

## 3. Réduction de périmètre explicite

1. **Pas de pipeline analytique générique** (`docs/18` §7, §13-14 : registre d'événements
   versionnés, bus, collecteur, stockage brut, transformations). Chaque indicateur est calculé en
   lecture directe depuis les tables OLTP du module propriétaire (`docs/18` §6 : « chaque indicateur
   déclare sa source »), cohérent avec la discipline déjà appliquée en P010/P011 (ne pas construire
   de moteur générique avant un besoin réel démontré).
2. **Pas d'audit métier append-only dédié** (Phase 2). Un audit de sécurité de compte existe déjà
   (`account_audit_events`, P001) ; un registre d'audit métier transverse (preuves d'action sur
   toute décision) est un chantier à part entière, hors périmètre « reporting et dashboards ».
3. **Pas d'observabilité technique ni d'alertes/incidents** (Phases 5-6) — infrastructure SRE
   distincte (logs structurés, métriques, traces, health checks, files, dead-letter, routage
   d'alertes), sans rapport direct avec le reporting fonctionnel demandé ici.
4. **Pas de réconciliation analytique** (Phase 7, fraîcheur/qualité des données) — à ne pas
   confondre avec le rapprochement financier GeniusPay déjà livré en P011-B ; sans pipeline
   analytique, cette phase n'a pas d'objet.
5. **Pas de système de rapports programmés ni d'exports massifs génériques** (Phase 8) — un export
   CSV simple est inclus pour le reporting Studio (même schéma que l'export de P011-B), pas un
   système de programmation.
6. **Pas de widgets configurables** (Phase 9) — dashboard fondateur à contenu fixe pour ce premier
   passage.
7. **Aucun reporting pour Fonds/Alertes/Santé/Carte/Live/Notifications/Support** — modules non
   implémentés dans ce dépôt (`docs/ROADMAP-INDEX.md` : P014-P020 « En attente »).

## 4. Contrats internes (nouveaux)

- `App\Modules\Feed\Application\Contracts\FeedCampaignStatsContract` (Feed, étend le rôle déjà
  joué par `FeedDashboardController`) : `statsForCampaign()`, `statsForCampaigns()`,
  `globalStats()`.
- `App\Modules\Ledger\Application\Contracts\LedgerReportingContract` (Ledger, nouveau) :
  `globalSummary()` — équilibre débit/crédit (`docs/18` §36) et solde net par type de compte
  (donne directement le Wallet utilisateur et le Wallet annonceur en circulation, sans dupliquer
  de logique de projection).
- `App\Modules\Subscriptions\Application\Contracts\SubscriptionsReportingContract`
  (Subscriptions, nouveau) : `summary()` — abonnements actifs, revenu total.
- `App\Modules\Campaigns\Application\Contracts\CampaignsReportingContract` (Campaigns, nouveau) :
  `reportFor()`, `reportForOrganization()` (consomme `FeedCampaignStatsContract`),
  `globalBudgetSummary()`.

## 5. Migrations

Aucune — lecture pure sur les tables existantes.

## 6. API

- `GET /api/campaigns/{campaign}/report` (annonceur, propriétaire de la campagne).
- `GET /api/campaigns/report` (annonceur, comparaison agrégée par organisation, `docs/18` §26).
- `GET /api/admin/dashboard/summary` (fondateur, capacité `admin.dashboard.view` existante).

## 7. UI

- `CampaignsPanel.vue` : section « Performance » sur la campagne sélectionnée.
- `AdminShell.vue` : onglet « Tableau de bord » réellement rempli (remplace le placeholder).

## 8. Permissions

Aucune nouvelle capacité — `admin.dashboard.view` (P001) couvre déjà le dashboard fondateur ; le
reporting Studio est self-service (propriété de la campagne vérifiée comme le reste du module
Campaigns).

## 9. Tests

- Rapport de campagne : livraisons/budget corrects après un parcours complet (financement →
  approbation → diffusion → complétion) ; campagne sans livraison → zéros, pas d'erreur division.
- Comparaison de campagnes au niveau organisation.
- Équilibre du Grand Livre (`docs/18` §36) : `totalDebitMinor === totalCreditMinor` toujours vrai.
- Dashboard fondateur : chiffres cohérents avec un scénario connu (crédit Wallet, budget campagne,
  abonnement).
- Refus sans capacité `admin.dashboard.view` ; refus d'un annonceur consultant une campagne d'une
  autre organisation.

## 10. Critères de fin

Code conforme, tests verts, `pint --test` vert, `npm run format/lint/types:check/build` verts,
captures réelles, rapport de chantier, mise à jour de `docs/ROADMAP-INDEX.md`, limites explicites.
