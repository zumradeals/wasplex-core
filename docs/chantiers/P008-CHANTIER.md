# P008 — SmartProfile, consentements et Matching minimal

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `2a8bc6d` (P007 fusionné)
**Dépendances :** P004 (classes économiques), P006/P007 (campagnes approuvées)
**Spécifications :** `docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md`,
`docs/09-compte-universel-et-mon-espace-intelligent-wasplex.md` (Phases 4, 5, partie 7),
`docs/17-donnees-permissions-consentements-techniques-wasplex.md` (§12-19, registre des
finalités et consentements), `docs/MASTER-WASPLEX.md`.

Ce chantier réutilise le raisonnement du plan pré-réinitialisation
(`docs/chantiers/P008-CHANTIER.md` sur la branche archivée `codex/p008-smart-profile-matching`,
conservée dans l'historique Git) : chaîne de décision, invariants de confidentialité, contrat de
sortie vers P009. Il en réduit délibérément le périmètre pour rester au niveau de granularité des
chantiers déjà livrés (P004-P007) et retient une leçon explicite du chantier de refonte
`P008-R-REFONTE-PROFIL-INTELLIGENT.md` (également archivé) : **aucun score de complétude n'est
affiché** — ce chantier y avait identifié un faux objectif de 100 % comme une erreur de conception.

---

## 1. Objectif

```text
réponses volontaires de l'utilisateur (taxonomies, catégories distinctes)
→ consentements publicitaires actifs, versionnés et retirables
→ campagne P007 approuvée et non suspendue
→ décision d'éligibilité protégée (eligible / ineligible / withheld)
→ explication compréhensible, sans révéler l'identité à l'annonceur
```

P008 ne diffuse aucune publicité, ne consomme aucun quota et ne crée aucune valeur financière —
il prépare uniquement des candidats autorisés et explicables pour P009 (Feed, non construit).

## 2. Périmètre inclus

1. **SmartProfile volontaire** : catalogue de taxonomies administrées (possession / usage /
   intérêt / projet / situation / territoire approximatif), déclaration et retrait par
   l'utilisateur, historique append-only, provenance toujours `declared_by_user`.
2. **Consentements publicitaires** : registre de finalités versionnées, décision explicite
   (accord/refus/retrait), preuve de version présentée, historique immuable, retrait
   immédiatement opposable.
3. **Matching minimal** : évalue une campagne P007 approuvée et non suspendue pour un compte —
   territoire déclaré (P006/Identity), classe économique (P006/Subscriptions), consentement
   `advertising_personalization` actif — et produit `eligible` / `ineligible` / `withheld` avec
   des raisons explicables.
4. **Explication utilisateur** : liste « Campagnes qui vous correspondent » dans Mon Espace,
   avec explication en langage clair par campagne éligible.
5. **Administration minimale** : gestion des taxonomies, des finalités de consentement et de la
   configuration Matching — écrans simples, en langage clair, sans éditeur JSON ni jargon
   technique (exigence explicite du fondateur, non-technicien).

## 3. Périmètre explicitement exclu — décisions

1. **Aucun ciblage de campagne par taxonomie dans ce chantier.** `AudienceConfigurationValidator`
   (P006) reste limité à `territory` + `economic_classes`. Étendre le Studio Annonceur (assistant,
   estimation, devis) aux taxonomies SmartProfile suppose une refonte de l'UI et du moteur de
   devis déjà livrés et testés (P006/P007) ; ce n'est pas spécifié avec assez de précision dans
   `docs/13` pour être codé sans inventer une UX. Le SmartProfile est donc collecté, gouverné et
   consentement-protégé dès ce chantier, mais ne devient un axe de ciblage réel qu'à un chantier
   ultérieur explicitement validé par le fondateur.
2. **Aucune fréquence/fatigue appliquée.** `matching_configurations` expose des seuils
   administrables (fenêtre de fréquence, plafond, seuil de fatigue) pour que l'écran admin soit
   complet dès maintenant, mais aucun compteur réel n'existe avant le Feed (P009) — appliquer une
   règle non vérifiable serait une fausse protection (même discipline que
   `SubscriptionQuotaContract` en P004, resté sans appelant réel jusqu'à un chantier ultérieur).
3. **Vérification de période au mieux.** `campaigns.scheduled_start`/`scheduled_end` existent dans
   le schéma P006 mais ne sont jamais renseignés par l'assistant annonceur (champs réservés, non
   câblés) — le Matching les vérifie s'ils sont présents (gratuit, sans risque, direct depuis
   `docs/04`) mais, tant qu'aucun écran ne les alimente, une campagne approuvée et non suspendue
   reste active en continu jusqu'à sa prochaine décision de revue.
4. **Un seul consentement a un effet réel sur la décision : `advertising_personalization`.**
   `smart_profile_usage` et `approximate_location_targeting` sont enregistrables et retirables dès
   ce chantier (le centre de consentements est complet), mais n'influencent pas encore la décision
   — aucun axe de ciblage actuel ne consomme le SmartProfile ou une localisation approximative
   (décision §3.1). Documenté explicitement plutôt qu'une fausse application.
5. **Pas de score de complétude.** Leçon retenue de `P008-R-REFONTE-PROFIL-INTELLIGENT.md` §1 :
   l'écran affiche le nombre d'informations actives et les catégories explorées, jamais un
   pourcentage.
6. **Taxonomies indépendantes, sans groupe à choix unique.** Chaque taxonomie est un fait
   déclaratif booléen autonome (déclaré / retiré). Un utilisateur peut déclarer deux taxonomies
   normalement exclusives (ex. deux réseaux mobiles « principaux ») ; imposer l'exclusivité au sein
   d'une catégorie est une amélioration future non demandée ici.
7. **Le registre de consentements suit les conventions `docs/17`** (tables et routes) mais n'est
   pas construit comme le module transversal complet (Santé, Alertes, Fonds, Carte, Live) — il vit
   dans le module SmartProfile, seul consommateur actuel. Les noms de colonnes restent génériques
   (`purpose_code`, pas `advertising_purpose_code`) pour permettre une extraction future sans
   renommage.
8. **`user_consents` ne capture ni IP ni empreinte d'appareil** — seuls `channel`, la version de
   texte présentée et les horodatages sont conservés ; `docs/17` §15 les liste en preuve
   optionnelle, aucune décision produit n'a été prise sur leur rétention.
9. **Pas de table d'audit séparée pour le Matching.** `matching_decisions` conserve la dernière
   décision par (campagne, compte) avec ses raisons et la version de règles utilisée — recalculée
   (upsert) à chaque évaluation plutôt qu'accumulée en historique complet, puisqu'il ne s'agit pas
   d'une écriture financière (le Grand Livre reste la seule source append-only obligatoire).

## 4. Invariants de confidentialité (repris de `docs/04`/`docs/17`, non négociables)

1. Aucune lecture directe des tables Santé, Alertes, Fonds ou KYC — structurellement impossible
   ici : aucun contrat vers ces modules n'existe, et les taxonomies ne peuvent être créées que par
   un administrateur via un catalogue fermé.
2. Une campagne rejetée, suspendue ou non approuvée ne produit jamais de match éligible.
3. Un consentement absent, refusé, retiré ou expiré bloque la finalité concernée.
4. L'annonceur ne reçoit jamais l'identité d'un compte évalué — le contrat de sortie
   (`MatchingContract`) ne transmet que des identifiants techniques et des jetons d'explication.
5. Les catégories de taxonomies (possession/usage/intérêt/projet/situation/territoire) ne sont
   jamais fusionnées : chaque taxonomie porte sa catégorie de façon immuable.

## 5. Modèle de données

### Module `SmartProfile`

- `profile_taxonomies` (code unique, catégorie, libellé, statut draft/active/suspended,
  fraîcheur en jours nullable) — catalogue plat administrable, pas de versionnage JSON (les
  champs sont modifiables individuellement pendant `draft`, activables/suspendables ensuite —
  pattern le plus simple pour un administrateur non technicien).
- `profile_answers` (compte, taxonomie, `source` = `declared_by_user`, `declared_at`,
  `withdrawn_at` nullable) — append-only : une correction retire une ligne et en déclare une
  nouvelle, l'historique reste lisible.
- `consent_purposes` (code unique, statut draft/active/suspended) + `consent_purpose_versions`
  (texte, `requires_new_decision`, statut draft/published, `published_at`) — même pattern
  draft→publish que `AdvertisingPriceCatalog`/`Version` (P006).
- `user_consents` (compte, finalité, version de texte présentée, statut
  granted/denied/withdrawn/expired/superseded, `channel`, `granted_at`, `withdrawn_at`) — état
  courant, une ligne par (compte, finalité).
- `consent_events` (append-only, un événement par décision).

### Module `Matching`

- `matching_configurations` (statut draft/published, fenêtre de fréquence en heures, plafond de
  fréquence, seuil de fatigue, `published_at`) — une seule version publiée à la fois.
- `matching_decisions` (campagne, version de campagne, compte, décision
  eligible/ineligible/withheld, `reason_codes` JSON, `rule_version_id`, `decided_at`, unique sur
  campagne+compte).

## 6. Contrats internes

- `App\Modules\Subscriptions\...\EconomicClassCatalogContract` étendu :
  `classForAccount(string $accountId): ?string` (réutilise la table déjà possédée par
  Subscriptions, aucune nouvelle table).
- `App\Modules\Identity\...\AccountCountryLookupContract` étendu :
  `countryForAccount(string $accountId): ?string`.
- `App\Modules\Campaigns\Application\Contracts\ApprovedCampaignAudienceContract` (nouveau) :
  `find(string $campaignId): ?ApprovedCampaignAudience`, `listApproved(): ApprovedCampaignAudience[]`
  — campagnes au statut `approved` uniquement, avec leur `audience_configuration` normalisée.
- `App\Modules\SmartProfile\Application\Contracts\AdvertisingConsentContract` (nouveau) :
  `isActive(string $accountId, string $purposeCode): ConsentState` (`active`, `refused`,
  `not_decided`) — la seule façon dont Matching lit l'état de consentement.
- `App\Modules\Matching\Application\Contracts\MatchingContract` (nouveau, exposé pour P009) :
  `checkEligibility(string $campaignId, string $accountId): MatchingDecisionResult`,
  `explain(string $campaignId, string $accountId): array` (jetons en langage clair).

## 7. API et capacités

### Utilisateur (self-service, `auth` + session non révoquée, aucune capacité — même discipline
que `docs/03` §24 pour les abonnements)

```text
GET    /api/me/smart-profile
POST   /api/me/smart-profile/{taxonomy}
DELETE /api/me/smart-profile/{taxonomy}
GET    /api/me/consents
POST   /api/me/consents/{purpose}/grant
POST   /api/me/consents/{purpose}/withdraw
GET    /api/me/consents/history
GET    /api/me/eligible-campaigns
```

### Administration (MFA récente + capacité dédiée)

```text
GET/POST/PATCH  /api/admin/smartprofile/taxonomies[/{id}[/activate|suspend]]
                 admin.smartprofile.taxonomies.manage
GET/POST/PATCH  /api/admin/smartprofile/consent-purposes[/{id}[/publish]]
                 admin.smartprofile.consents.manage
GET/PATCH/POST publish /api/admin/matching/configuration
                 admin.matching.configuration.manage
GET             /api/admin/matching/audit (agrégats, aucune identité)
                 admin.matching.audit.view
```

## 8. Décision de Matching

```text
campagne approuvée et non suspendue
→ territoire du compte ∈ territoire ciblé
→ classe économique du compte ∈ classes ciblées
→ consentement advertising_personalization
   - actif  → poursuite
   - refusé/retiré/expiré → ineligible (raison : consent_denied)
   - jamais décidé → withheld (raison : consent_not_decided — un doute de confidentialité,
     pas un refus)
→ eligible
```

## 9. Événements

`ConsentGranted`, `ConsentWithdrawn`, `ProfileAnswerDeclared`, `ProfileAnswerWithdrawn` (journal
applicatif via `consent_events` / append-only de `profile_answers`) ; aucun événement Ledger —
aucune opération financière.

## 10. Tests obligatoires

Retrait de consentement → inéligibilité immédiate ; consentement jamais décidé → `withheld` (pas
`ineligible`) ; catégories Santé/Alertes/Fonds/KYC impossibles à injecter (aucune route ne les
accepte) ; anonymat annonceur (le contrat de sortie ne contient aucun identifiant de compte) ;
correction d'une réponse conserve l'historique ; isolation pays et classe (un compte hors
territoire/classe ciblé est `ineligible`) ; campagne non approuvée/suspendue toujours exclue ;
idempotence du Matching (rejouer la même évaluation ne duplique pas `matching_decisions`) ;
capacités admin requises + MFA récente ; cas de référence Gold/Côte d'Ivoire positif.

## 11. Critères de fin

Migrations + rollback propre, tests Pest verts, Pint vert, qualité frontend verte, captures
Playwright, rapport, `docs/ROADMAP-INDEX.md` mis à jour, PR créée en brouillon, CI verte, merge,
resynchronisation de branche.
