# WASPLEX — ADMINISTRATION CENTRALE & SUPERVISION DU FONDATEUR

**Fichier cible recommandé :** `docs/11-administration/00-administration-centrale-supervision-fondateur-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module transversal de pilotage, configuration, supervision, contrôle opérationnel et intervention exceptionnelle  
**Dépendances :** Compte universel, Abonnements, Publicité, Matching, Feed, Wallet & Grand Livre, Super moteur de valeur, Fonds, Alertes, Santé, Carte Wasplex, Partenaires, Live  
**Principe central :** le fondateur doit disposer d’une visibilité complète et d’un pouvoir de configuration réel sur Wasplex, sans permettre la suppression silencieuse des traces ni la modification directe de la vérité financière  
**Directive produit :** l’administration est un outil d’exécution et de supervision ; elle ne doit pas devenir une constitution, une couche doctrinale ou un mécanisme bloquant le développement

---

# 1. Objet

Ce document définit l’administration centrale Wasplex.

Elle doit permettre de :

- superviser l’ensemble de l’écosystème ;
- accéder aux tableaux de bord par module ;
- configurer les règles métier ;
- publier et suspendre des paramètres ;
- gérer les utilisateurs et organisations ;
- gérer les abonnements ;
- gérer les campagnes et budgets ;
- superviser le Wallet et le Grand Livre ;
- administrer le Fonds ;
- superviser Alertes et Santé ;
- gérer la Carte et les partenaires ;
- gérer les Lives ;
- administrer les rôles et capacités ;
- traiter les incidents ;
- effectuer des corrections traçables ;
- utiliser un droit d’intervention exceptionnel du fondateur ;
- auditer toutes les actions sensibles ;
- produire des rapports ;
- piloter le déploiement progressif par pays et fonctionnalités.

---

# 2. Vision produit

L’administration centrale doit répondre immédiatement à ces questions :

```text
Que se passe-t-il actuellement dans Wasplex ?
Quels modules fonctionnent ou présentent un incident ?
Quels flux financiers sont en cours ?
Quelles campagnes consomment leur budget ?
Quels utilisateurs ou organisations sont bloqués ?
Quelles décisions attendent une validation ?
Quelles règles sont actives ?
Qui a modifié quoi ?
Quelle intervention le fondateur doit-il pouvoir effectuer ?
```

L’expérience cible est :

```text
Connexion administrateur
→ tableau de bord global
→ module concerné
→ diagnostic
→ action autorisée
→ confirmation renforcée
→ événement métier
→ audit
→ résultat vérifiable
```

---

# 3. Ce que l’administration n’est pas

L’administration centrale ne doit pas :

- réimplémenter toute la logique métier de chaque module ;
- modifier directement les tables financières ;
- supprimer l’historique ;
- permettre des actions anonymes ;
- créer un rôle technique `admin = tout autorisé` sans capacités explicites ;
- bloquer le code par des textes abstraits ;
- imposer une règle non configurée ;
- remplacer les services métier ;
- mélanger les données Santé, Alertes, KYC et Advertising ;
- permettre à un agent de contourner silencieusement un contrôle critique.

L’administration appelle les contrats des modules.

Exemple :

```text
Admin approuve un dépôt
→ Wallet Deposit Service
→ événement DepositApproved
→ Grand Livre
→ Wallet crédité
→ audit
```

Et non :

```text
Admin
→ UPDATE wallet_balance
```

---

# 4. Architecture générale

```text
Administration centrale
├── Tableau de bord global
├── Centre de configuration
├── Utilisateurs et comptes
├── Organisations et espaces
├── Rôles et capacités
├── Abonnements
├── Publicité et Matching
├── Feed
├── Wallet et Grand Livre
├── Super moteur de valeur
├── Fonds
├── Alertes
├── Santé
├── Carte et Partenaires
├── Live
├── Modération
├── Support
├── Incidents
├── Audit
├── Reporting
└── Déploiements et fonctionnalités
```

---

# 5. Position du fondateur

Le fondateur est l’autorité opérationnelle principale du produit.

Il doit pouvoir :

- voir toutes les configurations ;
- accéder à tous les tableaux de bord ;
- créer et modifier les règles ;
- publier ou suspendre une configuration ;
- voir les finances globales ;
- superviser les équipes ;
- attribuer et retirer des capacités ;
- intervenir sur un incident ;
- déclencher une correction exceptionnelle ;
- suspendre un module ou une fonctionnalité ;
- annuler une publication ;
- restaurer une version précédente ;
- consulter tous les journaux d’audit ;
- ouvrir une session de diagnostic contrôlée ;
- appliquer une décision exceptionnelle lorsque le fonctionnement normal bloque injustement une opération.

Ce pouvoir doit être réel, mais traçable.

---

# 6. Droit d’intervention exceptionnel du fondateur

Nom recommandé :

```text
Founder Exceptional Override
```

ou en interface :

```text
Intervention exceptionnelle du fondateur
```

Ce mécanisme permet au fondateur de dépasser une validation opérationnelle normale lorsqu’une situation l’exige.

Exemples :

- débloquer une configuration incorrectement suspendue ;
- rétablir une capacité ;
- autoriser une correction financière ;
- annuler une campagne problématique ;
- suspendre immédiatement un module ;
- imposer le remboursement d’un utilisateur ;
- rouvrir une opération en litige ;
- corriger un mauvais rattachement d’organisation ;
- interrompre un Live dangereux ;
- protéger le système pendant un incident.

---

# 7. Limites de l’intervention exceptionnelle

Même le fondateur ne doit pas pouvoir :

- supprimer une écriture du Grand Livre ;
- modifier un solde sans transaction ;
- effacer un audit ;
- falsifier une date historique ;
- réutiliser une preuve financière déjà consommée ;
- accéder anonymement à une capsule Santé ;
- publier une action sensible sans identité de session ;
- rendre invisible son intervention.

L’intervention exceptionnelle produit :

- motif obligatoire ;
- référence ;
- avant/après ;
- module ;
- action ;
- horodatage ;
- session ;
- éventuelle pièce jointe ;
- événement d’audit ;
- notification interne ;
- rapport.

---

# 8. Modes d’intervention du fondateur

## 8.1. Intervention de configuration

Exemples :

- modifier un quota ;
- modifier un poids ;
- changer un frais ;
- suspendre une règle ;
- activer un pays.

## 8.2. Intervention opérationnelle

Exemples :

- approuver une opération ;
- suspendre un compte ;
- restaurer un accès ;
- clôturer un incident.

## 8.3. Intervention financière

Exemples :

- approuver une correction ;
- déclencher un remboursement ;
- libérer une réserve ;
- régulariser une erreur.

Elle passe obligatoirement par le Grand Livre.

## 8.4. Intervention d’urgence

Exemples :

- couper un Live ;
- suspendre une campagne ;
- désactiver un moyen de paiement ;
- bloquer les retraits ;
- activer un mode maintenance.

---

# 9. Niveaux d’administration

Niveaux recommandés :

```text
Founder
Executive Admin
Domain Admin
Operations Manager
Reviewer
Support Agent
Moderator
Auditor
Read Only
```

Les noms sont configurables.

Les droits réels sont définis par capacités.

---

# 10. Capacités

Exemples transversaux :

```text
admin.dashboard.view
admin.configuration.view
admin.configuration.manage
admin.feature_flags.manage
admin.users.view
admin.users.restrict
admin.organizations.manage
admin.capabilities.grant
admin.capabilities.revoke
admin.audit.view
admin.incidents.manage
admin.reports.export
admin.override.execute
```

Chaque domaine ajoute ses capacités.

---

# 11. Règle de capacité explicite

Une route sensible vérifie :

```text
compte
+ espace
+ organisation
+ capacité
+ pays
+ contexte
+ niveau d’authentification
```

Le simple nom de rôle ne suffit pas.

---

# 12. Tableau de bord global

Le tableau de bord principal affiche :

- utilisateurs actifs ;
- nouveaux comptes ;
- abonnements ;
- campagnes ;
- budgets ;
- WP distribués ;
- dépôts ;
- retraits ;
- réservations ;
- opérations Fonds ;
- alertes actives ;
- incidents Santé ;
- cartes actives ;
- partenaires ;
- Lives en cours ;
- incidents techniques ;
- files en retard ;
- comptes de suspense ;
- décisions en attente.

---

# 13. Indicateurs de santé des modules

Chaque module expose :

```text
healthy
degraded
incident
maintenance
disabled
```

Indicateurs :

- API ;
- workers ;
- files ;
- base de données ;
- latence ;
- échecs ;
- événements en attente ;
- rapprochements ;
- projections divergentes.

---

# 14. Centre de configuration

Le centre de configuration regroupe les paramètres fonctionnels.

Catégories :

- global ;
- pays ;
- devise ;
- abonnement ;
- publicité ;
- matching ;
- Feed ;
- Wallet ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- partenaires ;
- Live ;
- notifications ;
- sécurité ;
- modération ;
- intégrations.

---

# 15. Versionnement des configurations

Chaque configuration possède :

```text
code
version
scope
status
effective_from
effective_to
value
created_by
approved_by
published_at
```

États :

```text
draft
simulated
approved
scheduled
active
superseded
suspended
retired
```

---

# 16. Publication d’une configuration

Flux :

```text
création
→ validation technique
→ simulation
→ comparaison
→ approbation
→ programmation
→ publication
→ événement
→ cache invalidé
```

Une configuration active ne doit pas être modifiée en place.

Créer une nouvelle version.

---

# 17. Retour à une version précédente

Le fondateur peut :

- sélectionner une version ;
- voir les différences ;
- simuler ;
- publier une nouvelle version identique à l’ancienne ;
- documenter le motif.

Ne pas réécrire l’historique.

---

# 18. Feature flags

Les fonctionnalités peuvent être activées par :

- environnement ;
- pays ;
- ville ;
- pourcentage ;
- plan ;
- organisation ;
- utilisateur pilote ;
- date ;
- version mobile.

Exemples :

```text
feed.rewarded_ads
live.rewarded_blocks
card.health_emergency
fonds.collective_debit
wallet.external_withdrawal
```

---

# 19. Kill switches

Interrupteurs d’urgence :

```text
disable_all_withdrawals
disable_new_deposits
disable_ad_rewards
disable_live_rewards
disable_partner_cashback
disable_card_payments
disable_health_break_glass
```

Chaque activation exige :

- motif ;
- durée ;
- initiateur ;
- notification ;
- réévaluation.

---

# 20. Utilisateurs

Fonctions :

- rechercher ;
- consulter le compte ;
- voir les espaces ;
- voir le statut ;
- voir le plan ;
- voir les limitations ;
- voir les incidents ;
- suspendre ;
- restreindre ;
- restaurer ;
- lancer une assistance ;
- consulter l’audit.

L’administration ne doit pas afficher inutilement les données sensibles.

---

# 21. Vue 360° utilisateur

Onglets :

```text
Compte
Sécurité
Abonnement
Wallet
Fonds
Alertes
Santé — accès contrôlé
Carte
Live
Consentements
Organisations
Incidents
Audit
```

Les onglets sensibles exigent une capacité spécifique.

---

# 22. Restriction d’un utilisateur

Une restriction peut viser :

- connexion ;
- publicité ;
- gain ;
- retrait ;
- transfert ;
- Fonds ;
- Alertes ;
- Live ;
- Carte ;
- publication ;
- espace professionnel.

Éviter la suspension globale lorsqu’une restriction ciblée suffit.

---

# 23. Organisations

L’administration peut :

- créer ;
- vérifier ;
- suspendre ;
- restaurer ;
- examiner les documents ;
- gérer les représentants ;
- voir les membres ;
- contrôler les capacités ;
- voir les contrats ;
- consulter l’audit.

Types :

- annonceur ;
- partenaire ;
- institution ;
- Santé ;
- administration.

---

# 24. Membres et capacités d’organisation

Chaque membre est nominatif.

Fonctions :

- inviter ;
- accepter ;
- attribuer ;
- limiter ;
- expirer ;
- suspendre ;
- révoquer ;
- auditer.

Aucun compte partagé générique.

---

# 25. Abonnements

Administration :

- plans ;
- versions ;
- prix ;
- durées ;
- quotas ;
- poids ;
- classes ;
- accès Fonds ;
- avantages ;
- pays ;
- activation ;
- suspension ;
- migration ;
- remboursement.

Configuration initiale :

```text
Gratuit : quota 120 — poids 10 %
Premium : quota 300 — poids 20 %
Gold : quota 600 — poids 35 %
Platine : quota 900 — poids 35 %
```

---

# 26. Publicité

Administration :

- annonceurs ;
- campagnes ;
- créations ;
- ciblage ;
- devis ;
- financement ;
- approbation ;
- suspension ;
- budgets ;
- enveloppes ;
- fréquence ;
- signalements ;
- remboursements ;
- reporting.

---

# 27. Matching

Configuration :

- taxonomies ;
- critères autorisés ;
- critères interdits ;
- seuil de segment ;
- coefficients ;
- fréquence ;
- fatigue ;
- score ;
- explications ;
- territoires ;
- classes.

L’administration ne doit pas afficher une liste nominative d’un segment publicitaire.

---

# 28. Feed

Administration :

- types de contenu ;
- cadence ;
- insertions ;
- Alertes ;
- fréquence ;
- quotas ;
- seuil d’exposition ;
- barre ;
- heartbeats ;
- médias ;
- modération ;
- Explorer ;
- statistiques.

---

# 29. Wallet

Administration :

- dépôts ;
- retraits ;
- transferts ;
- réservations ;
- rapprochements ;
- litiges ;
- frais ;
- plafonds ;
- moyens de paiement ;
- incidents ;
- corrections ;
- comptes de suspense ;
- relevés.

---

# 30. Grand Livre

Fonctions :

- consulter les comptes ;
- consulter les transactions ;
- voir les écritures ;
- vérifier l’équilibre ;
- rechercher par référence ;
- suivre une compensation ;
- reconstruire une projection ;
- détecter une divergence ;
- exporter.

Interdit :

```text
modifier une écriture publiée
supprimer une transaction
éditer directement un solde
```

---

# 31. Corrections financières

Flux :

```text
proposition
→ simulation
→ vérification
→ approbation
→ transaction compensatoire
→ projection
→ notification
→ audit
```

Au-dessus d’un seuil, une approbation renforcée peut être exigée.

Le fondateur peut appliquer l’override exceptionnel, toujours tracé.

---

# 32. Super moteur de valeur

Administration :

- événements valorisables ;
- règles ;
- versions ;
- simulations ;
- tentatives ;
- réservations ;
- preuves ;
- décisions ;
- reprises ;
- compensations ;
- incidents ;
- latence ;
- doublons.

---

# 33. Fonds

Administration :

- programmes ;
- adhésions ;
- mandats ;
- vœux ;
- contributions ;
- collectes ;
- frais fixes ;
- régularisations ;
- prestataires ;
- réserves ;
- litiges ;
- reporting.

Le bénéficiaire est exclu de son propre débit collectif.

---

# 34. Alertes

Administration :

- catégories ;
- déclarations ;
- vérification ;
- modération ;
- priorité ;
- visibilité renforcée ;
- récompenses ;
- restitutions ;
- institutions ;
- routage ;
- projections ;
- clôture ;
- audit.

---

# 35. Santé

Administration séparée :

- établissements ;
- professionnels ;
- vérifications ;
- consentements ;
- capsules ;
- représentants ;
- accès d’urgence ;
- audits ;
- incidents.

L’administrateur général n’obtient pas automatiquement le contenu médical.

Une capacité Santé explicite reste obligatoire.

---

# 36. Carte Wasplex

Administration :

- offres ;
- cartes ;
- QR ;
- supports ;
- commandes ;
- expéditions ;
- activation ;
- suspension ;
- remplacement ;
- partenaires ;
- avantages ;
- opérations ;
- cashback ;
- accès Santé ;
- restitutions Alertes.

---

# 37. Partenaires

Administration :

- vérification ;
- contrats ;
- offres ;
- points de vente ;
- commissions ;
- règlements ;
- preuves ;
- remboursements ;
- équipes ;
- suspension ;
- audit.

---

# 38. Live

Administration :

- Lives programmés ;
- validation ;
- streaming ;
- créateurs ;
- annonceurs ;
- budgets ;
- places rémunérées ;
- blocs ;
- attention ;
- modération ;
- pause ;
- arrêt ;
- replay ;
- remboursements ;
- incidents.

---

# 39. Administration des règles pays

Chaque règle peut être limitée par :

- pays ;
- devise ;
- ville ;
- organisation ;
- plan ;
- période ;
- intégration ;
- environnement.

Exemples :

- moyen de retrait disponible ;
- plafond ;
- numéro d’urgence ;
- partenaire local ;
- prestataire ;
- frais ;
- fonctionnalité.

---

# 40. Assistance utilisateur contrôlée

L’administration peut ouvrir une session d’assistance.

Deux modes :

## 40.1. Vue reproduite

L’agent voit une représentation de l’écran sans agir comme l’utilisateur.

## 40.2. Assistance interactive

L’agent peut exécuter certaines actions autorisées avec indication visible.

Règles :

- motif ;
- durée ;
- consentement si requis par produit ;
- bannière visible ;
- audit ;
- aucune opération financière sensible sans confirmation renforcée.

---

# 41. Impersonation interdite par défaut

Ne pas permettre une connexion secrète comme l’utilisateur.

Préférer :

```text
Support Session
```

avec :

- identité de l’agent ;
- périmètre ;
- durée ;
- bannière ;
- journal ;
- actions limitées.

---

# 42. Centre de support

Fonctions :

- tickets ;
- catégories ;
- priorité ;
- utilisateur ;
- organisation ;
- module ;
- pièces ;
- conversation ;
- statut ;
- assignation ;
- résolution ;
- satisfaction ;
- audit.

---

# 43. États d’un ticket

```text
open
assigned
waiting_user
waiting_internal
resolved
closed
reopened
```

---

# 44. Centre d’incidents

Un incident peut concerner :

- paiement ;
- Wallet ;
- campagne ;
- Feed ;
- Live ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- partenaire ;
- sécurité ;
- infrastructure.

États :

```text
detected
investigating
mitigating
monitoring
resolved
postmortem
```

---

# 45. Gestion de crise

Actions :

- créer incident ;
- nommer responsable ;
- activer kill switch ;
- informer les équipes ;
- afficher une bannière ;
- suspendre une fonction ;
- lancer reprise ;
- clôturer ;
- produire un rapport.

---

# 46. Centre des tâches en attente

Files :

- KYC ;
- dépôts ;
- retraits ;
- campagnes ;
- partenaires ;
- Alertes ;
- Santé ;
- Live ;
- corrections ;
- litiges ;
- remboursements ;
- organisations.

Chaque file affiche :

- âge ;
- priorité ;
- risque ;
- assignation ;
- SLA interne ;
- statut.

---

# 47. Notifications administratives

Exemples :

- seuil financier ;
- compte de suspense ;
- rapprochement manquant ;
- budget épuisé ;
- campagne anormale ;
- Live signalé ;
- accès Santé inhabituel ;
- collecte Fonds en échec ;
- retrait élevé ;
- configuration publiée ;
- override fondateur.

---

# 48. Audit

Chaque action sensible conserve :

```text
actor_account_id
actor_space_id
role_context
capability
action
target_type
target_id
before
after
reason
ip
device
session_id
trace_id
created_at
```

---

# 49. Audit append-only

Le journal d’audit doit être :

- append-only ;
- horodaté ;
- recherché ;
- exportable ;
- protégé ;
- non modifiable par l’interface ;
- séparé des logs techniques temporaires.

---

# 50. Vue d’audit du fondateur

Le fondateur peut rechercher :

- par agent ;
- module ;
- action ;
- date ;
- utilisateur ;
- organisation ;
- montant ;
- override ;
- incident ;
- pays.

---

# 51. Reporting global

Rapports :

- acquisition ;
- abonnements ;
- publicité ;
- revenus ;
- WP distribués ;
- Wallet ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- partenaires ;
- Live ;
- fraude ;
- support ;
- incidents ;
- équipes ;
- performance.

---

# 52. Exports

Formats futurs :

- CSV ;
- PDF ;
- JSON ;
- rapports programmés.

Chaque export sensible est :

- autorisé ;
- journalisé ;
- limité ;
- expirant ;
- chiffré si nécessaire.

---

# 53. Observabilité technique

L’administration affiche :

- état API ;
- files d’attente ;
- workers ;
- jobs échoués ;
- événements outbox ;
- latence ;
- erreurs ;
- stockage ;
- streaming ;
- webhooks ;
- prestataires ;
- bases ;
- cache.

---

# 54. Gestion des intégrations

Pour chaque prestataire :

- statut ;
- environnement ;
- clés masquées ;
- webhooks ;
- dernière communication ;
- erreurs ;
- rapprochement ;
- limites ;
- mode maintenance ;
- bascule.

Les secrets ne sont jamais affichés en clair après enregistrement.

---

# 55. Gestion des modèles de message

Configurer :

- push ;
- SMS ;
- email ;
- notifications in-app ;
- langue ;
- pays ;
- version ;
- variables ;
- statut.

---

# 56. Gestion des contenus administrables

Exemples :

- textes d’onboarding ;
- FAQ ;
- aides ;
- explications ;
- pages de service ;
- messages de maintenance ;
- libellés ;
- catégories ;
- visuels.

Le contenu ne doit pas modifier silencieusement les règles métier.

---

# 57. Environnements

L’administration distingue :

```text
development
testing
staging
production
```

Les actions de production doivent être clairement identifiées.

---

# 58. Confirmations renforcées

Pour les actions critiques :

- confirmation textuelle ;
- MFA récente ;
- saisie du motif ;
- aperçu de l’impact ;
- double validation optionnelle ;
- délai de sécurité ;
- notification.

---

# 59. Seuils de double validation

Configurables pour :

- correction financière ;
- remboursement élevé ;
- suspension globale ;
- modification de frais ;
- publication économique ;
- accès Santé exceptionnel ;
- suppression d’organisation ;
- export massif.

Le fondateur peut utiliser l’override exceptionnel si nécessaire.

---

# 60. Règle “code d’abord”

Les décisions administratives doivent devenir :

```text
configuration
capacité
machine d’état
contrat
événement
test
```

Elles ne doivent pas devenir :

```text
texte abstrait
→ interprétation libre
→ blocage automatique
```

---

# 61. Modèle de données

Entités recommandées :

```text
admin_dashboards
admin_dashboard_widgets
admin_roles
admin_capabilities
admin_role_capabilities
admin_capability_grants
admin_sessions
admin_support_sessions

configuration_definitions
configuration_versions
configuration_scopes
configuration_publications
feature_flags
feature_flag_targets
kill_switches

admin_tasks
admin_task_assignments
support_tickets
support_ticket_messages
incidents
incident_events
incident_actions
admin_notifications

founder_overrides
founder_override_actions
admin_approvals
admin_action_requests
admin_exports
admin_audit_events
admin_saved_views
```

---

# 62. Champs — Founder Override

```text
id
requested_by
executed_by
module
action_code
target_type
target_id
reason
risk_level
before_snapshot
after_snapshot
status
ledger_transaction_id
executed_at
created_at
```

---

# 63. États d’un override

```text
draft
confirmed
executing
completed
failed
compensated
cancelled
```

---

# 64. API administration globale

```text
GET    /api/admin/dashboard
GET    /api/admin/modules/health
GET    /api/admin/tasks
GET    /api/admin/notifications

GET    /api/admin/configurations
POST   /api/admin/configurations
POST   /api/admin/configurations/{id}/simulate
POST   /api/admin/configurations/{id}/approve
POST   /api/admin/configurations/{id}/publish
POST   /api/admin/configurations/{id}/suspend

GET    /api/admin/feature-flags
POST   /api/admin/feature-flags
PATCH  /api/admin/feature-flags/{id}

GET    /api/admin/kill-switches
POST   /api/admin/kill-switches/{id}/activate
POST   /api/admin/kill-switches/{id}/deactivate
```

---

# 65. API utilisateurs et organisations

```text
GET    /api/admin/accounts
GET    /api/admin/accounts/{id}
POST   /api/admin/accounts/{id}/restrict
POST   /api/admin/accounts/{id}/restore

GET    /api/admin/organizations
GET    /api/admin/organizations/{id}
POST   /api/admin/organizations/{id}/verify
POST   /api/admin/organizations/{id}/suspend
POST   /api/admin/organizations/{id}/restore

POST   /api/admin/capability-grants
DELETE /api/admin/capability-grants/{id}
```

---

# 66. API support et incidents

```text
GET    /api/admin/support/tickets
POST   /api/admin/support/tickets
PATCH  /api/admin/support/tickets/{id}
POST   /api/admin/support/sessions

GET    /api/admin/incidents
POST   /api/admin/incidents
POST   /api/admin/incidents/{id}/actions
POST   /api/admin/incidents/{id}/resolve
```

---

# 67. API override fondateur

```text
GET    /api/admin/founder-overrides
POST   /api/admin/founder-overrides
POST   /api/admin/founder-overrides/{id}/confirm
POST   /api/admin/founder-overrides/{id}/execute
POST   /api/admin/founder-overrides/{id}/compensate
GET    /api/admin/founder-overrides/{id}
```

Ces routes exigent une capacité spécifique et une authentification renforcée.

---

# 68. Événements métier

```text
AdminConfigurationCreated
AdminConfigurationPublished
AdminConfigurationSuspended
FeatureFlagEnabled
FeatureFlagDisabled
KillSwitchActivated
KillSwitchDeactivated

AdminCapabilityGranted
AdminCapabilityRevoked
AdminAccountRestricted
AdminAccountRestored
OrganizationVerified
OrganizationSuspended

SupportSessionStarted
SupportSessionEnded
IncidentCreated
IncidentMitigated
IncidentResolved

FounderOverrideRequested
FounderOverrideConfirmed
FounderOverrideExecuted
FounderOverrideFailed
FounderOverrideCompensated

AdminExportCreated
AdminAuditEventRecorded
```

---

# 69. Écrans principaux

## 69.1. Accueil administration

- KPI ;
- incidents ;
- tâches ;
- finances ;
- modules ;
- alertes.

## 69.2. Centre de configuration

- catégories ;
- versions ;
- simulation ;
- publication.

## 69.3. Utilisateurs

- recherche ;
- vue 360° ;
- restrictions ;
- assistance.

## 69.4. Finances

- Wallet ;
- Ledger ;
- dépôts ;
- retraits ;
- corrections ;
- rapprochement.

## 69.5. Opérations

- campagnes ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Live.

## 69.6. Sécurité et audit

- rôles ;
- capacités ;
- sessions ;
- overrides ;
- journaux.

---

# 70. Navigation recommandée

```text
Vue d’ensemble
Utilisateurs
Organisations
Économie
Publicité
Wallet
Fonds
Alertes
Santé
Carte
Partenaires
Live
Modération
Support
Incidents
Configurations
Permissions
Audit
Rapports
Système
```

---

# 71. Recherche globale

La recherche peut retrouver :

- compte ;
- téléphone masqué ;
- email masqué ;
- identifiant ;
- organisation ;
- campagne ;
- transaction ;
- alerte ;
- carte ;
- Live ;
- ticket ;
- incident.

Les résultats sont filtrés selon les capacités.

---

# 72. Sécurité

- MFA obligatoire ;
- session courte ;
- appareil vérifié ;
- IP et géographie ;
- réauthentification ;
- rate limiting ;
- session de support contrôlée ;
- permissions ;
- audit ;
- secrets masqués ;
- alertes d’accès ;
- séparation des environnements.

---

# 73. Comptes administrateurs

Interdictions :

- compte partagé ;
- identifiant générique ;
- mot de passe commun ;
- action sans acteur ;
- suppression des logs.

Chaque agent possède son compte personnel.

---

# 74. Performance

- tableaux agrégés ;
- chargement par module ;
- pagination ;
- filtres ;
- index ;
- tâches asynchrones ;
- exports différés ;
- cache des configurations ;
- aucune requête globale lourde à chaque page.

---

# 75. Tests des permissions

- accès sans capacité refusé ;
- capacité de domaine ;
- capacité pays ;
- capacité expirée ;
- rôle sans capacité ;
- fondateur ;
- override ;
- MFA ;
- support session ;
- audit.

---

# 76. Tests des configurations

- création ;
- simulation ;
- publication ;
- date d’effet ;
- version ;
- rollback par nouvelle version ;
- scope pays ;
- feature flag ;
- kill switch ;
- cache invalidé.

---

# 77. Tests financiers

- aucun solde direct ;
- correction compensatoire ;
- override financier ;
- double exécution impossible ;
- idempotence ;
- Ledger ;
- projection ;
- audit ;
- seuil d’approbation.

---

# 78. Tests du fondateur

- accès global ;
- modification de configuration ;
- suspension ;
- override ;
- motif obligatoire ;
- MFA ;
- action visible dans l’audit ;
- impossibilité de supprimer l’audit ;
- impossibilité d’éditer une écriture ;
- compensation.

---

# 79. Tests support

- vue reproduite ;
- session interactive ;
- bannière ;
- durée ;
- action limitée ;
- fin de session ;
- journal ;
- opération sensible protégée.

---

# 80. Tests incidents

- création ;
- kill switch ;
- notification ;
- reprise ;
- résolution ;
- rapport ;
- réactivation ;
- audit.

---

# 81. Tests visuels

Captures minimales :

1. dashboard global ;
2. modules ;
3. finances ;
4. utilisateur 360° ;
5. organisation ;
6. configuration ;
7. simulation ;
8. publication ;
9. feature flags ;
10. kill switch ;
11. Wallet ;
12. Grand Livre ;
13. Fonds ;
14. Alertes ;
15. Santé ;
16. Carte ;
17. Live ;
18. support ;
19. incident ;
20. override fondateur ;
21. audit ;
22. mobile/tablette si administration responsive.

---

# 82. Critères d’acceptation

Le module est accepté lorsque :

1. le fondateur voit tous les modules ;
2. les configurations sont administrables ;
3. les versions sont conservées ;
4. les feature flags existent ;
5. les kill switches existent ;
6. les utilisateurs et organisations sont supervisables ;
7. les capacités sont explicites ;
8. aucun compte administrateur n’est partagé ;
9. Wallet et Grand Livre sont consultables ;
10. aucune écriture publiée n’est modifiable ;
11. les corrections sont compensatoires ;
12. le fondateur dispose d’un override réel ;
13. tout override est audité ;
14. les modules Santé restent à accès séparé ;
15. le support contrôlé existe ;
16. les incidents sont gérables ;
17. les tâches en attente sont centralisées ;
18. les exports sont sécurisés ;
19. les actions critiques exigent MFA ;
20. les tests critiques passent.

---

# 83. Ordre d’implémentation

## Phase 1 — Socle administration

- layout ;
- authentification ;
- capacités ;
- navigation ;
- audit.

## Phase 2 — Tableau de bord

- widgets ;
- santé des modules ;
- tâches ;
- incidents.

## Phase 3 — Centre de configuration

- définitions ;
- versions ;
- simulation ;
- publication ;
- feature flags.

## Phase 4 — Utilisateurs et organisations

- recherche ;
- vue 360° ;
- restrictions ;
- capacités ;
- espaces.

## Phase 5 — Finances

- Wallet ;
- Ledger ;
- dépôts ;
- retraits ;
- corrections ;
- rapprochement.

## Phase 6 — Modules métier

- Publicité ;
- Feed ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Partenaires ;
- Live.

## Phase 7 — Support et incidents

- tickets ;
- sessions ;
- kill switches ;
- rapports.

## Phase 8 — Override fondateur

- modèle ;
- confirmation ;
- exécution ;
- compensation ;
- audit.

## Phase 9 — Reporting et stabilisation

- exports ;
- performance ;
- sécurité ;
- tests ;
- captures.

---

# 84. Première verticale à livrer

```text
Fondateur se connecte avec MFA
→ ouvre le tableau de bord
→ modifie le quota Gold dans une nouvelle version
→ simule l’impact
→ publie la configuration
→ événement diffusé
→ cache invalidé
→ nouvelle valeur appliquée
→ audit visible
```

Deuxième verticale :

```text
Dépôt utilisateur bloqué
→ fondateur ouvre le dossier
→ vérifie la preuve
→ exécute une intervention exceptionnelle
→ transaction Grand Livre créée
→ Wallet crédité
→ audit complet
```

---

# 85. Directive pour Claude Code

1. lire toutes les notes de domaine ;
2. auditer le nouveau dépôt ;
3. ne pas dupliquer les services métier dans Admin ;
4. créer des capacités explicites ;
5. donner au fondateur une supervision réelle ;
6. implémenter les configurations versionnées ;
7. implémenter feature flags et kill switches ;
8. interdire les modifications directes de soldes ;
9. créer l’override fondateur traçable ;
10. séparer Santé des autres espaces ;
11. fournir les migrations ;
12. fournir les tests ;
13. fournir les captures ;
14. ne pas introduire de constitution ou de texte bloquant.

---

# 86. Décision finale

L’administration centrale est le poste de commandement de Wasplex.

Elle doit permettre :

```text
Voir
→ comprendre
→ configurer
→ simuler
→ publier
→ superviser
→ intervenir
→ corriger
→ auditer
```

Le principe final est :

> **Le fondateur garde la main sur toutes les configurations et peut intervenir exceptionnellement lorsque Wasplex l’exige, mais aucune intervention ne doit supprimer la vérité financière, effacer l’historique ou rendre l’action invisible.**
