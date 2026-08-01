# WASPLEX — REPORTING, STATISTIQUES, AUDIT & OBSERVABILITÉ

**Fichier cible recommandé :** `docs/17-reporting-observabilite/00-reporting-statistiques-audit-observabilite-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module transversal de mesure, reporting, audit métier, supervision opérationnelle et observabilité technique  
**Interfaces officielles :**
- espace utilisateur : mobile-first strict, synthèses simples ;
- Studio Annonceur, partenaires, professionnels et institutions : desktop complet + mobile opérationnel ;
- administration : desktop-first, mobile limité aux alertes critiques, incidents et indicateurs essentiels.  
**Dépendances :** tous les modules Wasplex, notamment Compte universel, Abonnements, Studio Annonceur, Matching, Feed, Wallet & Grand Livre, Super moteur de valeur, Fonds, Alertes, Santé, Carte, Live, Espaces professionnels, Notifications, Sécurité & Antifraude, Données & Permissions, Administration centrale  
**Principe central :** Wasplex doit pouvoir expliquer ce qui s’est passé, mesurer ce qui fonctionne, détecter ce qui dérive et permettre au fondateur de piloter le produit à partir de données fiables  
**Directive produit :** ce module ne crée ni gouvernance doctrinale ni blocage abstrait ; il transforme les événements réels en indicateurs, audits, alertes, rapports et outils de diagnostic

---

# 1. Objet

Ce document définit :

- les événements analytiques ;
- les indicateurs produit ;
- les indicateurs économiques ;
- les indicateurs financiers ;
- les indicateurs opérationnels ;
- les tableaux de bord utilisateur ;
- les tableaux de bord annonceur ;
- les tableaux de bord partenaires ;
- les tableaux de bord institutionnels ;
- les tableaux de bord Santé ;
- les tableaux de bord administratifs ;
- les rapports ;
- les exports ;
- les audits métier ;
- les logs techniques ;
- les métriques ;
- les traces distribuées ;
- les alertes techniques ;
- les incidents ;
- les objectifs de service ;
- les historiques ;
- les API ;
- les modèles de données ;
- les événements ;
- les tests.

---

# 2. Vision produit

La chaîne cible est :

```text
événement métier réel
→ collecte
→ validation
→ projection analytique
→ agrégation
→ indicateur
→ tableau de bord
→ décision
```

Pour un incident :

```text
dégradation détectée
→ métrique ou trace
→ alerte
→ incident
→ diagnostic
→ action
→ résolution
→ rapport
```

Pour un audit :

```text
action sensible
→ événement d’audit append-only
→ consultation
→ preuve
→ export contrôlé
```

---

# 3. Quatre familles distinctes

## 3.1. Reporting

Présentation d’informations utiles à un acteur.

Exemples :

- résultats d’une campagne ;
- activité partenaire ;
- évolution du Wallet ;
- performance Fonds.

## 3.2. Statistiques

Agrégats permettant de comprendre les volumes, tendances et répartitions.

## 3.3. Audit

Historique nominatif et traçable des actions sensibles.

## 3.4. Observabilité

Capacité technique à comprendre l’état du système à partir de :

```text
metrics
logs
traces
events
health checks
```

Ces quatre familles utilisent des données communes, mais ne doivent pas être confondues.

---

# 4. Ce que le module doit permettre

- savoir ce qui s’est passé ;
- mesurer les volumes ;
- mesurer la qualité ;
- mesurer les revenus ;
- mesurer les dépenses ;
- suivre les réservations ;
- détecter les écarts ;
- comparer les périodes ;
- suivre les modules ;
- suivre les prestataires ;
- diagnostiquer les incidents ;
- prouver les actions ;
- produire des rapports ;
- exporter ;
- alerter ;
- auditer.

---

# 5. Ce que le module ne doit pas faire

Il ne doit pas :

- modifier les données sources ;
- recalculer librement le Grand Livre ;
- devenir la source de vérité financière ;
- exposer les identités aux annonceurs ;
- mélanger les données Santé avec le reporting publicitaire ;
- permettre des exports globaux sans capacité ;
- remplacer les services métier ;
- bloquer le code par des règles abstraites ;
- supprimer les audits ;
- transformer les logs en base métier.

---

# 6. Sources de vérité

Chaque indicateur doit déclarer sa source.

Exemples :

```text
Wallet et finances
→ Grand Livre

Campagnes
→ Campaign Events + Budget Reservations

Feed
→ AdDelivered + QualifiedAttention

Fonds
→ Fonds Events + Ledger

Live
→ Live Session Events + Ledger

Alertes
→ Alert Case Events

Santé
→ Health Access Events

Administration
→ Admin Audit Events
```

---

# 7. Registre des événements analytiques

Chaque événement analytique possède :

```text
code
domain
version
description
source_event
dimensions
measures
privacy_class
retention_policy
status
```

---

# 8. Événements métier et événements analytiques

Un événement métier exprime un fait.

Exemple :

```text
CampaignApproved
```

Un événement analytique peut en produire une projection :

```text
campaign_approved_v1
```

Le fait métier reste la source.

---

# 9. Qualité des événements

Chaque événement doit être :

- horodaté ;
- versionné ;
- idempotent ;
- relié à une source ;
- associé à un trace_id ;
- validé ;
- rejouable si nécessaire ;
- documenté.

---

# 10. Dimensions communes

Exemples :

```text
date
hour
country
city_or_zone
currency
module
plan
economic_class
organization
campaign
partner
device_type
app_version
channel
status
```

Les dimensions sensibles doivent être agrégées ou exclues selon le contexte.

---

# 11. Mesures communes

Exemples :

```text
count
amount
duration
latency
success_rate
failure_rate
completion_rate
conversion_rate
active_users
unique_users
reserved_amount
captured_amount
released_amount
```

---

# 12. Identifiants analytiques

Les pipelines analytiques peuvent utiliser :

- identifiant pseudonyme ;
- identifiant de campagne ;
- identifiant d’organisation ;
- identifiant de session ;
- identifiant de transaction ;
- trace_id.

Les identifiants personnels bruts ne sont pas nécessaires dans tous les rapports.

---

# 13. Architecture de collecte

```text
Services métier
→ événements/outbox
→ bus ou file
→ collecteur analytique
→ stockage brut contrôlé
→ transformations
→ agrégats
→ API reporting
```

---

# 14. Séparation OLTP et analytique

Les tableaux de bord lourds ne doivent pas interroger directement toutes les tables transactionnelles.

Architecture recommandée :

```text
Base métier
→ événements
→ projections analytiques
→ entrepôt ou tables d’agrégats
```

---

# 15. Données brutes et agrégées

## Données brutes

Utilisées pour :

- diagnostic ;
- recalcul ;
- audit technique ;
- transformations.

## Données agrégées

Utilisées pour :

- tableaux de bord ;
- rapports ;
- tendances ;
- comparaisons.

---

# 16. Fréquences de mise à jour

```text
temps réel
quasi temps réel
horaire
quotidien
hebdomadaire
mensuel
à la demande
```

Chaque indicateur déclare sa fréquence.

---

# 17. Temps réel

Réservé aux usages qui le nécessitent :

- crédit Wallet ;
- consommation de budget ;
- Live ;
- incident ;
- service dégradé ;
- file critique ;
- campagne active.

Les rapports historiques peuvent être calculés de manière différée.

---

# 18. Tableau de bord du fondateur

Le fondateur doit voir :

- utilisateurs actifs ;
- inscriptions ;
- abonnements ;
- revenus ;
- budgets annonceurs ;
- enveloppes utilisateurs ;
- WP distribués ;
- dépôts ;
- retraits ;
- fonds disponibles ;
- réservations ;
- campagnes actives ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- partenaires ;
- Lives ;
- fraude ;
- support ;
- incidents ;
- santé technique des modules.

---

# 19. Vue économique globale

Indicateurs :

```text
revenu brut
revenu net
part Wasplex
part utilisateurs
budgets réservés
budgets consommés
remboursements
frais
dépôts
retraits
cashback
fonds collectés
paiements prestataires
rémunérations Live
```

---

# 20. Vue par pays et devise

Le fondateur peut filtrer par :

- pays ;
- devise ;
- ville ;
- territoire ;
- période ;
- module ;
- plan ;
- partenaire ;
- organisation.

---

# 21. Vue des abonnements

Indicateurs :

- abonnés par plan ;
- nouveaux abonnements ;
- renouvellements ;
- expirations ;
- migrations ;
- résiliations ;
- revenus ;
- quota moyen consommé ;
- répartition Gratuit/Premium/Gold/Platine.

---

# 22. Reporting utilisateur

L’utilisateur voit des synthèses simples :

- WP gagnés ;
- revenus publicitaires ;
- dépôts ;
- retraits ;
- transferts ;
- Fonds ;
- avantages Carte ;
- Live ;
- évolution du mois ;
- quotas publicitaires.

Le reporting utilisateur reste mobile-first.

---

# 23. Synthèse Wallet utilisateur

```text
Solde disponible
Gains du mois
Dépôts
Retraits
Transferts
Montants réservés
Solde Fonds
Contribution à régulariser
```

Le Grand Livre reste source de vérité.

---

# 24. Reporting Studio Annonceur

Le Studio affiche :

- budget total ;
- budget réservé ;
- budget consommé ;
- budget restant ;
- portée ;
- livraisons ;
- attention qualifiée ;
- complétions ;
- interactions ;
- CTA ;
- coût moyen ;
- résultats par zone ;
- résultats par classe ;
- résultats par création ;
- remboursements.

---

# 25. Reporting annonceur agrégé

L’annonceur ne reçoit jamais :

- noms ;
- téléphones ;
- emails ;
- Wallets ;
- historiques individuels ;
- Santé ;
- Alertes ;
- Fonds ;
- KYC.

---

# 26. Comparaison de campagnes

Le Studio peut comparer :

- campagne A/B ;
- période ;
- création ;
- audience ;
- budget ;
- zone ;
- objectif ;
- coût ;
- taux de complétion ;
- CTA.

---

# 27. Reporting partenaire

Indicateurs :

- opérations ;
- montants ;
- offres utilisées ;
- avantages ;
- cashback ;
- remboursements ;
- règlements ;
- litiges ;
- points de vente ;
- agents ;
- anomalies.

---

# 28. Reporting prestataire Fonds

Indicateurs :

- dossiers reçus ;
- devis ;
- prestations ;
- paiements ;
- délais ;
- rejets ;
- reliquats ;
- preuves manquantes.

---

# 29. Reporting institutionnel

Indicateurs :

- dossiers entrants ;
- priorités ;
- affectations ;
- transferts ;
- délais ;
- clôtures ;
- territoires ;
- agents ;
- dossiers en retard ;
- événements ;
- incidents.

Les rapports institutionnels respectent leur périmètre.

---

# 30. Reporting Santé

Indicateurs agrégés :

- établissements ;
- professionnels ;
- accès autorisés ;
- accès d’urgence ;
- accès expirés ;
- incidents ;
- demandes ;
- temps de traitement.

Aucun tableau général ne doit exposer le contenu médical.

---

# 31. Reporting Feed

Indicateurs :

- sessions ;
- contenus ;
- publicités livrées ;
- quotas consommés ;
- attention qualifiée ;
- abandons ;
- erreurs ;
- temps de visionnage ;
- fréquence ;
- signalements ;
- insertions Alertes ;
- performance réseau.

---

# 32. Reporting campagnes

Indicateurs :

- campagnes créées ;
- financées ;
- soumises ;
- approuvées ;
- refusées ;
- actives ;
- suspendues ;
- terminées ;
- remboursées ;
- délai de revue ;
- budget ;
- consommation.

---

# 33. Reporting Matching

Indicateurs :

- requêtes ;
- candidats ;
- campagnes éligibles ;
- taux de match ;
- segments trop petits ;
- campagnes non livrées ;
- fréquence ;
- fatigue ;
- explications ;
- latence.

---

# 34. Reporting Super moteur de valeur

Indicateurs :

- quotes ;
- attempts ;
- reservations ;
- captures ;
- releases ;
- compensations ;
- preuves ;
- échecs ;
- doublons ;
- latence ;
- files.

---

# 35. Reporting Wallet & Grand Livre

Indicateurs :

- transactions ;
- écritures ;
- volumes ;
- comptes ;
- réservations ;
- dépôts ;
- retraits ;
- transferts ;
- suspense ;
- rapprochements ;
- compensations ;
- divergences de projection.

---

# 36. Contrôle d’équilibre du Grand Livre

Le module doit vérifier :

```text
somme débits = somme crédits
```

Par :

- transaction ;
- période ;
- devise ;
- compte ;
- journal.

Toute divergence déclenche une alerte critique.

---

# 37. Reporting Fonds

Indicateurs :

- adhésions ;
- mandats ;
- vœux ;
- contributions personnelles ;
- collectes ;
- comptes débités ;
- frais fixes ;
- régularisations ;
- prestataires ;
- paiements ;
- réserves ;
- délais.

---

# 38. Reporting Alertes

Indicateurs :

- déclarations ;
- catégories ;
- priorités ;
- vérifications ;
- correspondances ;
- restitutions ;
- institutions ;
- transferts ;
- clôtures ;
- délais ;
- visibilité renforcée ;
- récompenses ;
- signalements.

---

# 39. Reporting Carte

Indicateurs :

- cartes virtuelles ;
- cartes physiques ;
- activations ;
- suspensions ;
- pertes ;
- remplacements ;
- scans ;
- opérations ;
- avantages ;
- cashback ;
- accès Santé ;
- restitutions.

---

# 40. Reporting Live

Indicateurs :

- Lives programmés ;
- Lives actifs ;
- spectateurs ;
- places rémunérées ;
- liste d’attente ;
- blocs financés ;
- blocs validés ;
- blocs rejetés ;
- budgets ;
- revenus Wasplex ;
- rémunérations ;
- créateurs ;
- incidents ;
- modération.

---

# 41. Reporting Notifications

Indicateurs :

- notifications créées ;
- envois ;
- livraisons ;
- lectures ;
- actions ;
- échecs ;
- SMS ;
- emails ;
- push ;
- temps réel ;
- modèles ;
- prestataires.

---

# 42. Reporting Support

Indicateurs :

- tickets ;
- catégories ;
- priorités ;
- temps de première réponse ;
- temps de résolution ;
- réouvertures ;
- satisfaction ;
- équipe ;
- files ;
- incidents liés.

---

# 43. Reporting Sécurité & Antifraude

Indicateurs :

- signaux ;
- évaluations ;
- décisions ;
- holds ;
- dossiers ;
- sanctions ;
- réexamens ;
- faux positifs ;
- valeur protégée ;
- valeur libérée ;
- incidents ;
- kill switches.

---

# 44. Audit métier

L’audit métier répond à :

```text
qui ?
dans quel espace ?
pour quelle organisation ?
avec quelle capacité ?
a fait quoi ?
sur quelle ressource ?
pourquoi ?
quand ?
depuis quelle session ?
avec quel résultat ?
```

---

# 45. Audit append-only

L’audit doit être :

- append-only ;
- horodaté ;
- non modifiable par l’interface ;
- indexé ;
- exportable sous contrôle ;
- relié à un trace_id ;
- protégé ;
- distinct des logs temporaires.

---

# 46. Types d’événements audités

- connexion administrateur ;
- attribution de capacité ;
- révocation ;
- configuration ;
- publication ;
- correction financière ;
- accès Santé ;
- transfert institutionnel ;
- sanction ;
- override fondateur ;
- export ;
- suppression ;
- kill switch ;
- opération partenaire ;
- rapprochement.

---

# 47. Audit utilisateur

L’utilisateur peut consulter une partie de son audit :

- connexions ;
- appareils ;
- accès Santé ;
- exports ;
- changements de sécurité ;
- consentements ;
- sessions professionnelles ;
- opérations importantes.

---

# 48. Audit professionnel

Une organisation voit :

- actions de ses membres ;
- capacités ;
- invitations ;
- opérations ;
- dossiers ;
- exports ;
- modifications ;
- incidents.

Elle ne voit pas les audits d’une autre organisation.

---

# 49. Audit du fondateur

Le fondateur peut rechercher par :

- acteur ;
- module ;
- organisation ;
- action ;
- montant ;
- période ;
- dossier ;
- transaction ;
- override ;
- pays ;
- appareil ;
- session ;
- trace.

---

# 50. Logs techniques

Les logs techniques servent à diagnostiquer :

- erreurs ;
- warnings ;
- appels ;
- workers ;
- webhooks ;
- files ;
- intégrations ;
- exceptions ;
- délais.

Ils ne doivent pas contenir inutilement :

- mots de passe ;
- secrets ;
- données médicales ;
- contenus sensibles complets ;
- numéros de carte ;
- OTP.

---

# 51. Niveaux de logs

```text
debug
info
notice
warning
error
critical
alert
emergency
```

La production doit limiter les logs debug.

---

# 52. Logs structurés

Format recommandé :

```text
timestamp
level
service
environment
event_code
message
trace_id
span_id
account_id_pseudonym
organization_id
module
status
duration_ms
```

---

# 53. Métriques

Familles :

```text
counters
gauges
histograms
timers
rates
```

Exemples :

- requêtes ;
- erreurs ;
- latence ;
- files ;
- workers ;
- mémoire ;
- CPU ;
- connexions ;
- webhooks ;
- transactions ;
- événements.

---

# 54. Traces distribuées

Une trace doit permettre de suivre :

```text
requête utilisateur
→ API
→ service métier
→ Grand Livre
→ outbox
→ notification
→ projection
```

Chaque service propage :

- trace_id ;
- span_id ;
- parent_span_id.

---

# 55. Health checks

Chaque service expose :

```text
liveness
readiness
dependencies
version
build
```

États :

```text
healthy
degraded
unhealthy
maintenance
disabled
```

---

# 56. Objectifs de service

Pour chaque fonction critique, définir :

- disponibilité cible ;
- latence cible ;
- taux d’erreur ;
- temps de traitement ;
- fraîcheur des données ;
- délai de récupération.

Ces objectifs sont techniques et configurables.

---

# 57. Indicateurs de niveau de service

Exemples :

```text
API success rate
p95 latency
wallet credit delay
campaign activation delay
notification delivery rate
webhook processing delay
live heartbeat delay
```

---

# 58. Budget d’erreur technique

Un budget d’erreur peut aider à décider :

- poursuivre le déploiement ;
- ralentir ;
- corriger ;
- activer un mode dégradé.

Il ne doit pas devenir une gouvernance bloquante automatique.

---

# 59. Alertes techniques

Une alerte doit préciser :

- service ;
- signal ;
- seuil ;
- gravité ;
- environnement ;
- heure ;
- runbook ;
- responsable ;
- état.

---

# 60. Gravités

```text
sev0
sev1
sev2
sev3
sev4
```

Exemple :

- SEV0 : risque majeur global ;
- SEV1 : fonction critique indisponible ;
- SEV2 : dégradation importante ;
- SEV3 : anomalie limitée ;
- SEV4 : information.

---

# 61. Routage des alertes

Selon la gravité :

- tableau de bord ;
- push administrateur ;
- SMS ;
- email ;
- canal interne ;
- incident automatique ;
- fondateur.

---

# 62. Déduplication

Le système doit :

- regrouper les alertes identiques ;
- éviter les tempêtes ;
- corréler ;
- appliquer une fenêtre ;
- fermer automatiquement après retour à la normale.

---

# 63. Incidents

Un incident contient :

- titre ;
- gravité ;
- module ;
- services ;
- impact ;
- début ;
- responsable ;
- actions ;
- statut ;
- résolution ;
- rapport.

---

# 64. États d’incident

```text
detected
triaged
investigating
mitigating
monitoring
resolved
postmortem
closed
```

---

# 65. Timeline d’incident

La timeline contient :

- alerte ;
- décisions ;
- kill switches ;
- communications ;
- changements ;
- mesures ;
- rétablissement ;
- clôture.

---

# 66. Rapport d’incident

Il décrit :

- ce qui s’est passé ;
- impact ;
- durée ;
- cause ;
- détection ;
- réponse ;
- restauration ;
- actions correctives ;
- responsables ;
- échéances.

Il ne doit pas devenir un texte de gouvernance.

---

# 67. Mode dégradé

Exemples :

- Feed sans récompenses ;
- dépôts en attente ;
- retraits suspendus ;
- Live non rémunéré ;
- notifications in-app seulement ;
- lecture Wallet disponible ;
- création campagne en brouillon seulement.

---

# 68. Observabilité des intégrations

Pour chaque prestataire :

- statut ;
- latence ;
- erreurs ;
- webhooks ;
- dernière réussite ;
- volume ;
- retries ;
- rapprochement ;
- limite ;
- version.

---

# 69. Observabilité des files

Indicateurs :

- taille ;
- âge du plus ancien job ;
- taux de traitement ;
- échecs ;
- retries ;
- dead letters ;
- workers ;
- saturation.

---

# 70. Dead-letter queue

Chaque événement non traité après les reprises doit :

- être conservé ;
- être visible ;
- être inspectable ;
- être rejouable ;
- produire une alerte ;
- préserver l’idempotence.

---

# 71. Observabilité de l’outbox

Indicateurs :

- événements créés ;
- non publiés ;
- âge ;
- publications ;
- doublons ;
- échecs ;
- reprises.

---

# 72. Fraîcheur des données

Chaque tableau de bord affiche :

```text
mis à jour il y a ...
```

Le système mesure :

- retard ;
- dernière transformation ;
- dernière source ;
- jobs en échec.

---

# 73. Qualité des données

Contrôles :

- événements manquants ;
- doublons ;
- champs invalides ;
- dimensions inconnues ;
- montants incohérents ;
- devises ;
- horodatages ;
- ruptures de série ;
- dérive.

---

# 74. Réconciliation analytique

Comparer :

- événements ;
- agrégats ;
- Grand Livre ;
- budgets ;
- projections ;
- rapports.

Les écarts produisent une alerte, mais ne corrigent pas automatiquement la source métier.

---

# 75. Tableaux de bord configurables

L’administration peut :

- ajouter des widgets ;
- enregistrer des vues ;
- filtrer ;
- partager ;
- épingler ;
- programmer un rapport ;
- exporter.

Les indicateurs disponibles restent contrôlés par capacités.

---

# 76. Widgets

Exemples :

- KPI ;
- courbe ;
- histogramme ;
- tableau ;
- carte ;
- jauge ;
- liste d’incidents ;
- file ;
- évolution ;
- comparaison.

---

# 77. Filtres communs

- période ;
- pays ;
- devise ;
- module ;
- plan ;
- classe ;
- campagne ;
- organisation ;
- partenaire ;
- statut ;
- environnement.

---

# 78. Rapports programmés

Un rapport peut être envoyé :

- quotidiennement ;
- hebdomadairement ;
- mensuellement ;
- à une date ;
- après clôture de campagne ;
- après incident.

Canaux :

- centre de rapports ;
- email ;
- notification.

---

# 79. États d’un rapport

```text
draft
scheduled
generating
ready
delivered
failed
expired
cancelled
```

---

# 80. Exports

Formats :

- CSV ;
- XLSX futur ;
- PDF ;
- JSON ;
- image de graphique.

Les exports doivent être :

- autorisés ;
- filtrés ;
- journalisés ;
- temporaires ;
- limités ;
- protégés.

---

# 81. Exports massifs

Ils peuvent exiger :

- capacité spécifique ;
- MFA récente ;
- motif ;
- validation ;
- génération asynchrone ;
- expiration ;
- audit.

---

# 82. API reporting utilisateur

```text
GET /api/me/reporting/summary
GET /api/me/reporting/wallet
GET /api/me/reporting/fonds
GET /api/me/reporting/card
GET /api/me/reporting/live
```

---

# 83. API Studio Annonceur

```text
GET /api/advertiser/reporting/overview
GET /api/advertiser/campaigns/{id}/report
GET /api/advertiser/campaigns/{id}/timeseries
GET /api/advertiser/campaigns/{id}/breakdown
POST /api/advertiser/reports
GET /api/advertiser/reports/{id}
```

---

# 84. API professionnels

```text
GET /api/professional/reporting/overview
GET /api/professional/reporting/operations
GET /api/professional/reporting/team
GET /api/professional/reporting/incidents
POST /api/professional/reports
```

---

# 85. API administration

```text
GET /api/admin/reporting/dashboard
GET /api/admin/reporting/economy
GET /api/admin/reporting/modules
GET /api/admin/reporting/countries
GET /api/admin/reporting/organizations
POST /api/admin/reporting/reports
GET /api/admin/reporting/reports/{id}
```

---

# 86. API audit

```text
GET /api/admin/audit/events
GET /api/admin/audit/events/{id}
POST /api/admin/audit/exports

GET /api/me/security-audit
GET /api/organizations/{id}/audit
```

---

# 87. API observabilité

```text
GET /api/admin/observability/overview
GET /api/admin/observability/services
GET /api/admin/observability/services/{id}
GET /api/admin/observability/queues
GET /api/admin/observability/integrations
GET /api/admin/observability/incidents
POST /api/admin/observability/incidents
POST /api/admin/observability/incidents/{id}/actions
```

---

# 88. Modèle de données

Entités recommandées :

```text
analytics_event_definitions
analytics_events
analytics_event_failures
analytics_dimensions
analytics_measures

reporting_datasets
reporting_dataset_versions
reporting_aggregates
reporting_snapshots
reporting_dashboards
reporting_widgets
reporting_saved_views
reporting_reports
reporting_report_runs
reporting_exports

audit_event_definitions
audit_events
audit_exports

observability_services
observability_metrics
observability_logs_index
observability_traces_index
observability_health_checks
observability_alert_rules
observability_alerts
observability_incidents
observability_incident_events
observability_slo_definitions
observability_slo_measurements
data_quality_checks
data_quality_failures
```

---

# 89. Champs — Analytics Event

```text
id
event_code
event_version
domain
occurred_at
ingested_at
source_id
trace_id
subject_pseudonym
organization_id
dimensions
measures
status
```

---

# 90. Champs — Audit Event

```text
id
actor_account_id
actor_space_id
organization_id
capability_code
action_code
target_type
target_id
before_snapshot
after_snapshot
reason
session_id
device_id
trace_id
created_at
```

---

# 91. Champs — Alert Rule

```text
id
code
service
metric
operator
threshold
window
severity
status
version
notification_route
```

---

# 92. Champs — Incident

```text
id
title
severity
status
service
module
impact
started_at
detected_at
resolved_at
incident_manager
root_cause
```

---

# 93. Événements métier

```text
AnalyticsEventAccepted
AnalyticsEventRejected
ReportingAggregateUpdated
ReportingSnapshotCreated
ReportRequested
ReportReady
ReportFailed
ExportCreated
ExportExpired

AuditEventRecorded
AuditExportCreated

ServiceHealthChanged
AlertTriggered
AlertAcknowledged
AlertResolved
IncidentCreated
IncidentUpdated
IncidentResolved
DataQualityCheckFailed
ReconciliationMismatchDetected
```

---

# 94. Capacités

## Utilisateur

```text
reporting.self.view
reporting.self.export
audit.security.self.view
```

## Annonceur

```text
advertiser.reporting.view
advertiser.reporting.export
advertiser.reporting.schedule
```

## Professionnel

```text
professional.reporting.view
professional.reporting.export
organization.audit.view
```

## Administration

```text
reporting.global.view
reporting.financial.view
reporting.exports.manage
audit.global.view
observability.view
observability.alerts.manage
observability.incidents.manage
```

---

# 95. Sécurité

- permissions ;
- périmètres ;
- MFA ;
- agrégation ;
- pseudonymisation ;
- exports protégés ;
- secrets masqués ;
- logs nettoyés ;
- audit append-only ;
- accès Santé séparé ;
- aucune identité publicitaire ;
- aucun solde modifiable depuis le reporting.

---

# 96. Performance

- pré-agrégation ;
- cache ;
- pagination ;
- partitionnement ;
- index ;
- stockage analytique séparé ;
- jobs asynchrones ;
- compression ;
- rétention ;
- échantillonnage des logs non critiques ;
- limites d’export.

---

# 97. Résilience

- ingestion idempotente ;
- retry ;
- dead-letter queue ;
- reprise ;
- recalcul ;
- snapshots ;
- réplication ;
- sauvegarde ;
- tolérance au retard ;
- affichage de fraîcheur.

---

# 98. Accessibilité

Les tableaux de bord doivent :

- fonctionner au clavier ;
- proposer des tableaux alternatifs aux graphiques ;
- ne pas dépendre uniquement des couleurs ;
- fournir des libellés ;
- supporter le lecteur d’écran ;
- conserver une lecture simple sur mobile.

---

# 99. Tests événements analytiques

- version ;
- validation ;
- idempotence ;
- doublon ;
- retard ;
- champ manquant ;
- dead letter ;
- reprise ;
- agrégation.

---

# 100. Tests reporting économique

- partage 50/50 ;
- budgets ;
- réservations ;
- captures ;
- releases ;
- remboursements ;
- devises ;
- cohérence Grand Livre.

---

# 101. Tests Studio Annonceur

- portée agrégée ;
- attention ;
- budget ;
- classe ;
- zone ;
- absence d’identité ;
- export ;
- comparaison.

---

# 102. Tests audit

- événement append-only ;
- acteur ;
- espace ;
- capacité ;
- avant/après ;
- trace ;
- recherche ;
- export ;
- impossibilité de modifier.

---

# 103. Tests observabilité

- métrique ;
- log structuré ;
- trace distribuée ;
- health check ;
- alerte ;
- déduplication ;
- incident ;
- résolution ;
- mode dégradé.

---

# 104. Tests qualité des données

- événement manquant ;
- doublon ;
- montant incohérent ;
- rupture de série ;
- retard ;
- divergence Ledger ;
- notification ;
- absence de correction automatique de la source.

---

# 105. Tests responsive

## Utilisateur

- synthèse mobile ;
- Wallet ;
- Fonds ;
- Live ;
- interface mobile sur desktop.

## Annonceur et professionnels

- dashboard desktop ;
- filtres ;
- tableaux ;
- exports ;
- mobile synthétique ;
- tablette.

## Administration

- desktop complet ;
- incidents ;
- métriques ;
- traces ;
- mobile urgence.

---

# 106. Captures obligatoires

1. dashboard fondateur ;
2. vue économique ;
3. vue pays ;
4. reporting utilisateur mobile ;
5. reporting Studio Annonceur ;
6. comparaison de campagnes ;
7. reporting partenaire ;
8. reporting institutionnel ;
9. reporting Santé agrégé ;
10. contrôle Grand Livre ;
11. dashboard Feed ;
12. dashboard Live ;
13. audit ;
14. recherche d’audit ;
15. services ;
16. métriques ;
17. trace distribuée ;
18. file de jobs ;
19. incident ;
20. rapport programmé.

---

# 107. Critères d’acceptation

Le module est accepté lorsque :

1. les événements analytiques sont versionnés ;
2. les sources de vérité sont déclarées ;
3. les agrégats sont séparés des tables métier ;
4. le fondateur dispose d’un dashboard global ;
5. les utilisateurs voient des synthèses simples ;
6. les annonceurs voient des résultats agrégés ;
7. les partenaires et institutions ont leurs rapports ;
8. Santé reste agrégé et séparé ;
9. le Grand Livre contrôle l’équilibre ;
10. les audits sont append-only ;
11. les logs sont structurés ;
12. les métriques existent ;
13. les traces distribuées existent ;
14. les health checks existent ;
15. les alertes et incidents sont gérés ;
16. les files et outbox sont observables ;
17. les écarts analytiques sont détectés ;
18. les exports sont protégés ;
19. les interfaces respectent la doctrine responsive ;
20. les tests critiques passent.

---

# 108. Ordre d’implémentation

## Phase 1 — Registre d’événements

- définitions ;
- versions ;
- ingestion ;
- validation ;
- idempotence.

## Phase 2 — Audit métier

- événements ;
- stockage append-only ;
- recherche ;
- exports ;
- permissions.

## Phase 3 — Agrégats économiques

- campagnes ;
- Wallet ;
- Grand Livre ;
- abonnements ;
- revenus.

## Phase 4 — Dashboards métier

- utilisateur ;
- annonceur ;
- partenaire ;
- institution ;
- Santé.

## Phase 5 — Observabilité technique

- logs ;
- métriques ;
- traces ;
- health checks ;
- files ;
- outbox.

## Phase 6 — Alertes et incidents

- règles ;
- routage ;
- déduplication ;
- timelines ;
- rapports.

## Phase 7 — Qualité et réconciliation

- contrôles ;
- divergences ;
- fraîcheur ;
- reprises.

## Phase 8 — Rapports et exports

- génération ;
- programmation ;
- sécurité ;
- expiration.

## Phase 9 — Administration

- widgets ;
- vues ;
- SLI/SLO ;
- intégrations ;
- configuration.

## Phase 10 — Stabilisation

- performance ;
- résilience ;
- accessibilité ;
- responsive ;
- tests ;
- captures.

---

# 109. Première verticale à livrer

```text
Annonceur finance une campagne
→ budget réservé
→ campagne approuvée
→ publicité livrée
→ attention qualifiée
→ Grand Livre
→ Wallet utilisateur crédité
→ budget annonceur consommé
→ reporting Studio mis à jour
→ dashboard fondateur mis à jour
→ audit complet
```

Deuxième verticale :

```text
Crédit Wallet anormalement lent
→ métrique dépasse le seuil
→ alerte
→ incident créé
→ trace identifie la file
→ worker corrigé
→ événements rejoués
→ Wallets crédités
→ incident résolu
→ rapport
```

Troisième verticale :

```text
Correction financière exceptionnelle
→ demande
→ approbation
→ transaction compensatoire
→ audit append-only
→ reporting financier recalculé
→ export de preuve
```

---

# 110. Directive pour Claude Code

1. lire toutes les notes de domaine ;
2. auditer le nouveau dépôt ;
3. définir les sources de vérité ;
4. créer un registre d’événements versionnés ;
5. séparer les données transactionnelles et analytiques ;
6. construire l’audit append-only ;
7. construire les agrégats économiques depuis les événements réels ;
8. ne jamais exposer d’identités aux annonceurs ;
9. instrumenter logs, métriques, traces et health checks ;
10. rendre les files, webhooks et outbox observables ;
11. créer alertes, incidents et rapports ;
12. fournir exports protégés ;
13. fournir migrations, API, tests et captures ;
14. ne pas introduire de couche doctrinale ou de gouvernance bloquante.

---

# 111. Décision finale

Le module doit permettre à Wasplex de :

```text
mesurer
→ comprendre
→ expliquer
→ détecter
→ diagnostiquer
→ agir
→ vérifier
```

> **Le reporting montre la performance, l’audit prouve les actions et l’observabilité révèle l’état réel du système. Ensemble, ils donnent au fondateur et aux équipes les moyens de piloter Wasplex sans modifier les vérités métier ni exposer les données inutiles.**
