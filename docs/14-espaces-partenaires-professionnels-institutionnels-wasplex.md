# WASPLEX — ESPACES PARTENAIRES, PROFESSIONNELS & INSTITUTIONNELS

**Fichier cible recommandé :** `docs/13-espaces-professionnels/00-espaces-partenaires-professionnels-institutionnels-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** ensemble d’espaces métiers rattachés au Compte universel et aux organisations vérifiées  
**Interfaces officielles :** desktop complet et stratégique + mobile opérationnel de terrain  
**Dépendances :** Compte universel, Organisations, Rôles & Capacités, Wallet, Fonds, Alertes, Santé, Carte Wasplex, Partenaires, Live, Notifications, Administration centrale  
**Principe central :** chaque professionnel ou institution utilise son propre espace métier, avec les données minimales nécessaires, des capacités explicites, un historique complet et des parcours adaptés au bureau comme au terrain  
**Important :** aucun espace professionnel ne doit devenir un accès général à toutes les données Wasplex

---

# 1. Objet

Ce document définit les espaces métiers Wasplex destinés aux :

- partenaires commerciaux ;
- commerçants et points de vente ;
- prestataires Fonds ;
- institutions publiques ;
- services de sécurité ;
- établissements de Santé ;
- professionnels Santé ;
- agents de terrain ;
- opérateurs financiers ;
- modérateurs ;
- équipes opérationnelles Wasplex.

Il précise l’architecture commune, les interfaces desktop et mobile, les dossiers, les équipes, les permissions, les opérations, les échanges sécurisés, les preuves, les API, les données, les événements et les tests.

---

# 2. Vision produit

```text
Un professionnel possède un compte personnel
→ il rejoint une organisation vérifiée
→ il reçoit des capacités précises
→ il ouvre son espace métier
→ il voit uniquement ses dossiers et opérations
→ il agit depuis le bureau ou le terrain
→ chaque action est confirmée et auditée
```

---

# 3. Doctrine d’interface

## 3.1. Desktop

Le desktop est prioritaire pour :

- tableaux de bord ;
- dossiers ;
- recherches ;
- équipes ;
- statistiques ;
- rapprochements ;
- validations ;
- exports ;
- cartographie ;
- opérations multiples ;
- rapports.

## 3.2. Mobile

Le mobile est complet pour :

- scan QR ;
- consultation d’un dossier ;
- prise de photo ;
- preuve ;
- confirmation ;
- changement de statut ;
- restitution ;
- signature ;
- communication sécurisée ;
- intervention ;
- vérification de Carte ;
- encaissement ou opération partenaire.

## 3.3. Tablette

La tablette doit être pleinement supportée pour :

- guichets ;
- centres opérationnels ;
- établissements Santé ;
- points de vente ;
- agents de supervision.

---

# 4. Architecture commune

```text
Compte personnel
→ organisation
→ espace métier
→ rôle
→ capacités
→ territoire
→ dossiers
→ opérations
→ audit
```

Fondations communes :

- compte nominatif ;
- organisation vérifiée ;
- membre identifié ;
- capacités ;
- périmètre ;
- sessions ;
- MFA ;
- audit ;
- notifications ;
- documents ;
- historique.

---

# 5. Types d’espaces

```text
partner
merchant
service_provider
security_institution
healthcare_institution
health_professional
financial_operator
field_agent
moderation_team
wasplex_operations
```

De nouveaux types peuvent être ajoutés sans créer un nouveau système de comptes.

---

# 6. Organisation vérifiée

Une organisation contient :

- identité légale ;
- nom commercial ;
- type ;
- pays ;
- territoire ;
- représentant ;
- documents ;
- statut ;
- contrat ;
- membres ;
- espaces ;
- points de service ;
- intégrations ;
- audit.

États :

```text
draft
pending_verification
under_review
verified
active
restricted
suspended
closed
```

---

# 7. Membres nominatifs

Chaque membre utilise son propre compte.

Interdictions :

- compte partagé ;
- identifiant générique ;
- mot de passe commun ;
- action anonyme ;
- attribution implicite de toutes les capacités.

---

# 8. Rôles et capacités

Exemples de rôles :

```text
organization_owner
organization_admin
operations_manager
supervisor
agent
reviewer
finance_manager
health_professional
security_officer
partner_cashier
auditor
read_only
```

Le rôle est un regroupement. La capacité décide de l’action réelle.

---

# 9. Périmètres

Une capacité peut être limitée par :

- organisation ;
- établissement ;
- point de vente ;
- territoire ;
- catégorie ;
- programme ;
- dossier ;
- pays ;
- durée ;
- horaire ;
- montant.

---

# 10. Changement d’espace

Le changement d’espace :

- recalcule les permissions ;
- adapte la navigation ;
- affiche l’organisation active ;
- journalise les espaces sensibles ;
- empêche toute fuite inter-espace.

---

# 11. Tableau de bord commun

Chaque espace professionnel affiche :

- organisation ;
- statut ;
- tâches en attente ;
- dossiers récents ;
- opérations ;
- notifications ;
- incidents ;
- équipe ;
- indicateurs ;
- raccourcis ;
- état des intégrations.

Navigation commune :

```text
Vue d’ensemble
Dossiers
Opérations
Équipe
Documents
Messages
Rapports
Paramètres
Audit
```

---

# 12. Espace partenaire commercial

Fonctions :

- gérer les offres ;
- gérer les points de vente ;
- vérifier une Carte Wasplex ;
- créer une opération ;
- confirmer une vente ;
- appliquer une réduction ;
- déclencher un cashback ;
- traiter un remboursement ;
- consulter les règlements ;
- gérer l’équipe ;
- suivre les résultats.

---

# 13. Tableau de bord partenaire

Indicateurs :

- opérations du jour ;
- montant total ;
- opérations confirmées ;
- avantages accordés ;
- cashback en attente ;
- règlements ;
- remboursements ;
- anomalies ;
- points de vente actifs.

---

# 14. Parcours partenaire mobile

```text
Ouvrir scanner
→ scanner Carte ou QR
→ vérifier éligibilité
→ saisir montant
→ choisir offre
→ ajouter preuve
→ confirmer
→ reçu
```

Le partenaire ne voit jamais le Wallet complet de l’utilisateur.

---

# 15. Parcours partenaire desktop

- catalogue d’offres ;
- gestion multi-points de vente ;
- opérations ;
- équipes ;
- règlements ;
- rapports ;
- exports ;
- litiges ;
- remboursements ;
- audit.

---

# 16. Points de vente

Chaque point de vente possède :

- nom ;
- adresse ;
- zone ;
- coordonnées ;
- responsable ;
- équipe ;
- terminal ;
- horaires ;
- statut ;
- offres ;
- opérations.

---

# 17. Offres partenaires

Une offre définit :

- nom ;
- type ;
- avantage ;
- conditions ;
- dates ;
- points de vente ;
- cartes éligibles ;
- plans éligibles ;
- plafond ;
- budget ;
- preuve ;
- statut.

---

# 18. Opération partenaire

Cycle :

```text
created
pending_proof
submitted
confirmed
settlement_pending
settled
benefit_pending
benefit_credited
rejected
cancelled
refunded
disputed
```

---

# 19. Espace prestataire Fonds

Destiné à une clinique, une école, un fournisseur, un artisan, un bailleur ou autre prestataire vérifié.

Fonctions :

- recevoir une autorisation ;
- consulter le périmètre nécessaire ;
- soumettre un devis ;
- confirmer une prestation ;
- recevoir un paiement direct ;
- ajouter une preuve ;
- traiter un reliquat ;
- consulter le règlement.

---

# 20. Parcours prestataire Fonds

```text
Autorisation reçue
→ dossier minimal
→ devis ou facture
→ validation
→ prestation
→ preuve
→ paiement direct
→ reçu
```

Le prestataire ne voit pas l’ensemble du dossier social ou financier du bénéficiaire.

---

# 21. Espace institution de sécurité

Destiné aux services autorisés de police, gendarmerie, sécurité civile et institutions apparentées.

Fonctions :

- recevoir des signalements ;
- consulter les projections institutionnelles ;
- accepter ou refuser une prise en charge ;
- attribuer un dossier ;
- ajouter des événements ;
- changer un statut ;
- demander des informations ;
- transférer ;
- clôturer ;
- produire un rapport ;
- communiquer de manière sécurisée.

---

# 22. Tableau de bord institutionnel

- dossiers entrants ;
- urgences ;
- dossiers assignés ;
- transferts ;
- personnes ;
- véhicules ;
- objets ;
- correspondances ;
- territoires ;
- agents ;
- délais ;
- incidents ;
- statistiques agrégées.

---

# 23. Dossier institutionnel

Un dossier contient seulement la projection nécessaire :

- référence ;
- type ;
- priorité ;
- territoire ;
- faits ;
- pièces autorisées ;
- statut ;
- historique ;
- contacts relayés ;
- institution source ;
- institution destinataire.

Une déclaration Wasplex ne devient pas automatiquement une plainte légale.

---

# 24. Parcours agent mobile

```text
Mission reçue
→ ouvrir dossier
→ consulter le nécessaire
→ intervenir sur le terrain
→ ajouter preuve
→ scanner Carte ou QR
→ changer statut
→ transmettre
→ clôturer ou transférer
```

---

# 25. Parcours institution desktop

- centre opérationnel ;
- carte ;
- files ;
- équipes ;
- affectation ;
- recherche ;
- rapports ;
- transferts ;
- coordination ;
- audit.

---

# 26. Transfert institutionnel

Un transfert précise :

- source ;
- destination ;
- motif ;
- périmètre ;
- pièces ;
- statut ;
- date ;
- acteur ;
- accusé de réception.

Aucune copie libre de la base complète.

---

# 27. Espace établissement Santé

Fonctions :

- gérer les professionnels ;
- vérifier les capacités ;
- consulter les accès autorisés ;
- recevoir des demandes ;
- gérer les capsules d’urgence ;
- traiter les incidents ;
- consulter les audits ;
- gérer les points de service.

---

# 28. Espace professionnel Santé

Le professionnel peut :

- consulter un patient autorisé ;
- demander un accès ;
- utiliser une capsule d’urgence ;
- ajouter une note dans son périmètre ;
- terminer un accès ;
- consulter son historique ;
- signaler un incident.

---

# 29. Accès Santé normal

```text
patient ou représentant autorise
→ professionnel vérifié
→ périmètre défini
→ durée
→ accès
→ expiration
→ audit
```

---

# 30. Accès Santé d’urgence

```text
Carte ou identité
→ professionnel vérifié
→ justification urgence
→ MFA récente
→ capsule minimale
→ expiration rapide
→ audit
```

Le professionnel n’obtient pas automatiquement le dossier complet.

---

# 31. Tableau de bord Santé desktop

- patients autorisés ;
- demandes ;
- accès actifs ;
- accès d’urgence ;
- professionnels ;
- établissements ;
- incidents ;
- audit ;
- statistiques ;
- documents.

---

# 32. Mobile Santé

- scanner une Carte ;
- rechercher un patient autorisé ;
- consulter la capsule ;
- confirmer une intervention ;
- contacter un représentant ;
- clôturer l’accès ;
- signaler un incident.

---

# 33. Espace opérateur financier

Destiné aux équipes autorisées pour :

- dépôts ;
- retraits ;
- rapprochements ;
- preuves ;
- incidents ;
- confirmations ;
- rejets ;
- rapports.

L’opérateur ne modifie jamais directement un solde.

---

# 34. Dépôt supervisé

```text
intention de dépôt
→ preuve
→ vérification
→ approbation
→ Grand Livre
→ Wallet
→ reçu
```

---

# 35. Retrait supervisé

```text
demande
→ réservation
→ vérification
→ paiement externe
→ confirmation
→ Grand Livre
→ clôture
```

En cas d’échec :

- libération ;
- compensation ;
- notification ;
- audit.

---

# 36. Espace agent de terrain

Un agent de terrain peut travailler pour un partenaire, une institution, Santé, Fonds ou Wasplex.

Fonctions :

- missions ;
- navigation ;
- scan ;
- photo ;
- preuve ;
- signature ;
- statuts ;
- messages ;
- mode hors ligne limité ;
- synchronisation.

---

# 37. Mode hors ligne limité

Autorisé pour :

- brouillon ;
- photo ;
- note ;
- formulaire ;
- signature locale ;
- statut non financier en attente.

Interdit hors ligne :

- crédit Wallet définitif ;
- paiement final ;
- accès Santé sensible définitif ;
- clôture financière ;
- cashback définitif.

---

# 38. Synchronisation terrain

Chaque action hors ligne contient :

- identifiant local ;
- horodatage ;
- appareil ;
- auteur ;
- dossier ;
- version ;
- preuve ;
- statut de synchronisation.

Le serveur tranche les conflits.

---

# 39. Espace modération

Destiné aux équipes chargées des :

- campagnes ;
- Feed ;
- Live ;
- commentaires ;
- Alertes ;
- partenaires ;
- profils publics ;
- signalements.

Décisions :

```text
approve
request_changes
restrict
remove
suspend
escalate
restore
```

Chaque décision possède un motif.

---

# 40. Espace opérations Wasplex

Destiné aux équipes internes de :

- support ;
- finance ;
- revue ;
- partenaires ;
- Alertes ;
- Santé ;
- Live ;
- incidents.

Il reste soumis aux capacités et à l’audit.

---

# 41. Architecture des dossiers

```text
id
domain
type
organization_id
owner_id
assigned_to
priority
status
territory
opened_at
closed_at
```

Chaque domaine conserve ses données propres.

---

# 42. Machines d’états

Chaque transition vérifie :

- statut actuel ;
- capacité ;
- contexte ;
- preuve ;
- conditions ;
- éventuelle approbation ;
- événement.

Aucun agent ne saisit librement un statut arbitraire.

---

# 43. Affectation et tâches

Un dossier peut être :

- non assigné ;
- assigné à une équipe ;
- assigné à un agent ;
- réassigné ;
- transféré ;
- mis en attente ;
- escaladé.

Une tâche possède :

- type ;
- dossier ;
- assignation ;
- priorité ;
- échéance ;
- statut ;
- pièces ;
- commentaire ;
- historique.

---

# 44. Communication sécurisée

Canaux :

- messages internes ;
- demandes d’information ;
- pièces ;
- notifications ;
- accusés de réception ;
- commentaires de dossier ;
- canaux institutionnels.

Les échanges sensibles restent séparés des commentaires publics.

---

# 45. Contacts relayés

Wasplex peut :

- relayer un message ;
- masquer les coordonnées ;
- ouvrir un canal temporaire ;
- fermer le canal à la clôture.

---

# 46. Pièces jointes

Types :

- image ;
- PDF ;
- vidéo ;
- audio ;
- document ;
- preuve ;
- facture ;
- rapport.

Contrôles :

- taille ;
- format ;
- virus ;
- accès ;
- durée ;
- chiffrement ;
- expiration ;
- journal.

---

# 47. Signatures et confirmations

Une action peut exiger :

- code ;
- OTP ;
- signature tactile ;
- scan ;
- double confirmation ;
- biométrie locale ;
- MFA ;
- approbation superviseur.

---

# 48. Preuves

Une preuve peut être :

- référence externe ;
- photo ;
- reçu ;
- facture ;
- signature ;
- code ;
- QR ;
- géolocalisation autorisée ;
- document ;
- événement système.

Chaque preuve est liée à une finalité.

---

# 49. Géolocalisation

Usages autorisables :

- point de vente ;
- mission terrain ;
- restitution ;
- établissement ;
- zone d’intervention.

Elle doit être nécessaire, limitée et séparée du ciblage publicitaire.

---

# 50. Notifications métiers

Exemples :

- nouveau dossier ;
- tâche assignée ;
- urgence ;
- document reçu ;
- action requise ;
- paiement confirmé ;
- remboursement ;
- accès Santé ;
- restitution ;
- transfert ;
- suspension ;
- incident.

---

# 51. Gestion d’équipe

Fonctions :

- inviter ;
- accepter ;
- affecter ;
- attribuer des capacités ;
- limiter par territoire ;
- suspendre ;
- révoquer ;
- remplacer ;
- auditer.

---

# 52. Validation interne

Une organisation peut imposer :

```text
agent
→ superviseur
→ finance
→ exécution
```

Le workflow est configurable par opération.

---

# 53. Séparation des fonctions

Exemples :

- le créateur d’un paiement ne l’approuve pas ;
- le vérificateur d’un partenaire ne le règle pas ;
- l’agent Santé ne modifie pas les droits ;
- le modérateur ne supprime pas l’audit.

---

# 54. Rapports et exports

Rapports possibles :

- activité ;
- opérations ;
- délais ;
- équipe ;
- finances ;
- incidents ;
- territoires ;
- qualité ;
- audit.

Exports :

- CSV ;
- PDF ;
- JSON ;
- impression.

Les exports sensibles sont autorisés, limités, journalisés et temporaires.

---

# 55. Recherche et cartographie

La recherche respecte le périmètre autorisé.

Critères :

- dossier ;
- référence ;
- utilisateur masqué ;
- Carte ;
- opération ;
- point de vente ;
- membre ;
- statut ;
- date ;
- territoire.

Cartographie possible pour :

- points de vente ;
- établissements ;
- missions ;
- zones ;
- incidents.

---

# 56. Audit

Chaque action sensible conserve :

```text
actor_account_id
organization_id
space_id
capability
action
target_type
target_id
before
after
reason
device
session_id
trace_id
created_at
```

---

# 57. Administration centrale

L’administration peut :

- vérifier l’organisation ;
- suspendre ;
- restaurer ;
- gérer les capacités ;
- voir les opérations ;
- traiter un incident ;
- auditer ;
- intervenir exceptionnellement ;
- configurer les workflows ;
- gérer les intégrations.

---

# 58. Capacités partenaires

```text
partner.offer.view
partner.offer.manage
partner.operation.create
partner.operation.confirm
partner.operation.refund
partner.settlement.view
partner.team.manage
```

---

# 59. Capacités prestataire Fonds

```text
fonds.provider.case.view
fonds.provider.quote.submit
fonds.provider.service.confirm
fonds.provider.payment.view
fonds.provider.proof.upload
```

---

# 60. Capacités institutionnelles

```text
alerts.institution.case.receive
alerts.institution.case.view
alerts.institution.case.assign
alerts.institution.case.update
alerts.institution.case.transfer
alerts.institution.case.close
alerts.institution.report.export
```

---

# 61. Capacités Santé

```text
health.patient.authorized.read
health.consent.request
health.emergency.request
health.emergency.read
health.access.close
health.audit.self.view
```

---

# 62. Capacités financières

```text
wallet.deposit.review
wallet.deposit.approve
wallet.withdrawal.review
wallet.withdrawal.confirm
wallet.reconciliation.view
wallet.reconciliation.manage
```

---

# 63. API commune

```text
GET    /api/professional/spaces
POST   /api/professional/spaces/{id}/switch
GET    /api/professional/dashboard
GET    /api/professional/tasks
GET    /api/professional/notifications
GET    /api/professional/audit
```

---

# 64. API organisations

```text
GET    /api/organizations/{id}
PATCH  /api/organizations/{id}
GET    /api/organizations/{id}/members
POST   /api/organizations/{id}/invitations
PATCH  /api/organizations/{id}/members/{member}
DELETE /api/organizations/{id}/members/{member}
```

---

# 65. API partenaire

```text
GET    /api/partner/dashboard
GET    /api/partner/offers
POST   /api/partner/offers
GET    /api/partner/operations
POST   /api/partner/operations
POST   /api/partner/operations/{id}/confirm
POST   /api/partner/operations/{id}/refund
GET    /api/partner/settlements
```

---

# 66. API prestataire Fonds

```text
GET    /api/fonds-provider/cases
GET    /api/fonds-provider/cases/{id}
POST   /api/fonds-provider/cases/{id}/quotes
POST   /api/fonds-provider/cases/{id}/proofs
POST   /api/fonds-provider/cases/{id}/confirm-service
GET    /api/fonds-provider/payments
```

---

# 67. API institutionnelle

```text
GET    /api/institution/cases
GET    /api/institution/cases/{id}
POST   /api/institution/cases/{id}/accept
POST   /api/institution/cases/{id}/assign
POST   /api/institution/cases/{id}/events
POST   /api/institution/cases/{id}/transfer
POST   /api/institution/cases/{id}/close
```

---

# 68. API Santé

```text
GET    /api/health-professional/dashboard
GET    /api/health-professional/patients/{id}
POST   /api/health-professional/access-requests
POST   /api/health-professional/emergency-access
POST   /api/health-professional/access/{id}/close
GET    /api/health-professional/audit
```

---

# 69. API opérateur financier

```text
GET    /api/financial-operator/deposits
POST   /api/financial-operator/deposits/{id}/review
POST   /api/financial-operator/deposits/{id}/approve
POST   /api/financial-operator/deposits/{id}/reject

GET    /api/financial-operator/withdrawals
POST   /api/financial-operator/withdrawals/{id}/confirm
POST   /api/financial-operator/withdrawals/{id}/fail
```

---

# 70. Événements métier

```text
ProfessionalSpaceActivated
OrganizationVerified
OrganizationRestricted
OrganizationMemberInvited
OrganizationCapabilityGranted
OrganizationCapabilityRevoked

PartnerOperationCreated
PartnerOperationConfirmed
PartnerOperationRefunded
PartnerSettlementCompleted

FundsProviderQuoteSubmitted
FundsProviderServiceConfirmed
FundsProviderPaymentCompleted

InstitutionCaseReceived
InstitutionCaseAssigned
InstitutionCaseTransferred
InstitutionCaseClosed

HealthAccessRequested
HealthEmergencyAccessGranted
HealthAccessClosed

FinancialDepositReviewed
FinancialDepositApproved
FinancialWithdrawalConfirmed

FieldTaskAssigned
FieldProofUploaded
ProfessionalMessageSent
ProfessionalIncidentReported
```

---

# 71. Modèle de données

Entités recommandées :

```text
professional_spaces
professional_space_types
professional_space_memberships
professional_capability_grants
professional_scope_rules

organizations
organization_locations
organization_members
organization_invitations
organization_documents
organization_verifications

professional_dashboards
professional_tasks
professional_task_assignments
professional_messages
professional_message_threads
professional_attachments
professional_proofs
professional_signatures
professional_notifications
professional_incidents
professional_audit_events

partner_locations
partner_offers
partner_operations
partner_settlements

funds_provider_cases
funds_provider_quotes
funds_provider_proofs

institution_cases
institution_case_assignments
institution_case_events
institution_case_transfers

health_institutions
health_professionals
health_access_requests
health_emergency_accesses

financial_operator_reviews
field_agent_sessions
field_offline_actions
```

---

# 72. Sécurité

- MFA ;
- sessions nominatives ;
- appareils ;
- capacités ;
- périmètres ;
- séparation des espaces ;
- fichiers protégés ;
- audit ;
- rate limiting ;
- anti-replay ;
- idempotence ;
- aucune modification directe du Wallet ;
- aucun accès Santé global ;
- aucune exportation massive sans capacité.

---

# 73. Mode mobile sécurisé

Pour les actions sensibles :

- session courte ;
- appareil vérifié ;
- biométrie locale possible ;
- MFA récente ;
- confirmation ;
- écran de contexte ;
- blocage en cas de risque élevé selon politique.

---

# 74. Performance et accessibilité

Performance :

- tableaux paginés ;
- filtres ;
- recherche indexée ;
- synchronisation différentielle ;
- cache des référentiels ;
- pièces chargées à la demande ;
- mode terrain léger ;
- traitement asynchrone ;
- notifications temps réel.

Accessibilité :

- clavier ;
- lecteur d’écran ;
- contraste ;
- formulaires clairs ;
- alternative au scan ;
- indicateurs non fondés uniquement sur la couleur.

---

# 75. Tests communs

- activation espace ;
- changement d’espace ;
- organisation ;
- invitation ;
- capacité ;
- périmètre ;
- suspension ;
- audit ;
- mobile ;
- desktop ;
- tablette.

---

# 76. Tests partenaire

- offre ;
- scan ;
- opération ;
- preuve ;
- confirmation ;
- cashback ;
- remboursement ;
- règlement ;
- point de vente ;
- équipe.

---

# 77. Tests Fonds prestataire

- autorisation ;
- dossier minimal ;
- devis ;
- preuve ;
- confirmation ;
- paiement direct ;
- refus d’accès aux contributions des autres.

---

# 78. Tests institutionnels

- dossier entrant ;
- affectation ;
- transfert ;
- événement ;
- clôture ;
- territoire ;
- mobile agent ;
- aucune base universelle ;
- aucune plainte automatique.

---

# 79. Tests Santé

- professionnel vérifié ;
- accès normal ;
- accès d’urgence ;
- capsule minimale ;
- expiration ;
- audit ;
- refus sans capacité ;
- aucun dossier complet public.

---

# 80. Tests financiers et terrain

Financier :

- dépôt supervisé ;
- retrait ;
- idempotence ;
- Grand Livre ;
- aucune écriture directe ;
- double validation ;
- compensation.

Terrain :

- scan ;
- photo ;
- signature ;
- hors ligne ;
- synchronisation ;
- conflit ;
- action financière bloquée hors ligne.

---

# 81. Tests responsive

## Mobile

- 320 px ;
- 360 px ;
- 390 px ;
- missions ;
- scan ;
- dossier ;
- preuve ;
- statut.

## Tablette

- 768 px ;
- 1024 px ;
- guichet ;
- dossier ;
- carte ;
- équipe.

## Desktop

- 1280 px ;
- 1440 px ;
- tableaux ;
- recherche ;
- rapports ;
- multi-dossiers ;
- équipe.

---

# 82. Captures obligatoires

1. sélecteur d’espace ;
2. dashboard partenaire desktop ;
3. opération partenaire mobile ;
4. point de vente ;
5. espace prestataire Fonds ;
6. dossier institutionnel desktop ;
7. mission agent mobile ;
8. transfert institutionnel ;
9. dashboard Santé ;
10. accès d’urgence mobile ;
11. opérateur financier ;
12. dépôt supervisé ;
13. équipe ;
14. capacités ;
15. audit ;
16. mode hors ligne ;
17. tablette ;
18. incident.

---

# 83. Critères d’acceptation

Le module est accepté lorsque :

1. les espaces utilisent le Compte universel ;
2. les membres sont nominatifs ;
3. les organisations sont vérifiées ;
4. le desktop est complet ;
5. le mobile terrain est complet ;
6. les permissions sont explicites ;
7. les périmètres sont respectés ;
8. les partenaires gèrent leurs opérations ;
9. les prestataires Fonds voient uniquement le nécessaire ;
10. les institutions traitent et transfèrent des dossiers ;
11. les transferts sont auditables ;
12. Santé reste séparé ;
13. les accès Santé sont temporaires et contrôlés ;
14. les opérateurs financiers ne modifient pas les soldes ;
15. le mode hors ligne est limité ;
16. les actions sensibles sont confirmées ;
17. l’administration centrale supervise ;
18. aucune fuite inter-espace n’existe ;
19. les tests responsive passent ;
20. les tests critiques sont verts.

---

# 84. Ordre d’implémentation

## Phase 1 — Socle commun

- types d’espaces ;
- organisations ;
- membres ;
- capacités ;
- navigation ;
- responsive.

## Phase 2 — Tâches et dossiers

- dossiers ;
- affectations ;
- statuts ;
- pièces ;
- messages ;
- audit.

## Phase 3 — Partenaires

- offres ;
- points de vente ;
- opérations ;
- règlements ;
- scan mobile.

## Phase 4 — Prestataires Fonds

- dossiers ;
- devis ;
- preuves ;
- paiements.

## Phase 5 — Institutionnel

- files ;
- affectation ;
- transferts ;
- terrain ;
- rapports.

## Phase 6 — Santé

- établissements ;
- professionnels ;
- accès ;
- urgence ;
- audit.

## Phase 7 — Opérations financières

- dépôts ;
- retraits ;
- rapprochement ;
- incidents.

## Phase 8 — Terrain

- missions ;
- hors ligne ;
- synchronisation ;
- signature ;
- géolocalisation.

## Phase 9 — Modération et opérations Wasplex

- files ;
- décisions ;
- escalades ;
- rapports.

## Phase 10 — Stabilisation

- sécurité ;
- performance ;
- accessibilité ;
- tests ;
- captures.

---

# 85. Première verticale à livrer

```text
Partenaire vérifié
→ responsable invite un agent
→ capacité de caisse accordée
→ agent ouvre l’application mobile
→ scanne une Carte Wasplex
→ saisit une opération
→ ajoute une preuve
→ confirme
→ avantage calculé
→ Wallet utilisateur crédité
→ opération visible sur desktop
→ audit complet
```

Deuxième verticale :

```text
Institution reçoit une Alerte
→ superviseur assigne un agent
→ agent consulte sur mobile
→ ajoute une preuve
→ change le statut
→ transfère si nécessaire
→ institution clôture
→ citoyen voit le statut communicable
```

---

# 86. Directive pour Claude Code

1. lire Compte universel, Carte, Fonds, Alertes, Santé, Wallet et Administration ;
2. auditer le nouveau dépôt ;
3. construire un socle commun d’espaces professionnels ;
4. ne pas créer un back-office unique donnant accès à tout ;
5. concevoir desktop et mobile comme deux compositions complètes ;
6. utiliser des comptes nominatifs ;
7. appliquer les capacités et périmètres ;
8. séparer strictement Santé, Alertes, Fonds et Finance ;
9. interdire les modifications directes de solde ;
10. construire les parcours terrain ;
11. fournir migrations, API, tests et captures ;
12. ne pas introduire de règles doctrinales ou de gouvernance bloquante.

---

# 87. Décision finale

Les espaces professionnels Wasplex doivent être :

```text
puissants sur desktop
rapides sur mobile
adaptés au terrain
limités par capacité
séparés par domaine
traçables
```

> **Chaque organisation dispose d’un espace métier complet, mais chaque professionnel ne voit et ne fait que ce qui est nécessaire à sa mission, dans son périmètre, avec une preuve et un audit.**
