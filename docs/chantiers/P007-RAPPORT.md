# RAPPORT — P007 : Revue administrative et activation de campagne

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `d92d1ae` (P006 fusionné)
**Chantier :** `docs/chantiers/P007-CHANTIER.md`
**Spécifications :** `docs/13-studio-annonceur-wasplex.md` §53-60, §93-100 ; `docs/12`, `docs/16`, `docs/18` (généralités)
**Statut :** ready_for_review

Ce rapport remplace l'ancienne version pré-réinitialisation (branche `codex/p007-campaign-admin-review`,
conservée dans l'historique Git à titre d'audit mais ne décrivant plus l'état réel du dépôt
reconstruit). Sa politique financière par décision et l'option de séparation demandeur/décideur
ont été reprises (cohérentes et déjà correctement raisonnées) mais adaptées aux conventions
actuelles du dépôt.

---

## 1. Objectif

Donner à l'administration une file de revue pour les campagnes soumises (P006) : approuver,
demander une correction, rejeter, ou suspendre une campagne déjà approuvée — sans jamais capturer
le budget ni activer le Feed (P008/P009, non construits).

## 2. Réalisé

### 2.1. Dossier de revue et historique append-only

`campaign_review_cases` (un dossier par cycle de soumission) et `campaign_review_events`
(append-only : `submitted`/`changes_requested`/`resubmitted`/`approved`/`rejected`/`suspended`).
Chaque soumission ou resoumission ouvre un nouveau dossier — l'historique complet d'une campagne
qui a été corrigée plusieurs fois reste traçable.

### 2.2. Extension du cycle de vie de campagne

`campaigns.status` accepte désormais `changes_requested`, `approved`, `rejected`, `suspended` en
plus des statuts P006. `campaign_versions.status` n'a pas été étendu (décision §3.5 du chantier) —
rien ne le lit au-delà de ce que P006 vérifiait déjà.

### 2.3. Politique financière par décision

| Décision | Réservation |
|---|---|
| Soumission / correction / resoumission / approbation / suspension | conservée |
| Rejet | **libérée** |

`AdvertiserWalletReservationContract` a été étendu avec `release()` (nouvelle méthode, même
discipline que `reserve()` : poste au Grand Livre via `LedgerPostingContract`, jamais de mutation
directe de solde). Une suspension conserve la réservation — contrairement à un rejet, elle reste
réactivable sans nouveau financement (décision §3.4, reprise de l'analyse pré-réinitialisation,
cohérente et documentée plutôt que réinventée).

### 2.4. Correction et resoumission sans second financement

`PATCH /campaigns/{id}` reste utilisable quand le statut est `changes_requested` — contrairement à
une édition en `quoted`/`funded` (qui invalide le devis et repasse en `draft`), l'édition pendant
`changes_requested` **ne** repasse **pas** en `draft` : l'annonceur doit explicitement appeler
`POST /campaigns/{id}/resubmit`, qui rouvre un dossier de revue sans jamais retoucher le Wallet —
le budget déjà réservé reste verrouillé (vérifié par test : le nombre de
`campaign_budget_reservations` ne change jamais entre soumission et resoumission).

### 2.5. Séparation optionnelle demandeur/décideur

`config('campaigns.review_require_distinct_decider')` (défaut `false`,
`CAMPAIGNS_REVIEW_REQUIRE_DISTINCT_DECIDER`) : si activée, l'administrateur qui a demandé une
correction ne peut pas décider (approuver/rejeter) la resoumission correspondante
(`SameAdminCannotDecideException`, 403).

**Bug trouvé et corrigé pendant les tests** : `opened_at`/`decided_at` sont des colonnes
`timestamp` à précision de la seconde — deux dossiers ouverts dans la même seconde (soumission
puis resoumission immédiate, cas fréquent en test automatisé) pouvaient être départagés dans le
mauvais ordre par un tri uniquement sur le timestamp. Corrigé en ajoutant l'id ULID (monotone à la
création) comme critère de tri secondaire dans `Campaign::latestReviewCase()` et
`CampaignReviewService::assertDistinctDecider()`.

### 2.6. API et permissions

Routes admin (`docs/13` §93) : `GET/POST /api/admin/campaign-reviews[/{id}]`, decide
(`approve`/`request-changes`/`reject`), plus `POST /api/admin/campaigns/{id}/suspend`. Une route
supplémentaire non listée par `docs/13` §93 a été ajoutée par nécessité :
`GET /api/admin/campaigns/approved` — sans elle, l'administration n'aurait aucun moyen de
retrouver une campagne approuvée à suspendre (documenté explicitement plutôt qu'ajouté en
silence). Côté annonceur : `POST /api/advertiser/campaigns/{id}/resubmit` (`docs/13` §90).

Capacités : `admin.campaign-reviews.view`, `admin.campaign-reviews.decide`,
`admin.campaigns.suspend` — accordées explicitement au fondateur, aucune autorité dérivée du seul
rôle (même discipline que tous les autres domaines admin de ce dépôt).

### 2.7. UI

- `AdminCampaignReviewsPanel.vue` (nouvel onglet "Revue de campagnes" d'`AdminShell`) : file
  d'attente, fiche de dossier (objectif, média, audience, devis), boutons de décision, liste des
  campagnes approuvées avec suspension.
- `CampaignsPanel.vue` (Studio Annonceur) étendu : bannière de correction avec motif affiché,
  bouton "Resoumettre la campagne", états terminaux (approuvée/rejetée/suspendue) affichés
  distinctement à l'étape Soumission de l'assistant P006.

## 3. Décisions explicites (résumé, voir aussi `docs/chantiers/P007-CHANTIER.md` §3)

1. Pas de table `campaign_review_tasks` séparée — le dossier de revue sert déjà de file d'attente.
2. Pas de table `campaign_status_events` séparée — `campaign_review_events` couvre déjà toutes les
   transitions pertinentes.
3. Pas de commande de bootstrap — aucune campagne héritée dans le dépôt reconstruit.
4. Suspension conserve la réservation (réactivable), rejet la libère (terminal).
5. `campaign_versions.status` non étendu.
6. Widget "dashboard fondateur" simplifié en un compteur dans le panneau de revue lui-même, plutôt
   que le cadre générique `admin_dashboards` de `docs/12` §69 (hors périmètre).
7. Route `GET /api/admin/campaigns/approved` ajoutée par nécessité, au-delà de la liste exacte de
   `docs/13` §93.

## 4. Tests exécutés

- `php artisan test` (Pest 4) — **152 tests, 1220 assertions, aucune régression** (142 avant ce
  chantier + 10 nouveaux).
- Couverture explicite des tests obligatoires (§10) : campagne soumise visible dans la file ;
  refus sans capacité (403) ; demande de correction exige un motif et conserve la réservation ;
  correction et resoumission sans second financement (une seule réservation avant/après) ;
  approbation conserve la réservation ; aucune décision rejouée sur un dossier déjà décidé (409) ;
  rejet libère la réservation exactement une fois et bloque toute édition ultérieure (422) ;
  suspension d'une campagne approuvée conserve la réservation ; refus de suspendre une campagne
  non approuvée (422) ; séparation demandeur/décideur appliquée uniquement si configurée.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- `migrate:rollback --step=3` → `migrate` — aller-retour propre sur les 3 nouvelles migrations.
- Parcours navigateur (Playwright/Chromium) : campagne soumise avec 27 abonnés GOLD synthétiques
  (devis réel, 74 événements estimés) → admin ouvre le dossier → demande une correction avec motif
  → annonceur voit la bannière de correction et le motif dans le Studio → corrige le titre →
  resoumet sans nouveau financement → admin approuve → campagne visible dans "Campagnes
  approuvées" → admin suspend avec motif → campagne disparaît de la liste des approuvées.

## 5. Captures

- Administration, onglet Revue de campagnes : fiche de dossier avec objectif, audience, devis, et
  les trois boutons de décision (`p007-admin-review-fiche.png`).
- Studio Annonceur, étape Soumission : bannière "Correction demandée par l'administration" avec le
  motif et le bouton de resoumission (`p007-studio-changes-requested.png`).
- Administration : file vide, campagne approuvée listée avec bouton Suspendre
  (`p007-admin-approved-list.png`).
- Administration : campagne suspendue, disparue de la liste des approuvées
  (`p007-admin-suspended.png`).

## 6. Fichiers modifiés/ajoutés

```text
app/Modules/Campaigns/Database/Migrations/
  2026_08_06_120000_extend_campaigns_status_for_review.php   (nouveau)
  2026_08_06_120001_create_campaign_review_cases_table.php   (nouveau)
  2026_08_06_120002_create_campaign_review_events_table.php  (nouveau)
app/Modules/Campaigns/Infrastructure/Models/
  CampaignReviewCase.php, CampaignReviewEvent.php            (nouveau)
  Campaign.php                                                (modifié — statuts, latestReviewCase)
app/Modules/Campaigns/Application/Services/
  CampaignReviewService.php                                   (nouveau)
  CampaignService.php                                         (modifié — submit/resubmit/updateVersion)
  CampaignReviewCaseNotFoundException.php, CampaignReviewCaseAlreadyDecidedException.php,
  SameAdminCannotDecideException.php, CampaignNotApprovedException.php (nouveau)
app/Modules/Campaigns/Http/Controllers/
  Admin/CampaignReviewsController.php                         (nouveau)
  Advertiser/CampaignsController.php                          (modifié — submit/resubmit)
app/Modules/Campaigns/Http/routes/api.php                     (modifié)
app/Modules/AdvertiserWallet/Application/Contracts/AdvertiserWalletReservationContract.php (modifié)
app/Modules/AdvertiserWallet/Application/Services/AdvertiserWalletReservationService.php (modifié)
app/Modules/Identity/Console/SeedFounderCommand.php            (modifié — capacités)
config/campaigns.php                                           (modifié — review_require_distinct_decider)
resources/js/Components/AdminCampaignReviewsPanel.vue          (nouveau)
resources/js/Components/CampaignsPanel.vue                     (modifié — correction/resoumission)
resources/js/Pages/Identity/AdminShell.vue                     (modifié)
tests/Feature/Campaigns/CampaignReviewTest.php                 (nouveau, 10 tests)
docs/chantiers/P007-CHANTIER.md, P007-RAPPORT.md               (réécrits)
```

## 7. Migrations, API, événements, permissions

- **Migrations** : 3 nouvelles (extension du CHECK constraint + 2 tables).
- **API** : voir §2.6.
- **Événements** : `campaign_review_events` (append-only, docs/18 : "action sensible → événement
  d'audit append-only → consultation").
- **Permissions** : `admin.campaign-reviews.view`, `admin.campaign-reviews.decide`,
  `admin.campaigns.suspend` (nouvelles).

## 8. Limites restantes

- Aucune modification créant une nouvelle version après approbation (`docs/13` §58) — non
  spécifié avec assez de détail pour être codé sans inventer de règle ; une campagne approuvée
  n'est plus éditable dans ce chantier.
- Aucun widget de dashboard générique — compteur simplifié dans le panneau de revue lui-même.
- Aucune automatisation antifraude (`docs/16`) — décisions exclusivement humaines.
- Approbation ne déclenche aucune programmation/Matching/Feed réels (P008/P009, non construits).

## 9. État Git

`php artisan test` : 152/152. `pint --test` : vert. Frontend : format/lint/types/build verts.
Migration round-trip vérifié. Prêt pour commit, push et PR.

## 10. Chantier suivant recommandé

P008 — SmartProfile, consentements et Matching minimal.
