# P008 — SMARTPROFILE, CONSENTEMENTS ET MATCHING

**Statut :** `in_progress`  
**Phase :** Distribution  
**Branche :** `codex/p008-smart-profile-matching`  
**Commit de base :** `e82d8f2521fd8c6f196d14fc9d8d57255737efae`  
**Dépendances :** P004 — Configurations économiques ; P007 — Revue administrative  
**Date d’ouverture :** 4 août 2026

## 1. Finalité

P008 construit le cœur intelligent de la première verticale Wasplex :

```text
réponses volontaires de l’utilisateur
→ faits publicitaires minimaux et versionnés
→ consentements actifs
→ règles de campagne approuvée
→ décision d’éligibilité protégée
→ explication « Pourquoi cette publicité ? »
```

Le système doit déterminer qui peut recevoir une campagne, à quel moment et pour quelle raison, sans révéler l’identité de la personne à l’annonceur et sans utiliser de données sensibles ou provenant de domaines interdits.

P008 ne diffuse encore aucune publicité et ne produit aucune valeur financière. Il prépare uniquement les candidats autorisés pour P009.

## 2. Sources canoniques

- `docs/MASTER-WASPLEX.md` ;
- `docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md` ;
- `docs/09-compte-universel-et-mon-espace-intelligent-wasplex.md` ;
- `docs/16-moderation-securite-antifraude-globale-wasplex.md` ;
- `docs/17-donnees-permissions-consentements-techniques-wasplex.md` ;
- `docs/IMPLEMENTATION-ROADMAP-WASPLEX.md`.

Ordre d’autorité : décision explicite du fondateur → note métier concernée → document maître → chantier → code.

## 3. Périmètre inclus

### 3.1. SmartProfile utilisateur

- centre « Mon profil intelligent » dans l’espace utilisateur ;
- questions courtes, facultatives et administrées ;
- réponses structurées issues de taxonomies ;
- catégories initiales : possessions, usages, intérêts, projets, besoins, activité professionnelle et territoires approximatifs ;
- provenance de chaque fait ;
- fraîcheur, expiration, confirmation, correction et contestation ;
- versionnement append-only des réponses et faits ;
- score de complétude informatif, sans score social et sans garantie de gain.

### 3.2. Consentements publicitaires

- registre de finalités versionnées ;
- finalités initiales :
  - `advertising_personalization` ;
  - `smart_profile_usage` ;
  - `approximate_location_targeting` ;
- décision explicite : accord, refus ou retrait ;
- preuve de la version du texte présentée ;
- historique immuable ;
- retrait immédiatement opposable aux nouveaux matchings ;
- propagation interne du changement ;
- écran utilisateur simple expliquant les conséquences de chaque choix.

### 3.3. Taxonomies et règles autorisées

- taxonomies publicitaires administrées et versionnées ;
- questions liées à des clés techniques stables ;
- distinction stricte entre possession, usage, intérêt, intention d’achat, résidence, travail et zone d’intérêt ;
- critères activables, suspendables et classés par finalité ;
- liste technique de sources et domaines interdits.

### 3.4. Segments et estimation protégée

- lecture des règles d’audience déjà attachées aux campagnes P006 ;
- normalisation vers les taxonomies P008 ;
- estimation agrégée ;
- seuil minimal de segment ;
- arrondis et masquage anti-réidentification ;
- aucune exportation de membres ;
- aucune liste nominative ;
- audit des estimations inhabituelles.

### 3.5. Matching minimal

- seules les campagnes P007 approuvées peuvent être évaluées ;
- contrôle de période, territoire, classe économique, consentements et profil ;
- fréquence et fatigue minimales préparatoires à la livraison ;
- décision explicite : `eligible`, `ineligible` ou `withheld` ;
- score de pertinence explicable ;
- raisons positives et causes d’exclusion structurées ;
- idempotence d’une même évaluation ;
- aucun accès de l’annonceur à l’utilisateur évalué.

### 3.6. Explication utilisateur

- endpoint et vue « Pourquoi cette publicité ? » ;
- explication fondée uniquement sur des éléments compréhensibles : classe, zone approximative, intérêt ou usage déclaré, projet volontaire et consentement ;
- aucune révélation de règles antifraude sensibles ;
- possibilité de corriger la réponse concernée ou de retirer le consentement.

### 3.7. Administration minimale

- consultation des finalités, taxonomies et questions ;
- activation/suspension contrôlée ;
- seuil minimal de segment ;
- limites de fréquence et fatigue ;
- audit sans données nominatives inutiles ;
- capacités explicites et MFA récente pour les actions critiques.

## 4. Périmètre explicitement exclu

P008 ne doit pas contenir :

- le Feed ou la livraison réelle d’une publicité — P009 ;
- la consommation définitive du quota sur affichage — P009 ;
- la preuve d’attention, les heartbeats et la qualification — P010 ;
- la réservation ou capture de valeur publicitaire — P010 ;
- le crédit du Wallet utilisateur ou le partage 50/50 — P011 ;
- les animations de récompense ;
- le reporting complet annonceur ou fondateur — P012 ;
- un moteur général d’intelligence artificielle ;
- un profil public nominatif ;
- un score social ;
- des décisions sensibles hors publicité ;
- l’utilisation commerciale des données Santé, Alertes, Fonds, KYC, dette, vulnérabilité, religion, politique, orientation sexuelle, grossesse supposée, historique judiciaire ou données de mineurs non autorisées.

## 5. Invariants de sécurité et de confidentialité

1. L’annonceur achète une capacité de ciblage, jamais une identité.
2. Une campagne rejetée, suspendue ou non approuvée ne peut jamais produire de match éligible.
3. Un consentement absent, refusé, retiré, expiré ou remplacé bloque la finalité concernée.
4. Le retrait bloque les nouveaux usages sans réécrire l’historique.
5. Aucun module ne lit directement les tables Santé, Alertes, Fonds ou KYC.
6. Les projections inter-domaines sont minimales, contractuelles et auditées.
7. Les textes libres ne deviennent jamais des règles exécutables de ciblage.
8. Une donnée déduite reste identifiable comme déduite, explicable et contestable.
9. Un segment trop petit est masqué, arrondi, élargi ou refusé.
10. Les logs et audits ne contiennent aucun secret ni profil publicitaire complet.
11. Le matching n’effectue aucun débit, réservation, capture ou crédit financier.
12. Les décisions sont reproductibles à partir des versions de règles enregistrées.

## 6. Modèle de données proposé

### SmartProfile

- `advertising_profile_questions` ;
- `advertising_profile_question_versions` ;
- `advertising_profile_answers` ;
- `advertising_profile_answer_versions` ;
- `advertising_profile_facts` ;
- `advertising_profile_fact_versions`.

### Consentements et finalités

- `data_purposes` ;
- `data_purpose_versions` ;
- `advertising_consents` ;
- `advertising_consent_events`.

### Taxonomies et segments

- `advertising_taxonomies` ;
- `advertising_taxonomy_versions` ;
- `advertising_segments` ;
- `advertising_segment_rules` ;
- `advertising_segment_estimates`.

### Matching

- `advertising_matches` ;
- `advertising_match_reasons` ;
- `advertising_frequency_counters` ;
- `advertising_fatigue_scores` ;
- `advertising_match_audits`.

Le schéma définitif pourra regrouper certaines tables si cela réduit la complexité sans perdre le versionnement, l’audit ou la séparation des responsabilités.

## 7. Contrats applicatifs

### Projections entrantes

- `AdvertisingProfileProjection` ;
- `ActiveAdvertisingConsentProjection` ;
- `EconomicClassEligibilityProjection` ;
- `ApprovedCampaignTargetingProjection`.

### Services

- `AdvertisingProfileService` ;
- `AdvertisingConsentService` ;
- `AdvertisingTaxonomyService` ;
- `SegmentEstimationService` ;
- `CampaignEligibilityService` ;
- `AdvertisingExplanationService`.

### Sortie minimale vers P009

```text
eligible
campaign_id
match_id
score_band
explanation_tokens
frequency_state
rule_version
```

Le contrat ne transmet jamais l’intégralité du SmartProfile.

## 8. API et écrans

### Espace utilisateur

- `GET /mon-espace/profil-intelligent` ;
- réponses, correction et suppression des données facultatives ;
- centre de consentements publicitaires ;
- historique compréhensible ;
- explication « Pourquoi cette publicité ? ».

### Studio annonceur

- taxonomies autorisées en lecture seule ;
- estimation agrégée de l’audience ;
- aucune identité et aucun membre du segment.

### Administration

- configuration des finalités, taxonomies, questions, seuils, fréquence et fatigue ;
- activation/suspension versionnée ;
- journal d’audit agrégé.

## 9. Événements métier

- `AdvertisingProfileUpdated` ;
- `AdvertisingProfileFactExpired` ;
- `AdvertisingConsentGranted` ;
- `AdvertisingConsentDenied` ;
- `AdvertisingConsentWithdrawn` ;
- `AdvertisingPurposeVersionPublished` ;
- `AdvertisingTaxonomyPublished` ;
- `SegmentEstimated` ;
- `CampaignMatched` ;
- `CampaignMatchWithheld`.

Les événements ne contiennent que les identifiants techniques et données minimales nécessaires.

## 10. Capacités

### Utilisateur

- `advertising.profile.view.self` ;
- `advertising.profile.manage.self` ;
- `advertising.consent.view.self` ;
- `advertising.consent.manage.self` ;
- `advertising.explanation.view.self`.

### Annonceur

- `advertiser.targeting.taxonomy.view` ;
- `advertiser.segment.estimate`.

### Administration

- `advertising.configuration.view` ;
- `advertising.configuration.manage` ;
- `advertising.configuration.publish` ;
- `advertising.match.audit.view`.

Aucune autorité ne découle du seul nom d’un rôle.

## 11. Décision de Matching minimale

Ordre obligatoire :

```text
campagne approuvée et non suspendue
→ campagne dans sa période
→ territoire et classe compatibles
→ consentements requis actifs
→ faits volontaires correspondants et suffisamment frais
→ seuils de fréquence et fatigue respectés
→ segment non interdit et non réidentifiant
→ score explicable
→ décision
```

Les critères durs échoués rendent la décision `ineligible`. Un doute de sécurité ou de confidentialité produit `withheld`, sans révéler la règle précise au client.

## 12. Jeux de démonstration

### Utilisateur de référence

```text
Classe : Gold
Pays : Côte d’Ivoire
Ville : Abidjan
Commune approximative : Cocody
Réseau principal déclaré : Orange
Intérêt déclaré : offres Internet mobile
Consentements : personnalisation + profil volontaire + localisation approximative
```

### Campagne de référence

```text
Annonceur : Orange
Territoire : Côte d’Ivoire / Abidjan / Cocody
Classes : Gold et Platine
Critères : utilise Orange + intérêt Internet mobile
Statut P007 : approuvée
```

### Résultat attendu

- l’utilisateur est éligible ;
- l’explication mentionne sa zone approximative, sa classe et ses réponses volontaires ;
- l’annonceur ne reçoit aucun identifiant utilisateur ;
- le retrait de `smart_profile_usage` rend immédiatement les prochains matchings inéligibles.

## 13. Tests obligatoires

- profil vide et profil volontaire ;
- réponse corrigée avec conservation de l’historique ;
- provenance et fraîcheur ;
- consentement accordé, refusé, retiré, expiré et remplacé ;
- propagation du retrait ;
- campagne non approuvée ou suspendue exclue ;
- Orange / Cocody / Gold positif ;
- isolation pays et classe ;
- distinction possession / intérêt / usage / projet ;
- segment trop petit masqué ;
- absence de liste nominative ;
- requêtes répétées et anti-réidentification ;
- fréquence et fatigue ;
- explication compréhensible ;
- données Santé, Alertes, Fonds et KYC impossibles à injecter ;
- capacités et isolation des espaces ;
- idempotence du matching ;
- migrations PostgreSQL et rollback ;
- frontend mobile et desktop ;
- analyse statique, lint, TypeScript et build.

## 14. Critères d’acceptation

P008 est acceptable lorsque :

1. l’utilisateur peut compléter et corriger un profil publicitaire volontaire ;
2. chaque question explique sa finalité, son caractère facultatif et son influence ;
3. les consentements sont explicites, versionnés et retirables ;
4. le retrait bloque immédiatement les nouveaux matchings ;
5. une campagne P007 approuvée peut être évaluée ;
6. le cas Gold / Cocody / Orange produit une éligibilité explicable ;
7. une campagne non approuvée ou suspendue est toujours exclue ;
8. Santé, Alertes, Fonds, KYC et autres catégories interdites ne peuvent jamais participer ;
9. l’annonceur ne reçoit que des estimations agrégées ;
10. le contrat de sortie vers P009 ne contient aucun profil complet ;
11. aucune opération financière n’est créée ;
12. les tests SQLite et PostgreSQL, la sécurité et le frontend sont verts ;
13. le rapport, les captures et le rollback sont fournis.

## 15. Plan de réalisation

### P008-A — Registre des finalités et consentements

- finalités versionnées ;
- décisions et historique ;
- centre utilisateur ;
- propagation du retrait.

### P008-B — SmartProfile volontaire

- taxonomies et questions ;
- réponses, faits, provenance et fraîcheur ;
- écran Mon Espace intelligent.

### P008-C — Segments et estimation protégée

- normalisation des audiences P006 ;
- estimation agrégée ;
- seuil et anti-réidentification.

### P008-D — Matching et explication

- éligibilité ;
- fréquence/fatigue minimale ;
- décision, audit et explication ;
- contrat P009.

### P008-E — Administration, preuves et stabilisation

- configuration minimale ;
- démonstration Orange/Cocody/Gold ;
- captures ;
- rapport et rollback.

## 16. Rollback

- désactiver les routes P008 ;
- arrêter les consommateurs d’événements P008 ;
- retirer les capacités P008 ;
- revenir aux migrations dans l’ordre inverse ;
- aucune écriture Ledger n’est concernée ;
- aucune campagne P007 n’est modifiée ou supprimée ;
- les données de consentement déjà recueillies ne doivent pas être effacées sans décision explicite.

## 17. Interdictions de chantier

- aucun merge sans autorisation explicite du fondateur ;
- aucun déploiement sans autorisation explicite ;
- aucune modification du Wallet ou du Grand Livre ;
- aucun contournement des statuts P007 ;
- aucun début anticipé de P009, P010 ou P011 ;
- aucune donnée sensible ajoutée « pour plus tard » ;
- aucune règle cachée dans le frontend.
