# P008 — RAPPORT DE VALIDATION

**Chantier :** SmartProfile, consentements et Matching  
**Branche :** `codex/p008-smart-profile-matching`  
**Pull request :** `#13`  
**Base :** `main@e82d8f2521fd8c6f196d14fc9d8d57255737efae`  
**Statut technique :** `ready_for_review`  
**Statut de la PR :** brouillon — aucune fusion ni aucun déploiement autorisé  
**Date :** 4 août 2026

## 1. Résumé exécutif

P008 livre le cœur intelligent et protégé de la distribution publicitaire Wasplex :

```text
profil volontaire
→ consentements actifs
→ segment autorisé
→ estimation protégée
→ Matching explicable
→ décision eligible / ineligible / withheld
```

Le chantier ne diffuse encore aucune publicité, ne mesure aucune attention et ne crée aucune valeur financière. Il prépare uniquement un contrat d’éligibilité minimal pour P009.

Le périmètre technique A à E est implémenté :

- P008-A — finalités et consentements versionnés ;
- P008-B — SmartProfile volontaire ;
- P008-C — segments et estimation protégée ;
- P008-D — Matching, fréquence, fatigue et explication ;
- P008-E — administration minimale, audit agrégé et configuration versionnée.

## 2. Fonctionnalités livrées

### 2.1. SmartProfile volontaire

- questions facultatives liées à des taxonomies stables ;
- réponses structurées et non textuelles ;
- conservation append-only des corrections ;
- provenance, fraîcheur et suppression logique ;
- séparation possession, usage, intérêt, projet et territoire approximatif ;
- refus technique des taxonomies sensibles ou interdites ;
- écran utilisateur mobile-first.

### 2.2. Consentements

- finalités versionnées ;
- accord, refus et retrait explicites ;
- preuve de la version présentée ;
- historique immuable ;
- retrait immédiatement opposable aux nouveaux matchings ;
- consentement distinct pour la localisation approximative.

### 2.3. Segments et estimation protégée

- segment lié à la version active d’une campagne ;
- règles limitées aux taxonomies publiées, actives, non sensibles et autorisées ;
- aucune règle libre exécutable ;
- estimation agrégée ;
- seuil minimal administrable ;
- arrondi administrable ;
- fourchette protégée ;
- résultat `withheld` sous le seuil ;
- aucune liste de membres ni compte exact transmis à l’annonceur.

### 2.4. Véritable Matching

Ordre appliqué :

```text
statut P007
→ période
→ consentements
→ classe économique P004
→ disponibilité des taxonomies
→ faits volontaires
→ seuil de confidentialité
→ fréquence
→ fatigue
→ décision
```

Le moteur produit :

- `eligible` lorsque tous les critères sont satisfaits ;
- `ineligible` lorsqu’une condition métier dure échoue ;
- `withheld` lorsqu’une barrière de confidentialité ou de sécurité interdit de conclure publiquement.

Chaque décision conserve :

- la version de campagne ;
- la version des règles ;
- la version de la configuration P008 ;
- une bande de score ;
- des jetons d’explication ;
- des codes d’exclusion ;
- l’état de fréquence ;
- un audit pseudonymisé.

### 2.5. Interfaces

#### Utilisateur

- `Mon Espace > Profil intelligent` ;
- centre de consentements ;
- correction et suppression des réponses ;
- page « Pourquoi cette publicité ? ».

#### Annonceur

- page `Ciblage protégé` par campagne ;
- sélection des seuls critères autorisés ;
- estimation agrégée ;
- seuil et arrondi explicités ;
- aucune identité ou liste nominative.

#### Administration

- page `Administration > Matching et confidentialité` ;
- publication atomique et versionnée des contrôles ;
- seuil minimal de segment ;
- pas d’arrondi ;
- fenêtre et limite de fréquence ;
- seuil de fatigue ;
- suspension et réactivation des finalités ;
- suspension et réactivation des taxonomies autorisées ;
- audit agrégé des décisions et estimations ;
- journal des actions d’administration sans identité affichée.

## 3. Administration et gouvernance

Les routes d’administration imposent cumulativement :

- authentification ;
- session d’identité active ;
- espace `administration` ;
- MFA récente ;
- capacités explicites de lecture, d’audit, de gestion ou de publication.

Une publication de contrôles crée une nouvelle version effective et ferme la période de la version précédente. Une republication strictement identique est idempotente.

La suspension d’une taxonomie déjà utilisée par un segment ne laisse pas subsister une ancienne décision éligible : l’état de la taxonomie participe à l’empreinte du Matching et la nouvelle décision devient `withheld` avec le code `targeting_taxonomy_unavailable`.

## 4. Confidentialité et sécurité

### 4.1. Données interdites

P008 n’utilise pas les données issues de :

- Santé ;
- Alertes ;
- Fonds ;
- KYC ;
- dette ;
- vulnérabilité ;
- religion ;
- politique ;
- orientation sexuelle ;
- grossesse supposée ;
- historique judiciaire ;
- données de mineurs non autorisées.

### 4.2. Données non exposées à l’annonceur

- nom ;
- téléphone ;
- adresse électronique ;
- identifiant de compte ;
- réponses individuelles ;
- SmartProfile complet ;
- empreinte pseudonymisée ;
- liste des membres d’un segment ;
- compte brut d’une estimation.

### 4.3. Audit administratif

L’audit présenté dans l’interface retourne seulement :

- nombres de décisions par statut ;
- nombres par bande de score ;
- principaux codes de décision ;
- nombres d’estimations disponibles ou masquées ;
- actions administratives et transitions d’état.

Il ne retourne aucun identifiant de compte, aucune empreinte de sujet et aucun profil publicitaire.

## 5. Cas de référence validé

```text
Utilisateur
- classe Gold
- commune approximative Cocody
- réseau principal Orange
- intérêt Internet mobile
- consentements nécessaires actifs

Campagne
- annonceur Orange
- territoire Cocody
- classes Gold et Platine
- critères Orange + Internet mobile + Cocody
- statut P007 approved

Décision
- eligible
- bande high
- explication compréhensible
- aucune identité transmise
- aucune opération financière créée
```

Variantes validées :

- retrait du consentement → `ineligible` ;
- campagne suspendue → `ineligible` ;
- segment trop petit → `withheld` ;
- fréquence atteinte → `ineligible` ;
- fatigue au seuil → `withheld` ;
- taxonomie suspendue → `withheld` ;
- rejeu du même état → même Matching, sans duplication ;
- configuration identique republiée → même version, sans duplication.

## 6. Validation automatisée

Dernier jalon complet avant le rapport :

```text
Commit métier et sécurité : 3e240920d5359f2b2c619fee3126d2c081e8c3fa
Workflow GitHub Actions : 30941068506

Pint                         succès
Larastan niveau 8            succès
Tests SQLite                 succès
Tests PostgreSQL 17          succès
Migrations PostgreSQL        succès
Rollback PostgreSQL          succès
Prettier                     succès
ESLint                       succès
TypeScript / Vue             succès
Build Vite                   succès
```

Le passage PostgreSQL exécute 83 tests et valide notamment les quatre migrations P008 et leur rollback avec les migrations dépendantes du Ledger et de l’identité.

## 7. Contrat de sortie vers P009

P008 prépare uniquement :

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

P009 devra relire et revalider l’état minimal nécessaire avant toute livraison réelle. Une décision P008 ne constitue ni une impression, ni une consommation de quota, ni une preuve d’attention.

## 8. Frontières maintenues

P008 ne contient pas :

- Feed ou affichage réel — P009 ;
- consommation définitive de quota — P009 ;
- heartbeat, preuve d’attention ou qualification — P010 ;
- réservation ou capture de valeur — P010 ;
- crédit du Wallet utilisateur — P011 ;
- partage économique 50/50 — P011 ;
- reporting publicitaire complet — P012 ;
- moteur général d’intelligence artificielle ;
- score social.

## 9. Procédure de déploiement proposée

Aucune commande de cette section ne doit être exécutée sans autorisation explicite du fondateur.

Ordre recommandé :

1. sauvegarder la base PostgreSQL ;
2. relever le commit actuellement déployé ;
3. déployer le commit P008 accepté ;
4. exécuter les migrations avec `php8.4 artisan migrate --force` ;
5. exécuter `php8.4 artisan advertising:bootstrap` ;
6. reconstruire le frontend ;
7. redémarrer les workers ;
8. vider les caches applicatifs ;
9. vérifier les routes utilisateur, annonceur et administration ;
10. effectuer la recette visuelle et fonctionnelle décrite au § 11.

La première version de configuration en base peut être publiée depuis la console d’administration. Tant qu’aucune version n’est publiée, le moteur utilise les valeurs sûres de l’environnement.

## 10. Procédure de rollback

### 10.1. Rollback applicatif recommandé

En production, le rollback recommandé est non destructif :

1. arrêter les nouveaux déploiements et workers concernés ;
2. redéployer le commit applicatif antérieur à P008 ;
3. vider les caches ;
4. redémarrer les workers de l’ancienne version ;
5. conserver les tables P008 et l’historique des consentements ;
6. vérifier que les routes P008 ne sont plus exposées par l’ancienne version.

Cette méthode évite d’effacer des consentements, décisions et preuves d’audit.

### 10.2. Rollback de base destructif

Le rollback des quatre migrations P008 est réservé à un environnement vide, de test ou à une décision explicite accompagnée d’une sauvegarde vérifiée.

Ordre inverse :

1. `2026_08_04_065000_create_advertising_administration_tables.php` ;
2. `2026_08_04_060000_create_advertising_matching_tables.php` ;
3. `2026_08_04_055000_align_subscription_accounts_for_matching.php` ;
4. `2026_08_04_050000_create_advertising_profile_consent_tables.php`.

Dans une release isolée où ces quatre migrations sont les dernières appliquées :

```bash
php8.4 artisan migrate:rollback --step=4 --force
```

Avant cette commande, confirmer impérativement :

- qu’aucune migration ultérieure n’est présente ;
- que la sauvegarde est restaurable ;
- que la suppression de l’historique P008 est juridiquement et opérationnellement autorisée.

### 10.3. Rollback d’une configuration

Une configuration publiée ne doit pas être modifiée en place. Pour revenir à d’anciennes valeurs, publier une nouvelle version reprenant les valeurs souhaitées. L’historique reste immuable et la nouvelle version devient la seule version effective.

## 11. Recette visuelle manuelle requise

Les captures ne sont pas fabriquées par la CI. Elles doivent être obtenues sur une instance de recette authentifiée après déploiement autorisé.

Captures requises :

1. espace utilisateur — Profil intelligent, questions et caractère facultatif ;
2. espace utilisateur — centre de consentements avec accord et retrait ;
3. Studio annonceur — page Ciblage protégé ;
4. Studio annonceur — estimation disponible avec fourchette ;
5. Studio annonceur — estimation `withheld` sous le seuil ;
6. espace utilisateur — page « Pourquoi cette publicité ? » ;
7. administration — contrôles versionnés du Matching ;
8. administration — finalités et taxonomies ;
9. administration — audit agrégé sans identité ;
10. affichage mobile des trois espaces.

La revue doit également confirmer :

- l’absence d’identités dans les réponses réseau de l’annonceur et de l’administration agrégée ;
- le blocage sans MFA récente ;
- le blocage depuis un espace non administratif ;
- la création d’une nouvelle version après publication ;
- l’effet immédiat d’une suspension de taxonomie sur un nouveau Matching.

## 12. Conclusion

Le code de P008 est techniquement prêt pour revue fondatrice. Les protections de confidentialité, le Matching, les contrôles administratifs, les tests multi-base et le rollback sont documentés.

La PR reste volontairement en brouillon. Les étapes restantes avant acceptation sont :

1. recette visuelle authentifiée et collecte des captures ;
2. validation fonctionnelle du fondateur ;
3. autorisation explicite de passer la PR en revue ;
4. autorisation distincte de fusion ;
5. autorisation distincte de déploiement.
