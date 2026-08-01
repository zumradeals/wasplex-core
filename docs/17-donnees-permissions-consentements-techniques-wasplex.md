# WASPLEX — DONNÉES, PERMISSIONS & CONSENTEMENTS TECHNIQUES

**Fichier cible recommandé :** `docs/16-donnees-permissions/00-donnees-permissions-consentements-techniques-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module transversal de classification des données, contrôle d’accès, consentements, journalisation, export, suppression et conservation configurable  
**Interfaces officielles :**
- espace utilisateur : mobile-first strict ;
- espaces annonceur, partenaire, professionnel et institutionnel : desktop complet + mobile opérationnel ;
- administration : desktop-first.  
**Dépendances :** Compte universel, Mon Espace intelligent, Studio Annonceur, Matching, Feed, Wallet, Fonds, Alertes, Santé, Carte, Live, Espaces professionnels, Notifications, Administration centrale, Modération & Antifraude  
**Principe central :** chaque donnée doit avoir un domaine, une finalité, une règle d’accès, une durée, une provenance et un historique technique clair  
**Directive produit :** ce module ne doit pas devenir une constitution, un moteur juridique ni une couche bloquante ; il traduit les décisions produit en structures, permissions, configurations, contrats et tests

---

# 1. Objet

Ce document définit :

- le catalogue technique des données ;
- les domaines de données ;
- les finalités ;
- les permissions ;
- les consentements ;
- les préférences ;
- les accès sensibles ;
- les séparations entre modules ;
- les projections de données ;
- les journaux d’accès ;
- les exports ;
- les corrections ;
- les suppressions ;
- les durées de conservation ;
- les archives ;
- les demandes utilisateur ;
- les données professionnelles ;
- les données organisationnelles ;
- les API ;
- les modèles de données ;
- les événements ;
- les tests.

---

# 2. Vision produit

La chaîne cible est :

```text
une donnée est créée
→ son domaine est connu
→ sa provenance est enregistrée
→ sa finalité est définie
→ son accès est limité
→ son utilisation est auditée
→ sa correction ou suppression suit une règle
→ son expiration est gérée
```

Pour un consentement :

```text
une finalité est présentée
→ l’utilisateur accepte ou refuse
→ la décision est versionnée
→ les modules concernés reçoivent le changement
→ les nouveaux usages sont adaptés
```

---

# 3. Ce que le module doit faire

Le module doit :

- classifier les données ;
- identifier leur propriétaire fonctionnel ;
- contrôler les accès ;
- enregistrer les consentements ;
- propager les retraits ;
- exposer des projections minimales ;
- journaliser les accès sensibles ;
- permettre la correction ;
- permettre l’export ;
- supprimer les données facultatives ;
- archiver selon configuration ;
- limiter les usages inter-modules ;
- fournir des contrats techniques stables.

---

# 4. Ce que le module ne doit pas faire

Il ne doit pas :

- interpréter automatiquement des lois ;
- bloquer une fonctionnalité au motif d’un texte abstrait ;
- imposer une constitution interne ;
- empêcher le fondateur de configurer une règle métier ;
- mélanger les données de tous les modules ;
- donner un accès global à l’administration ;
- vendre ou transmettre des identités aux annonceurs ;
- utiliser Santé, Alertes, Fonds ou KYC pour le ciblage publicitaire ;
- supprimer une écriture financière ;
- effacer un audit critique ;
- modifier silencieusement l’historique.

---

# 5. Architecture générale

```text
Data Catalog
→ Data Domains
→ Purpose Registry
→ Consent Registry
→ Permission Engine
→ Access Gateway
→ Projection Services
→ Access Audit
→ Retention Jobs
→ Export & Deletion Services
```

---

# 6. Domaines de données

Domaines initiaux :

```text
account
identity
kyc
advertising
smart_profile
wallet
fonds
alerts
health
card
live
partner
professional
institutional
security
support
audit
```

Chaque domaine possède :

- propriétaire fonctionnel ;
- schémas ;
- permissions ;
- finalités ;
- durées ;
- événements ;
- API.

---

# 7. Propriétaire fonctionnel

Le propriétaire fonctionnel d’une donnée est le module responsable de :

- sa définition ;
- sa qualité ;
- sa correction ;
- son accès ;
- sa conservation ;
- son export ;
- sa suppression éventuelle.

Exemple :

```text
Wallet
→ propriétaire des opérations et projections financières

Santé
→ propriétaire des capsules et accès médicaux

Advertising
→ propriétaire des campagnes et segments

Compte
→ propriétaire de l’identité de connexion
```

---

# 8. Catégories techniques

Catégories recommandées :

```text
public
internal
personal
sensitive
highly_sensitive
financial
medical
security_critical
audit_only
```

Ces catégories guident les contrôles techniques.

Elles ne remplacent pas les permissions détaillées.

---

# 9. Provenance

Chaque donnée importante possède une provenance :

```text
declared_by_user
confirmed_by_user
created_by_system
derived_from_allowed_activity
verified_by_partner
verified_by_institution
imported_from_provider
corrected
contested
expired
```

---

# 10. Qualité et statut

États possibles :

```text
active
unverified
verified
contested
deprecated
expired
deleted
archived
```

Une donnée contestée ne doit pas continuer à être présentée comme certaine sans indication.

---

# 11. Finalités

Chaque utilisation importante doit correspondre à une finalité technique.

Exemples :

```text
account_authentication
wallet_operation
advertising_personalization
smart_profile_matching
approximate_location_targeting
partner_offer_eligibility
fonds_program_execution
alerts_case_processing
health_emergency_capsule
security_monitoring
support_resolution
```

---

# 12. Registre des finalités

Une finalité possède :

```text
code
name
description
domain
required_or_optional
consent_required
default_status
version
active_from
active_to
```

---

# 13. Consentements techniques

Un consentement représente une décision utilisateur pour une finalité facultative.

Exemples :

```text
advertising_personalization
smart_profile_usage
approximate_location
marketing_notifications
partner_offers
health_emergency_capsule
```

---

# 14. Consentements obligatoires et facultatifs

## Facultatif

L’utilisateur peut accepter ou refuser sans perdre les fonctions fondamentales non concernées.

## Nécessaire au service choisi

Exemple :

```text
utilisation de la capsule Santé d’urgence
→ consentement ou configuration spécifique du service
```

Le système doit expliquer la conséquence technique du refus.

---

# 15. Preuve du consentement

Chaque décision conserve :

```text
account_id
purpose_code
text_version_id
status
channel
device
ip
granted_at
withdrawn_at
expires_at
```

---

# 16. États d’un consentement

```text
granted
denied
withdrawn
expired
superseded
pending
```

---

# 17. Versionnement

Le texte ou écran associé à une finalité est versionné.

Une nouvelle version peut :

- conserver l’ancien consentement si compatible ;
- demander une nouvelle décision ;
- suspendre l’usage facultatif ;
- notifier l’utilisateur.

La règle est configurable par finalité.

---

# 18. Retrait

Lorsqu’un consentement est retiré :

- le statut est enregistré ;
- les nouveaux usages sont bloqués ;
- les segments futurs sont recalculés ;
- les modules concernés sont notifiés ;
- les traitements déjà finalisés ne sont pas réécrits automatiquement ;
- les données facultatives peuvent être supprimées selon configuration.

---

# 19. Propagation

Événement recommandé :

```text
ConsentWithdrawn
```

Consommateurs possibles :

- Matching ;
- Feed ;
- Notifications ;
- Partenaires ;
- Mon Espace ;
- Analytics ;
- Live.

---

# 20. Préférences et consentements

Les préférences ne sont pas toujours des consentements.

Exemples de préférences :

- son ;
- sous-titres ;
- mode sombre ;
- fréquence de notification ;
- catégories masquées.

Exemples de consentements :

- personnalisation publicitaire ;
- utilisation du profil intelligent ;
- localisation approximative ;
- communication marketing.

---

# 21. Permissions techniques

Une permission vérifie :

```text
acteur
espace
organisation
capacité
ressource
action
périmètre
contexte
niveau d’authentification
```

---

# 22. Capacités

Exemples :

```text
account.profile.read.self
account.profile.update.self
advertising.profile.use
wallet.transactions.read.self
fonds.case.read.assigned
alerts.case.read.assigned
health.patient.authorized.read
health.emergency.read
admin.audit.view
```

---

# 23. Périmètres

Une capacité peut être limitée par :

- compte ;
- organisation ;
- établissement ;
- territoire ;
- dossier ;
- programme ;
- campagne ;
- point de vente ;
- période ;
- montant ;
- pays.

---

# 24. Accès par contexte

Une même capacité peut exiger un contexte.

Exemple :

```text
health.emergency.read
+ professionnel vérifié
+ urgence déclarée
+ MFA récente
+ capsule uniquement
```

---

# 25. Accès du fondateur

Le fondateur dispose d’une supervision globale, mais :

- les accès sensibles restent tracés ;
- les accès Santé exigent un contexte explicite ;
- le Grand Livre n’est pas modifiable ;
- les audits ne sont pas effaçables ;
- les interventions exceptionnelles sont enregistrées.

---

# 26. Séparation des domaines

Les domaines ne doivent pas lire directement les tables internes des autres domaines.

Ils utilisent :

- API internes ;
- événements ;
- projections ;
- vues autorisées ;
- contrats ;
- services de lecture dédiés.

---

# 27. Exemple Advertising

Le Matching peut recevoir :

```text
age_band
approximate_zone
declared_interest
declared_project
economic_class
consent_status
```

Il ne reçoit pas :

- dossier Santé ;
- dossier Alertes ;
- difficultés Fonds ;
- KYC complet ;
- solde Wallet ;
- dette ;
- adresse précise inutile.

---

# 28. Exemple Feed

Le Feed reçoit :

```text
eligible = true
campaign_id
reward_amount
explanation_tokens
```

Il ne reçoit pas l’intégralité du profil intelligent.

---

# 29. Exemple partenaire

Le partenaire peut recevoir :

```text
card_valid = true
offer_eligible = true
public_display_name
operation_reference
```

Il ne reçoit pas :

- solde Wallet ;
- historique ;
- profil publicitaire ;
- KYC ;
- données Santé.

---

# 30. Exemple institutionnel

L’institution reçoit une projection de dossier :

- faits nécessaires ;
- pièces autorisées ;
- statut ;
- territoire ;
- contact relayé ;
- historique institutionnel.

Elle ne reçoit pas automatiquement toutes les données du compte.

---

# 31. Exemple Santé

Le professionnel reçoit :

- capsule autorisée ;
- durée ;
- finalité ;
- identité utile ;
- représentant si nécessaire.

Il ne reçoit pas automatiquement :

- historique publicitaire ;
- campagnes vues ;
- Fonds ;
- Wallet ;
- Alertes non liées.

---

# 32. Projections de données

Une projection est une vue minimale préparée pour une finalité.

Elle possède :

- code ;
- domaine source ;
- domaine destinataire ;
- champs ;
- finalité ;
- version ;
- durée ;
- cache ;
- audit.

---

# 33. Contrats de projection

Exemples :

```text
AdvertisingEligibilityProjection
PartnerCardEligibilityProjection
InstitutionAlertCaseProjection
HealthEmergencyCapsuleProjection
WalletPublicReceiptProjection
```

---

# 34. Accès sensibles

Accès sensibles :

- Santé ;
- Alertes ;
- KYC ;
- sécurité ;
- preuves financières ;
- audit ;
- récupération de compte ;
- données d’enfant ;
- exports massifs.

Ils exigent :

- capacité ;
- contexte ;
- MFA ;
- justification ;
- audit ;
- durée limitée.

---

# 35. Journal des accès

Chaque accès sensible conserve :

```text
actor_account_id
actor_space_id
organization_id
capability
purpose_code
resource_type
resource_id
action
reason
session_id
device_id
trace_id
created_at
```

---

# 36. Accès non sensible

Les lectures ordinaires peuvent être :

- agrégées ;
- journalisées de manière technique ;
- échantillonnées ;
- conservées moins longtemps.

Les accès sensibles restent détaillés.

---

# 37. Consultation par l’utilisateur

L’utilisateur peut voir :

- consentements ;
- accès Santé ;
- accès de sécurité importants ;
- appareils ;
- exports ;
- modifications de profil ;
- espaces professionnels liés ;
- sessions.

---

# 38. Centre de données personnel

Dans Mon Espace :

```text
Mes informations
Mes intérêts
Mes projets
Mes consentements
Mes accès sensibles
Mes appareils
Exporter mes données
Supprimer des données facultatives
Clôturer mon compte
```

---

# 39. Correction

L’utilisateur peut corriger :

- profil personnel ;
- centres d’intérêt ;
- projets ;
- possessions ;
- zones ;
- préférences ;
- informations contestables.

Les données vérifiées peuvent nécessiter une nouvelle procédure.

---

# 40. Contestation

Une donnée dérivée peut être :

- contestée ;
- masquée ;
- recalculée ;
- supprimée ;
- confirmée.

La contestation produit un historique.

---

# 41. Suppression des données facultatives

Peuvent être supprimés :

- intérêts ;
- projets ;
- besoins ;
- possessions ;
- préférences ;
- bio ;
- photo ;
- audiences enregistrées personnelles.

La suppression doit se propager aux projections futures.

---

# 42. Données non supprimables directement

Ne doivent pas être supprimées comme une simple préférence :

- écritures du Grand Livre ;
- transactions ;
- audit critique ;
- incidents actifs ;
- preuves nécessaires à une opération ouverte ;
- sanctions actives ;
- rapprochements ;
- dossiers institutionnels en cours.

Elles peuvent être archivées ou limitées selon configuration.

---

# 43. Export des données

L’utilisateur peut demander un export de :

- profil ;
- consentements ;
- réponses du profil intelligent ;
- opérations Wallet ;
- historique Fonds communicable ;
- Alertes communicables ;
- accès Santé communicables ;
- Carte ;
- Live ;
- support ;
- appareils.

---

# 44. États d’un export

```text
requested
preparing
ready
downloaded
expired
failed
cancelled
```

---

# 45. Sécurité de l’export

Un export doit être :

- authentifié ;
- protégé ;
- temporaire ;
- journalisé ;
- expirant ;
- chiffré si nécessaire ;
- séparé par domaine.

---

# 46. Export professionnel

Une organisation peut exporter uniquement les données :

- qu’elle possède ;
- auxquelles elle a accès ;
- correspondant à son périmètre ;
- avec une capacité explicite.

---

# 47. Suppression du compte

Flux :

```text
demande
→ authentification renforcée
→ vérification des opérations ouvertes
→ suspension des nouveaux usages
→ délai
→ clôture
→ suppression ou archivage selon type de donnée
```

---

# 48. Clôture et services actifs

Avant clôture, vérifier :

- solde ;
- retrait ;
- dépôt ;
- litige ;
- abonnement ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Live ;
- organisation ;
- support.

---

# 49. Conservation configurable

Chaque type de donnée possède :

```text
retention_policy_code
active_duration
archive_duration
deletion_action
scope
version
effective_from
```

---

# 50. Actions de fin de conservation

```text
delete
anonymize
pseudonymize
archive
retain
review
```

---

# 51. Expiration automatique

Des tâches planifiées peuvent :

- expirer un consentement ;
- archiver un dossier ;
- supprimer un jeton ;
- fermer un canal temporaire ;
- purger un cache ;
- anonymiser une donnée dérivée ;
- supprimer une pièce temporaire.

---

# 52. Archives

Les archives doivent être :

- séparées des données actives ;
- accessibles par capacité ;
- non utilisées pour le ciblage ;
- protégées ;
- restaurables selon procédure ;
- auditables.

---

# 53. Anonymisation

Peut être utilisée pour :

- statistiques ;
- reporting ;
- analyse produit ;
- recherche technique ;
- tests.

Elle doit réduire le risque de rattachement à une personne.

---

# 54. Pseudonymisation

Peut être utilisée lorsqu’un lien contrôlé reste nécessaire.

Exemple :

```text
user_id interne
→ identifiant pseudonyme analytique
```

Les clés de correspondance sont protégées.

---

# 55. Données de test

Interdictions :

- copier librement la production ;
- utiliser des dossiers Santé réels ;
- utiliser des identités réelles sans protection ;
- exposer des clés ;
- réutiliser des exports.

Préférer :

- données synthétiques ;
- anonymisation ;
- échantillons contrôlés ;
- environnements séparés.

---

# 56. Environnements

Séparation :

```text
development
testing
staging
production
```

Chaque environnement possède :

- données ;
- secrets ;
- accès ;
- intégrations ;
- audits ;
- sauvegardes.

---

# 57. Données analytiques

Les analytics doivent être séparés des tables métier.

Ils peuvent recevoir :

- événements pseudonymisés ;
- agrégats ;
- dimensions autorisées ;
- mesures.

Ils ne doivent pas devenir une copie complète de tous les domaines.

---

# 58. Données annonceur

L’annonceur reçoit :

- portée agrégée ;
- fréquence ;
- événements ;
- classes agrégées ;
- territoires agrégés ;
- coûts ;
- résultats.

Il ne reçoit pas :

- identités ;
- contacts ;
- profils individuels ;
- historiques personnels ;
- données sensibles.

---

# 59. Données de sécurité

Les données de risque peuvent inclure :

- appareil ;
- session ;
- IP ;
- signaux ;
- score ;
- dossier ;
- sanction.

Elles restent réservées aux équipes autorisées.

---

# 60. Données de mineurs

Règles techniques renforcées :

- profil public minimal ;
- ciblage limité ;
- consentement ou représentant selon produit ;
- accès restreint ;
- export contrôlé ;
- suppression simplifiée ;
- sécurité renforcée.

---

# 61. Administration du catalogue

Le back-office permet de :

- créer une catégorie ;
- définir un domaine ;
- définir une finalité ;
- associer une permission ;
- définir une durée ;
- publier une version ;
- suspendre une finalité ;
- voir les accès ;
- lancer une purge ;
- simuler un impact.

---

# 62. Administration des consentements

Fonctions :

- créer une finalité ;
- créer une version de texte ;
- définir les canaux ;
- définir les conséquences ;
- programmer une activation ;
- voir les taux agrégés ;
- tester ;
- publier ;
- retirer une version.

---

# 63. Administration des permissions

Fonctions :

- voir les capacités ;
- créer une capacité ;
- associer à un rôle ;
- limiter par périmètre ;
- attribuer ;
- révoquer ;
- expirer ;
- auditer.

---

# 64. Administration de la conservation

Fonctions :

- créer une politique ;
- simuler les éléments concernés ;
- programmer ;
- suspendre ;
- exécuter ;
- produire un rapport ;
- restaurer si l’action est réversible.

---

# 65. Simulation

Avant publication d’une règle de conservation ou d’accès :

- compter les données concernées ;
- identifier les modules ;
- détecter les opérations ouvertes ;
- afficher les impacts ;
- bloquer uniquement les incohérences techniques réelles.

---

# 66. Capacités

## Utilisateur

```text
data.profile.read.self
data.profile.update.self
data.consents.manage.self
data.access_logs.view.self
data.export.request.self
data.optional.delete.self
```

## Professionnel

```text
data.projection.read.authorized
data.sensitive.read.authorized
data.attachments.upload.authorized
data.audit.self.view
```

## Administration

```text
data.catalog.manage
data.purposes.manage
data.consents.manage
data.permissions.manage
data.retention.manage
data.access_audit.view
data.exports.manage
```

---

# 67. Modèle de données

Entités recommandées :

```text
data_domains
data_categories
data_catalog_entries
data_field_definitions
data_sources
data_purposes
data_purpose_versions

consent_definitions
consent_text_versions
user_consents
consent_events

permission_definitions
permission_grants
permission_scopes
permission_conditions

data_projections
data_projection_versions
data_access_requests
data_access_grants
data_access_events

retention_policies
retention_policy_versions
retention_jobs
retention_job_items

data_export_requests
data_export_files
data_correction_requests
data_deletion_requests
data_archives
data_audit_events
```

---

# 68. Champs — Data Catalog Entry

```text
id
domain_code
entity_name
field_name
category_code
purpose_codes
source_code
retention_policy_code
owner_module
status
version
```

---

# 69. Champs — Consent

```text
id
account_id
purpose_code
text_version_id
status
channel
granted_at
withdrawn_at
expires_at
created_at
```

---

# 70. Champs — Permission Grant

```text
id
account_id
space_id
organization_id
capability_code
scope_type
scope_id
starts_at
expires_at
status
granted_by
```

---

# 71. Champs — Access Event

```text
id
actor_account_id
actor_space_id
organization_id
purpose_code
capability_code
resource_type
resource_id
action
reason
session_id
trace_id
created_at
```

---

# 72. Champs — Retention Policy

```text
id
code
domain_code
active_duration_days
archive_duration_days
end_action
scope
version
status
effective_from
```

---

# 73. API utilisateur

```text
GET    /api/me/data-summary
GET    /api/me/consents
POST   /api/me/consents/{purpose}/grant
POST   /api/me/consents/{purpose}/withdraw
GET    /api/me/consents/history

GET    /api/me/data-accesses
POST   /api/me/data-exports
GET    /api/me/data-exports/{id}
POST   /api/me/data-corrections
POST   /api/me/data-deletions
```

---

# 74. API projections internes

```text
GET    /internal/data-projections/{code}
POST   /internal/data-projections/{code}/resolve
POST   /internal/data-access/check
POST   /internal/data-access/log
```

Ces API sont internes et protégées.

---

# 75. API administration

```text
GET    /api/admin/data/catalog
POST   /api/admin/data/catalog
PATCH  /api/admin/data/catalog/{id}

GET    /api/admin/data/purposes
POST   /api/admin/data/purposes
POST   /api/admin/data/purposes/{id}/publish

GET    /api/admin/data/permissions
POST   /api/admin/data/permissions
POST   /api/admin/data/permissions/grants
DELETE /api/admin/data/permissions/grants/{id}

GET    /api/admin/data/retention-policies
POST   /api/admin/data/retention-policies
POST   /api/admin/data/retention-policies/{id}/simulate
POST   /api/admin/data/retention-policies/{id}/publish

GET    /api/admin/data/access-events
GET    /api/admin/data/export-requests
GET    /api/admin/data/deletion-requests
```

---

# 76. Événements métier

```text
DataCatalogEntryCreated
DataPurposePublished
ConsentGranted
ConsentDenied
ConsentWithdrawn
ConsentExpired

PermissionGranted
PermissionRevoked
PermissionExpired

DataProjectionResolved
SensitiveDataAccessed
DataAccessDenied

DataExportRequested
DataExportReady
DataCorrectionRequested
DataCorrectionCompleted
DataDeletionRequested
DataDeletionCompleted

RetentionPolicyPublished
RetentionJobStarted
RetentionJobCompleted
DataArchived
DataAnonymized
DataDeleted
```

---

# 77. Intégration avec le Compte universel

Le Compte universel fournit :

- acteur ;
- espace ;
- organisation ;
- session ;
- appareil ;
- authentification ;
- MFA ;
- pays ;
- langue.

Le module Données décide ensuite :

- finalité ;
- capacité ;
- projection ;
- journal ;
- durée.

---

# 78. Intégration avec Matching

Le Matching doit utiliser uniquement :

- données autorisées ;
- consentements actifs ;
- projections ;
- critères explicables ;
- segments protégés.

Il ne doit pas lire directement Santé, Alertes, Fonds ou KYC.

---

# 79. Intégration avec Wallet

Le Wallet expose des projections :

- reçu ;
- solde au titulaire ;
- statut d’opération ;
- éligibilité.

Les écritures et transactions restent immuables.

---

# 80. Intégration avec Santé

Santé possède :

- schémas séparés ;
- permissions séparées ;
- audit détaillé ;
- accès temporaires ;
- projections minimales.

---

# 81. Intégration avec Alertes

Alertes possède :

- dossier source ;
- projections publiques ;
- projections institutionnelles ;
- contacts relayés ;
- restrictions ;
- historiques.

---

# 82. Intégration avec Studio Annonceur

Le Studio reçoit :

- tailles estimées ;
- segments agrégés ;
- résultats ;
- coûts ;
- explications.

Il ne reçoit pas les individus.

---

# 83. Sécurité

- authentification ;
- capacités ;
- périmètres ;
- MFA ;
- chiffrement ;
- secrets ;
- journalisation ;
- fichiers signés ;
- rate limiting ;
- anti-replay ;
- séparation des environnements ;
- aucun accès direct inter-domaine.

---

# 84. Performance

- cache des permissions ;
- cache des finalités ;
- projections préparées ;
- index ;
- pagination ;
- jobs asynchrones ;
- exports différés ;
- purges planifiées ;
- archives séparées ;
- journaux partitionnés.

---

# 85. Résilience

En cas d’indisponibilité :

- accès sensibles : refus ou attente selon configuration ;
- accès ordinaires déjà autorisés : cache court ;
- consentements : dernière version connue avec expiration ;
- exports : mise en attente ;
- purges : reprise idempotente ;
- audit : file locale sécurisée puis synchronisation.

---

# 86. Accessibilité

Le centre de consentement doit :

- utiliser un langage clair ;
- expliquer la finalité ;
- afficher les conséquences ;
- être navigable au clavier ;
- fonctionner au lecteur d’écran ;
- proposer des actions distinctes accepter/refuser ;
- éviter les cases précochées par défaut pour les usages facultatifs.

---

# 87. Tests du catalogue

- domaine ;
- catégorie ;
- propriétaire ;
- finalité ;
- provenance ;
- version ;
- publication ;
- suspension.

---

# 88. Tests des consentements

- accord ;
- refus ;
- retrait ;
- expiration ;
- version ;
- propagation ;
- nouvelle campagne exclue ;
- historique ;
- idempotence.

---

# 89. Tests des permissions

- capacité absente ;
- capacité présente ;
- mauvais espace ;
- mauvais territoire ;
- périmètre expiré ;
- MFA manquante ;
- fondateur ;
- audit ;
- accès Santé séparé.

---

# 90. Tests des projections

- Advertising ;
- Feed ;
- Partenaire ;
- Institution ;
- Santé ;
- Wallet ;
- aucun champ interdit ;
- version ;
- cache ;
- audit.

---

# 91. Tests d’export

- demande ;
- préparation ;
- sécurité ;
- expiration ;
- téléchargement ;
- export professionnel limité ;
- domaine séparé ;
- gros volume.

---

# 92. Tests de suppression

- donnée facultative ;
- propagation ;
- cache purgé ;
- segment recalculé ;
- écriture financière refusée ;
- audit conservé ;
- opération ouverte bloquant la suppression.

---

# 93. Tests de conservation

- expiration ;
- archivage ;
- anonymisation ;
- suppression ;
- simulation ;
- reprise ;
- idempotence ;
- rapport.

---

# 94. Tests inter-domaines

- Matching sans Santé ;
- Feed sans profil complet ;
- partenaire sans Wallet complet ;
- institution sans base universelle ;
- Santé sans données publicitaires ;
- administration sans accès automatique global.

---

# 95. Tests responsive

## Utilisateur

- consentements mobile ;
- historique ;
- export ;
- suppression ;
- accès sensibles ;
- interface mobile sur desktop.

## Professionnels

- projections desktop ;
- accès mobile ;
- audit tablette ;
- permissions.

## Administration

- catalogue desktop ;
- matrice de permissions ;
- simulation ;
- journaux ;
- exports.

---

# 96. Captures obligatoires

1. centre de consentement mobile ;
2. détail d’une finalité ;
3. historique ;
4. retrait ;
5. résumé des données ;
6. accès sensibles ;
7. demande d’export ;
8. suppression facultative ;
9. matrice de permissions ;
10. catalogue des données ;
11. projection ;
12. journal d’accès ;
13. politique de conservation ;
14. simulation ;
15. archive ;
16. dashboard administration.

---

# 97. Critères d’acceptation

Le module est accepté lorsque :

1. les domaines sont définis ;
2. les données ont un propriétaire fonctionnel ;
3. les finalités sont versionnées ;
4. les consentements sont enregistrés ;
5. le retrait est propagé ;
6. les permissions utilisent capacités et périmètres ;
7. les domaines communiquent par projections ;
8. Advertising ne lit pas Santé, Alertes, Fonds ou KYC ;
9. les accès sensibles sont audités ;
10. l’utilisateur voit ses consentements ;
11. l’utilisateur peut corriger ses données ;
12. l’utilisateur peut supprimer les données facultatives ;
13. l’utilisateur peut demander un export ;
14. le Grand Livre n’est pas supprimable ;
15. les politiques de conservation sont configurables ;
16. les purges sont simulables ;
17. les archives sont séparées ;
18. les données de test sont protégées ;
19. les interfaces respectent la doctrine responsive ;
20. les tests critiques passent.

---

# 98. Ordre d’implémentation

## Phase 1 — Catalogue et domaines

- domaines ;
- catégories ;
- propriétaires ;
- finalités ;
- provenance.

## Phase 2 — Permissions

- capacités ;
- périmètres ;
- conditions ;
- moteur de contrôle ;
- audit.

## Phase 3 — Consentements

- définitions ;
- versions ;
- accord ;
- retrait ;
- propagation.

## Phase 4 — Projections

- contrats ;
- API internes ;
- minimisation ;
- cache ;
- audit.

## Phase 5 — Centre utilisateur

- résumé ;
- consentements ;
- accès ;
- corrections ;
- suppressions.

## Phase 6 — Export

- demande ;
- jobs ;
- fichiers ;
- expiration ;
- sécurité.

## Phase 7 — Conservation

- politiques ;
- simulation ;
- archivage ;
- anonymisation ;
- suppression.

## Phase 8 — Intégrations

- Matching ;
- Feed ;
- Wallet ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Live ;
- Studio ;
- professionnels.

## Phase 9 — Administration

- catalogue ;
- permissions ;
- finalités ;
- conservation ;
- journaux.

## Phase 10 — Stabilisation

- sécurité ;
- performance ;
- résilience ;
- responsive ;
- tests ;
- captures.

---

# 99. Première verticale à livrer

```text
Utilisateur ouvre Mon Espace
→ consulte “Personnalisation publicitaire”
→ accepte
→ renseigne Orange et Cocody
→ projection Matching créée
→ campagne compatible proposée dans le Feed
→ utilisateur retire le consentement
→ événement propagé
→ projection invalidée
→ futures campagnes personnalisées exclues
→ historique conservé
```

Deuxième verticale :

```text
Professionnel Santé demande un accès d’urgence
→ capacité vérifiée
→ MFA
→ finalité enregistrée
→ capsule minimale projetée
→ accès journalisé
→ expiration
→ utilisateur voit l’accès dans Mon Espace
```

Troisième verticale :

```text
Utilisateur demande un export
→ export par domaines
→ fichier protégé
→ notification
→ téléchargement
→ expiration
→ audit
```

---

# 100. Directive pour Claude Code

1. lire Compte universel, Mon Espace, Matching, Wallet, Alertes, Santé, Administration et Sécurité ;
2. auditer le nouveau dépôt ;
3. créer un catalogue simple et codable ;
4. séparer les domaines ;
5. utiliser capacités, périmètres et contextes ;
6. construire les consentements versionnés ;
7. propager les retraits ;
8. créer des projections minimales ;
9. interdire les lectures directes inter-domaines ;
10. protéger Grand Livre et audits ;
11. rendre les durées configurables ;
12. fournir export, correction et suppression facultative ;
13. fournir migrations, API, tests et captures ;
14. ne pas introduire de constitution, doctrine ou moteur juridique bloquant.

---

# 101. Décision finale

Le module doit fonctionner ainsi :

```text
donnée
→ domaine
→ finalité
→ permission
→ projection minimale
→ usage
→ audit
→ expiration ou conservation configurée
```

> **Wasplex doit pouvoir utiliser les données nécessaires pour faire fonctionner ses services, tout en empêchant les accès inutiles, les mélanges entre domaines et les usages non autorisés — sans transformer cette protection technique en couche de blocage du développement.**
