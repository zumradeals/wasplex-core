# RAPPORT — P008 : SmartProfile, consentements et Matching minimal

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `2a8bc6d` (P007 fusionné)
**Chantier :** `docs/chantiers/P008-CHANTIER.md`
**Spécifications :** `docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md`,
`docs/09-compte-universel-et-mon-espace-intelligent-wasplex.md`,
`docs/17-donnees-permissions-consentements-techniques-wasplex.md`
**Statut :** ready_for_review

Ce rapport réutilise le raisonnement du plan pré-réinitialisation
(`docs/chantiers/P008-CHANTIER.md` de la branche archivée `codex/p008-smart-profile-matching`) pour
la chaîne de décision et les invariants de confidentialité, mais en réduit le périmètre au niveau
des chantiers déjà livrés (P004-P007). Une leçon explicite du chantier de refonte archivé
`P008-R-REFONTE-PROFIL-INTELLIGENT.md` a été retenue : **aucun score de complétude n'est affiché**
(un pourcentage arbitraire y avait été identifié comme une fausse promesse).

---

## 1. Objectif

```text
réponses volontaires (taxonomies, catégories distinctes)
→ consentements publicitaires actifs, versionnés et retirables
→ campagne P007 approuvée et non suspendue
→ décision d'éligibilité protégée (eligible / ineligible / withheld)
→ explication compréhensible, sans révéler l'identité à l'annonceur
```

Le fondateur a explicitement demandé une expérience « très jolie, intuitive, utilisable » y
compris — et surtout — pour la configuration admin du Matching, lui-même non technicien. Cette
exigence a guidé chaque écran : chips à cocher plutôt que formulaires, tables simples avec
libellés en langage clair plutôt qu'éditeurs JSON, boutons Activer/Suspendre/Publier explicites.

## 2. Réalisé

### 2.1. SmartProfile volontaire (module `SmartProfile`)

- `profile_taxonomies` : catalogue plat administrable (code, catégorie immuable, libellé,
  fraîcheur), 6 catégories strictement séparées (`possession`, `usage`, `interest`, `project`,
  `situation`, `territory`) — une contrainte CHECK PostgreSQL rend techniquement impossible
  d'injecter une catégorie Santé/Alertes/Fonds/KYC (testé).
- `profile_answers` : append-only, un fait déclaratif booléen par (compte, taxonomie), retrait =
  `withdrawn_at` posé sans suppression — historique préservé (testé).
- Catalogue de départ : 14 taxonomies couvrant délibérément plusieurs secteurs (télécom,
  mobilité, mode, éducation, entrepreneuriat, géographie) — pas un seul secteur dominant, leçon
  explicite de `P008-R-REFONTE-PROFIL-INTELLIGENT.md` §1.
- Écran utilisateur : chips à cocher groupées par catégorie, compteur « X informations actives
  sur Y proposées » — **aucun pourcentage de complétude**.

### 2.2. Consentements (module `SmartProfile`, conventions `docs/17`)

- `consent_purposes` + `consent_purpose_versions` (draft → publish, même pattern que
  `AdvertisingPriceVersion` P006) ; `user_consents` (état courant) + `consent_events`
  (append-only) — noms de tables et routes alignés sur `docs/17` §12-19 pour permettre une
  extraction future vers un module transversal sans renommage.
- 3 finalités de départ : `advertising_personalization`, `smart_profile_usage`,
  `approximate_location_targeting` (textes en langage clair, ajustables par l'administration).
- Décision explicite (accord/retrait), preuve de version présentée, historique immuable, retrait
  immédiatement opposable (testé).
- Publier une nouvelle version avec « exige une nouvelle décision » fait basculer les accords
  existants en `superseded` — traduit en `NOT_DECIDED` (un doute, jamais un refus) par le contrat
  de consentement (testé).

### 2.3. Matching minimal (module `Matching`)

Chaîne de décision réellement appliquée (`docs/chantiers/P008-CHANTIER.md` §8) :

```text
campagne approuvée et non suspendue
→ période si renseignée (scheduled_start/end, jamais alimentés par l'assistant P006 à ce jour)
→ territoire du compte ∈ territoire ciblé
→ classe économique du compte ∈ classes ciblées
→ consentement advertising_personalization
   actif  → eligible
   refusé/retiré/expiré → ineligible (consent_denied)
   jamais décidé → withheld (consent_not_decided — un doute, pas un refus)
```

- `matching_decisions` : une ligne par (campagne, compte), recalculée (upsert) à chaque
  évaluation — idempotence vérifiée par test (3 évaluations consécutives → 1 ligne).
- `matching_configurations` : seuils de fréquence/fatigue administrables dès ce chantier, **non
  appliqués** — aucun compteur de livraison réelle n'existe avant le Feed (P009), documenté
  explicitement plutôt que simulé.
- Le contrat de sortie (`MatchingContract`) ne transmet jamais l'identité du compte évalué —
  vérifié par test sur la réponse `/api/me/eligible-campaigns`.

### 2.4. Explication utilisateur

« Campagnes qui vous correspondent » dans Mon Espace : liste les campagnes approuvées pour
lesquelles le compte est `eligible`, avec une explication en langage clair
(`EligibleCampaignsForAccountService` + `MatchingContract::explain()`) — remplace un Feed réel
(P009, non construit) par une démonstration honnête de l'explicabilité.

### 2.5. Administration

- `AdminSmartProfilePanel.vue` : table des taxonomies (catégorie en langage clair, statut,
  Activer/Suspendre) + table des finalités de consentement (créer une version, publier).
- `AdminMatchingPanel.vue` : réglages de fréquence/fatigue (Créer/Publier) + audit agrégé
  (compteurs par décision, aucune identité).
- Capacités : `admin.smartprofile.taxonomies.manage`, `admin.smartprofile.consents.manage`,
  `admin.matching.configuration.manage`, `admin.matching.audit.view`.

## 3. Décisions explicites (voir aussi `docs/chantiers/P008-CHANTIER.md` §3)

1. Aucun ciblage de campagne par taxonomie dans ce chantier — `AudienceConfigurationValidator`
   (P006) reste limité à `territory` + `economic_classes`. Le SmartProfile est collecté et
   consentement-protégé dès maintenant, mais ne devient un axe de ciblage réel qu'à un chantier
   ultérieur explicitement validé.
2. Fréquence/fatigue configurables mais non appliquées (aucun compteur réel avant P009).
3. Vérification de période au mieux : `scheduled_start`/`scheduled_end` existent dans le schéma
   P006 mais ne sont jamais alimentés par l'assistant — le Matching les vérifie s'ils sont
   présents, sans effet réel tant qu'aucun écran ne les alimente.
4. Seul le consentement `advertising_personalization` a un effet réel sur la décision ;
   `smart_profile_usage` et `approximate_location_targeting` sont gérables dès ce chantier mais
   n'influencent pas encore le Matching (aucun axe de ciblage ne les consomme).
5. Pas de score de complétude (leçon de `P008-R-REFONTE-PROFIL-INTELLIGENT.md` §1).
6. Taxonomies indépendantes, sans groupe à choix unique.
7. Le registre de consentements suit les conventions `docs/17` mais vit dans le module
   SmartProfile (seul consommateur actuel), pas comme le module transversal complet.
8. `user_consents` ne capture ni IP ni empreinte d'appareil (non spécifié comme requis).
9. `matching_decisions` recalculé par upsert, pas un historique complet — pas une écriture
   financière, la discipline append-only obligatoire reste réservée au Grand Livre.

## 4. Contrats internes (nouveaux ou étendus)

- `EconomicClassCatalogContract::classForAccount()` (Subscriptions, étendu).
- `AccountCountryLookupContract::countryForAccount()` (Identity, étendu).
- `Campaigns\Application\Contracts\ApprovedCampaignAudienceContract` (nouveau) — campagnes
  `approved` uniquement.
- `SmartProfile\Application\Contracts\AdvertisingConsentContract` (nouveau) — `stateFor()` :
  `active` / `refused` / `not_decided`.
- `Matching\Application\Contracts\MatchingContract` (nouveau, exposé pour P009) —
  `checkEligibility()` / `explain()`.

## 5. API et capacités

Utilisateur (self-service, aucune capacité) : `GET/POST/DELETE /api/me/smart-profile[...]`,
`GET/POST /api/me/consents[...]`, `GET /api/me/eligible-campaigns`.

Administration (MFA récente + capacité dédiée) :
`GET/POST/PATCH /api/admin/smartprofile/taxonomies[...]`,
`GET/POST/PATCH /api/admin/smartprofile/consent-purposes[...]`,
`GET/POST/PATCH /api/admin/matching/configuration[...]`, `GET /api/admin/matching/audit`.

## 6. Tests exécutés

- `php artisan test` (Pest 4) — **167 tests, 1516 assertions, aucune régression** (152 avant ce
  chantier + 15 nouveaux : 6 SmartProfile/consentement, 9 Matching).
- Couverture explicite des scénarios obligatoires (§10 du chantier) : retrait de consentement →
  inéligibilité immédiate ; consentement jamais décidé → `withheld` (pas `ineligible`) ;
  catégories Santé/Alertes/Fonds/KYC impossibles à injecter (contrainte CHECK, 6 valeurs
  testées) ; anonymat annonceur (aucun `account_id` dans la réponse du contrat) ; correction
  d'une réponse conserve l'historique ; isolation pays et classe (territoire et classe hors
  cible → `ineligible`) ; campagne suspendue toujours exclue ; idempotence du Matching (3
  évaluations → 1 ligne) ; capacités admin requises pour chaque domaine ; cas de référence
  Gold/Côte d'Ivoire positif avec explication en langage clair ; supersession d'un consentement
  après republication.
- **Bug réel trouvé et corrigé pendant les tests** : `ConsentEvent` n'avait pas la relation
  `purpose()` utilisée par `ConsentService::history()` — `GET /api/me/consents/history` levait une
  `RelationNotFoundException` (500). Corrigé en ajoutant la relation `BelongsTo` manquante.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- `migrate:rollback --step=8` → `migrate` — aller-retour propre sur les 8 nouvelles migrations.
- Parcours navigateur (Playwright/Chromium) : campagne GamaDeals ciblant Gold/Côte d'Ivoire
  approuvée avec un segment réel de 30 abonnés Gold → candidat Gold/CI déclare 2 informations de
  profil (réseau Orange, intérêt Internet mobile) → accorde le consentement personnalisation →
  « Campagnes qui vous correspondent » affiche GamaDeals avec 3 raisons en langage clair →
  administration : catalogue de taxonomies et finalités de consentement, réglages Matching et
  audit reflétant la décision réelle (1 éligible).

## 7. Captures

- Mon Espace, Profil intelligent : chips par catégorie, 2 informations actives sur 14
  (`p008-user-smartprofile.png`).
- Mon Espace, centre de consentements : Personnalisation accordée, deux autres finalités « pas
  encore décidé » (`p008-user-consents.png`).
- Mon Espace, « Campagnes qui vous correspondent » : GamaDeals éligible avec explication
  dépliée (`p008-user-eligible-campaigns.png`).
- Administration, Profil intelligent : table des taxonomies et catégories en langage clair
  (`p008-admin-smartprofile.png`).
- Administration, Matching : réglages en brouillon (`p008-admin-matching-before.png`) et audit
  après décision (1 éligible) (`p008-admin-matching-audit.png`).

## 8. Fichiers modifiés/ajoutés

```text
app/Modules/SmartProfile/                                       (nouveau module)
  Database/Migrations/2026_08_06_130000-130005_*.php             (6 migrations)
  Infrastructure/Models/{ProfileTaxonomy,ProfileAnswer,ConsentPurpose,
    ConsentPurposeVersion,UserConsent,ConsentEvent}.php
  Application/Contracts/AdvertisingConsentContract.php
  Application/Services/{ProfileTaxonomyService,ProfileAnswerService,
    ConsentPurposeService,ConsentService}.php (+ exceptions)
  Http/Controllers/{User,Admin}/*.php, Http/routes/api.php
  Infrastructure/Providers/SmartProfileServiceProvider.php
  Console/SeedCatalogCommand.php
app/Modules/Matching/                                            (nouveau module)
  Database/Migrations/2026_08_06_130100-130101_*.php              (2 migrations)
  Infrastructure/Models/{MatchingConfiguration,MatchingDecision}.php
  Application/Contracts/MatchingContract.php
  Application/ValueObjects/MatchingDecisionResult.php
  Application/Services/{MatchingService,MatchingConfigurationService,
    EligibleCampaignsForAccountService,MatchingAuditService}.php (+ exceptions)
  Http/Controllers/{User,Admin}/*.php, Http/routes/api.php
  Infrastructure/Providers/MatchingServiceProvider.php
  Console/SeedConfigurationCommand.php
app/Modules/Campaigns/Application/Contracts/ApprovedCampaignAudienceContract.php (nouveau)
app/Modules/Campaigns/Application/Services/ApprovedCampaignAudienceService.php  (nouveau)
app/Modules/Campaigns/Application/ValueObjects/ApprovedCampaignAudience.php     (nouveau)
app/Modules/Campaigns/Infrastructure/Providers/CampaignsServiceProvider.php     (modifié)
app/Modules/Subscriptions/Application/Contracts/EconomicClassCatalogContract.php (modifié)
app/Modules/Subscriptions/Application/Services/EconomicClassCatalogService.php  (modifié)
app/Modules/Identity/Application/Contracts/AccountCountryLookupContract.php     (modifié)
app/Modules/Identity/Application/Services/AccountCountryLookupService.php      (modifié)
app/Modules/Identity/Console/SeedFounderCommand.php                            (modifié)
bootstrap/providers.php                                                        (modifié)
resources/js/Components/{SmartProfilePanel,ConsentsPanel,EligibleCampaignsPanel,
  AdminSmartProfilePanel,AdminMatchingPanel}.vue                               (nouveau)
resources/js/Pages/Identity/{UserShell,AdminShell}.vue                        (modifié)
tests/Feature/SmartProfile/SmartProfileAndConsentTest.php                    (nouveau, 6 tests)
tests/Feature/Matching/MatchingEligibilityTest.php                           (nouveau, 9 tests)
docs/chantiers/P008-CHANTIER.md, P008-RAPPORT.md                              (réécrits)
```

## 9. Migrations, événements, permissions

- **Migrations** : 8 nouvelles (6 SmartProfile + 2 Matching).
- **Événements** : `consent_events` (append-only) ; `profile_answers` est lui-même son propre
  journal append-only.
- **Permissions** : `admin.smartprofile.taxonomies.manage`, `admin.smartprofile.consents.manage`,
  `admin.matching.configuration.manage`, `admin.matching.audit.view` (nouvelles, accordées
  explicitement au fondateur).

## 10. Limites restantes

- Le SmartProfile n'est pas encore un axe de ciblage de campagne — collecté et gouverné, mais
  sans consommateur publicitaire réel (décision §3.1).
- Fréquence/fatigue configurables, non appliquées — aucun compteur de livraison avant P009.
- Aucune vérification de période réellement active — champs jamais alimentés par le Studio.
- Aucune donnée déduite/inférée — `source` reste toujours `declared_by_user`.
- Aucun moteur d'IA — hors périmètre de ce chantier et de `docs/CLAUDE.md` §25.

## 11. État Git

`php artisan test` : 167/167. `pint --test` : vert. Frontend : format/lint/types/build verts.
Migration round-trip vérifié (8 migrations). Prêt pour commit, push et PR.

## 12. Chantier suivant recommandé

P009 — Super Moteur, Feed, attention et crédit automatique.
