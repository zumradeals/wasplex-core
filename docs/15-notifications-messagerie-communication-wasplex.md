# WASPLEX — NOTIFICATIONS, MESSAGERIE & COMMUNICATION SÉCURISÉE

**Fichier cible recommandé :** `docs/14-communication/00-notifications-messagerie-communication-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module transversal de communication, notification, conversation, relais et suivi  
**Interfaces officielles :**
- espace utilisateur : mobile-first strict, y compris sur desktop ;
- espaces annonceur, partenaire, professionnel et institutionnel : desktop complet + mobile complet ;
- administration : desktop-first, mobile limité aux urgences et à la supervision.  
**Dépendances :** Compte universel, Mon Espace, Wallet, Fonds, Alertes, Santé, Carte Wasplex, Feed, Live, Studio Annonceur, Espaces professionnels, Administration centrale  
**Principe central :** chaque message doit utiliser le bon canal, atteindre la bonne personne, révéler le minimum d’information nécessaire et rester traçable lorsqu’il concerne une opération sensible  
**Important :** la messagerie privée, les commentaires publics, les notifications système, les échanges institutionnels et le support sont des fonctions distinctes

---

# 1. Objet

Ce document définit :

- le centre de notifications ;
- les notifications dans l’application ;
- les notifications push ;
- les SMS ;
- les emails ;
- les rappels ;
- les conversations privées ;
- les conversations professionnelles ;
- les échanges institutionnels ;
- les canaux temporaires ;
- les contacts relayés ;
- les accusés de réception ;
- les pièces jointes ;
- les messages Alertes ;
- les communications Santé ;
- les notifications Wallet ;
- les notifications Fonds ;
- les notifications Carte ;
- les notifications Live ;
- les communications annonceur ;
- le support ;
- la modération ;
- les préférences ;
- les modèles de messages ;
- l’administration ;
- les API ;
- les données ;
- les événements ;
- les tests.

---

# 2. Vision produit

L’expérience cible est :

```text
Un événement important se produit
→ Wasplex choisit le canal adapté
→ le destinataire reçoit une information claire
→ il comprend l’action attendue
→ il ouvre le bon module
→ il agit
→ le statut est mis à jour
→ l’historique reste disponible
```

Pour une conversation sensible :

```text
Une partie doit contacter une autre
→ Wasplex ouvre un canal temporaire
→ les coordonnées restent masquées
→ les messages sont relayés
→ la conversation est liée au dossier
→ le canal se ferme à la résolution
```

---

# 3. Distinction entre les formes de communication

## 3.1. Notification

Message court généré par le système.

Exemples :

- Wallet crédité ;
- campagne approuvée ;
- Live programmé ;
- dossier assigné ;
- accès Santé utilisé.

## 3.2. Conversation privée

Échange direct entre comptes autorisés.

## 3.3. Conversation professionnelle

Échange lié à une organisation, un dossier ou une opération.

## 3.4. Canal institutionnel

Échange encadré entre institutions ou agents autorisés.

## 3.5. Support

Conversation entre utilisateur et équipe Wasplex.

## 3.6. Commentaire public

Interaction visible sous un contenu Feed ou Live.

Le commentaire public n’appartient pas à la messagerie privée.

---

# 4. Principes fondamentaux

Le module doit respecter ces règles :

```text
bon destinataire
bon canal
bonne finalité
minimum d’information
action claire
traçabilité adaptée
aucune confusion public/privé
```

---

# 5. Centre de notifications utilisateur

Le centre de notifications affiche :

- nouvelles notifications ;
- non lues ;
- prioritaires ;
- Wallet ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Live ;
- campagnes ;
- sécurité ;
- support ;
- système.

Actions :

- ouvrir ;
- marquer comme lu ;
- tout marquer comme lu ;
- archiver ;
- supprimer une notification non obligatoire ;
- filtrer ;
- rechercher ;
- accéder aux préférences.

---

# 6. Priorités de notification

```text
critical
high
normal
low
silent
```

## Critical

Exemples :

- accès Santé d’urgence ;
- connexion suspecte ;
- retrait critique ;
- alerte vitale ;
- incident majeur.

## High

Exemples :

- paiement échoué ;
- campagne suspendue ;
- dossier institutionnel assigné ;
- Live interrompu.

## Normal

Exemples :

- Wallet crédité ;
- campagne approuvée ;
- tâche reçue ;
- nouveau message.

## Low

Exemples :

- recommandation ;
- résumé ;
- rappel non urgent.

## Silent

- mise à jour d’état sans interruption ;
- synchronisation ;
- information secondaire.

---

# 7. Canaux de diffusion

Canaux initiaux :

```text
in_app
push
sms
email
websocket
professional_inbox
institutional_inbox
```

Un même événement peut produire plusieurs canaux selon la priorité.

---

# 8. Règles de choix du canal

Le moteur de notification vérifie :

- type d’événement ;
- priorité ;
- préférences ;
- pays ;
- disponibilité du canal ;
- appareil ;
- langue ;
- heure ;
- niveau de sécurité ;
- rôle ;
- organisation ;
- obligation opérationnelle.

---

# 9. Notifications in-app

Elles sont la source principale pour les événements ordinaires.

Elles doivent contenir :

- titre ;
- résumé ;
- icône ;
- module ;
- date ;
- état ;
- action ;
- référence ;
- priorité.

---

# 10. Notifications push

Utilisées pour :

- nouveaux messages ;
- Wallet ;
- Live ;
- Alertes ;
- tâches ;
- sécurité ;
- rappels.

Une notification push ne doit pas afficher une donnée sensible sur écran verrouillé si la préférence de confidentialité est activée.

Exemple sécurisé :

```text
Nouvelle mise à jour Wasplex
Ouvrez l’application pour consulter.
```

---

# 11. SMS

Utilisés lorsque :

- vérification de compte ;
- OTP ;
- récupération ;
- événement critique ;
- appareil sans push ;
- opération financière ;
- notification institutionnelle autorisée.

Les SMS doivent rester courts et ne pas contenir :

- détail médical ;
- solde complet ;
- données sensibles ;
- lien non signé ;
- mot de passe.

---

# 12. Emails

Utilisés pour :

- reçus ;
- factures ;
- rapports ;
- invitations ;
- campagnes ;
- exports ;
- support ;
- résumés ;
- sécurité.

Les liens sensibles doivent :

- expirer ;
- être signés ;
- exiger une authentification lorsque nécessaire.

---

# 13. WebSocket et temps réel

Utilisés pour :

- Wallet crédité ;
- Live ;
- nouveaux messages ;
- changement de statut ;
- campagne active ;
- incident ;
- tâche assignée.

Le temps réel ne remplace pas l’historique persistant.

---

# 14. États d’une notification

```text
created
queued
sent
delivered
read
acted
failed
expired
archived
```

---

# 15. Accusés de réception

Selon le type :

- envoyé ;
- livré ;
- lu ;
- action effectuée ;
- refusé ;
- expiré.

Pour les communications sensibles, l’accusé est lié à l’identité du membre et à sa session.

---

# 16. Préférences utilisateur

L’utilisateur peut configurer :

- Wallet ;
- publicité ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Live ;
- partenaires ;
- sécurité ;
- marketing ;
- rappels ;
- support.

Canaux :

- in-app ;
- push ;
- SMS ;
- email.

---

# 17. Notifications non désactivables

Certaines notifications peuvent rester obligatoires :

- sécurité du compte ;
- opération financière ;
- accès Santé d’urgence ;
- modification critique ;
- suspension ;
- incident ;
- changement légalement ou contractuellement nécessaire traduit en règle produit explicite.

Elles ne doivent pas devenir une catégorie illimitée.

---

# 18. Horaires silencieux

L’utilisateur peut définir :

- début ;
- fin ;
- jours ;
- fuseau ;
- exceptions critiques.

Les notifications critiques peuvent contourner les horaires silencieux.

---

# 19. Résumés

Wasplex peut proposer :

- résumé quotidien ;
- résumé hebdomadaire ;
- résumé campagne ;
- résumé Wallet ;
- résumé professionnel.

Les résumés ne remplacent pas les alertes critiques.

---

# 20. Centre de messages

Le centre de messages contient :

```text
Conversations personnelles
Conversations professionnelles
Alertes
Support
Archives
```

Les catégories sensibles peuvent être séparées.

---

# 21. Conversation personnelle

Peut permettre :

- texte ;
- image ;
- document ;
- lien ;
- réaction ;
- réponse ;
- blocage ;
- signalement.

Cette fonction peut être activée progressivement.

---

# 22. Conversation professionnelle

Une conversation professionnelle est liée à :

- organisation ;
- dossier ;
- opération ;
- campagne ;
- partenaire ;
- Fonds ;
- Carte ;
- Live ;
- support.

Elle ne doit pas devenir une discussion libre sans contexte dans les domaines sensibles.

---

# 23. Conversation institutionnelle

Une conversation institutionnelle possède :

- dossier ;
- institution source ;
- institution destinataire ;
- membres autorisés ;
- pièces ;
- accusés ;
- historique ;
- statut ;
- périmètre.

---

# 24. Canal temporaire

Un canal temporaire peut être créé pour :

- restitution Alertes ;
- transaction partenaire ;
- rendez-vous Fonds ;
- échange Santé autorisé ;
- support ;
- livraison ;
- opération Carte.

Il possède :

- finalité ;
- participants ;
- date de début ;
- date d’expiration ;
- permissions ;
- statut ;
- dossier lié.

---

# 25. Contacts relayés

Wasplex peut masquer :

- téléphone ;
- email ;
- adresse ;
- identité complète.

Le système relaie les messages entre les parties.

Exemple :

```text
Personne ayant trouvé un objet
↔ canal Wasplex temporaire
↔ propriétaire
```

---

# 26. Clôture d’un canal temporaire

Le canal se ferme lorsque :

- dossier résolu ;
- restitution confirmée ;
- expiration ;
- retrait d’autorisation ;
- incident ;
- blocage ;
- décision administrative.

Après clôture :

- lecture historique selon droits ;
- aucun nouveau message ;
- pièces conservées selon configuration ;
- audit disponible.

---

# 27. Messagerie Alertes

La messagerie Alertes doit permettre :

- contact relayé ;
- demande de précision ;
- correspondance ;
- restitution ;
- rendez-vous ;
- preuve ;
- communication institutionnelle ;
- notification de statut.

Elle ne doit pas exposer immédiatement les coordonnées des parties.

---

# 28. Restitution Alertes

Flux :

```text
correspondance confirmée
→ canal temporaire
→ proposition de rendez-vous
→ QR ou code
→ double confirmation
→ récompense éventuelle
→ clôture
```

---

# 29. Messagerie Santé

Usages autorisés :

- demande d’accès ;
- information générale ;
- prise de rendez-vous ;
- échange professionnel autorisé ;
- contact représentant ;
- notification de capsule ;
- incident.

Les messages Santé doivent rester dans leur domaine séparé.

---

# 30. Notifications Santé

Exemples :

- nouveau consentement demandé ;
- accès autorisé ;
- accès d’urgence utilisé ;
- accès expiré ;
- professionnel ajouté ;
- incident signalé.

Une notification externe ne doit pas révéler le contenu médical.

---

# 31. Notifications Wallet

Exemples :

- dépôt reçu ;
- dépôt en attente ;
- retrait demandé ;
- retrait confirmé ;
- transfert reçu ;
- transfert envoyé ;
- WP crédités ;
- réservation ;
- remboursement ;
- opération échouée ;
- sécurité.

---

# 32. Notifications Fonds

Exemples :

- adhésion active ;
- mandat créé ;
- contribution personnelle ;
- contribution à régulariser ;
- collecte démarrée ;
- collecte terminée ;
- prestataire payé ;
- dossier mis à jour ;
- rendez-vous.

---

# 33. Notifications Carte Wasplex

Exemples :

- carte émise ;
- support expédié ;
- carte activée ;
- QR sensible utilisé ;
- paiement ;
- avantage ;
- cashback ;
- perte ;
- suspension ;
- remplacement ;
- expiration.

---

# 34. Notifications Feed

Exemples :

- gain publicitaire crédité ;
- quota proche de la limite ;
- quota atteint ;
- campagne utile ;
- contenu enregistré ;
- signalement traité.

Les notifications Feed ne doivent pas devenir intrusives.

---

# 35. Notifications Live

Exemples :

- Live programmé ;
- rappel ;
- place rémunérée ;
- liste d’attente ;
- démarrage ;
- bloc validé ;
- bloc rejeté ;
- Wallet crédité ;
- retard ;
- annulation ;
- replay ;
- modération.

---

# 36. Notifications Studio Annonceur

Exemples :

- dépôt confirmé ;
- solde faible ;
- campagne soumise ;
- correction demandée ;
- campagne approuvée ;
- campagne active ;
- budget faible ;
- campagne terminée ;
- remboursement ;
- membre invité ;
- Live sponsorisé programmé.

---

# 37. Notifications partenaires

Exemples :

- nouvelle offre ;
- opération ;
- preuve requise ;
- règlement ;
- remboursement ;
- point de vente suspendu ;
- incident ;
- équipe.

---

# 38. Notifications institutionnelles

Exemples :

- nouveau dossier ;
- urgence ;
- affectation ;
- transfert ;
- document ;
- statut ;
- accusé ;
- escalade ;
- incident.

---

# 39. Notifications administratives

Exemples :

- incident critique ;
- compte de suspense ;
- file bloquée ;
- Live signalé ;
- campagne anormale ;
- retrait élevé ;
- accès Santé inhabituel ;
- override fondateur ;
- kill switch ;
- service dégradé.

---

# 40. Centre de support

Le support permet :

- créer un ticket ;
- choisir une catégorie ;
- décrire ;
- joindre une pièce ;
- suivre le statut ;
- répondre ;
- fermer ;
- rouvrir ;
- noter la résolution.

---

# 41. Types de tickets

```text
account
security
wallet
deposit
withdrawal
advertising
fonds
alerts
health
card
live
partner
technical
other
```

---

# 42. États d’un ticket

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

# 43. Priorité support

```text
critical
urgent
normal
low
```

La priorité est déterminée par :

- impact ;
- risque ;
- nombre de personnes ;
- montant ;
- sécurité ;
- santé ;
- urgence opérationnelle.

---

# 44. Assignation support

Un ticket peut être assigné à :

- agent ;
- équipe ;
- domaine ;
- superviseur ;
- incident.

L’utilisateur voit le statut, pas nécessairement l’identité interne complète de l’agent.

---

# 45. Messages système

Les messages système sont générés automatiquement.

Exemples :

```text
Campagne approuvée
Wallet crédité
Dossier transféré
Canal clôturé
```

Ils ne sont pas éditables par les participants.

---

# 46. Messages humains

Un message humain contient :

- auteur ;
- espace ;
- organisation ;
- texte ;
- pièces ;
- date ;
- statut ;
- référence.

---

# 47. Édition et suppression

## Édition

Peut être autorisée pendant un délai limité.

L’ancienne version reste traçable pour les conversations sensibles.

## Suppression

Deux niveaux :

- masqué pour l’utilisateur ;
- retiré par modération.

Les messages sensibles ne doivent pas disparaître silencieusement de l’audit.

---

# 48. Pièces jointes

Types :

- image ;
- PDF ;
- vidéo ;
- audio ;
- document ;
- facture ;
- reçu ;
- preuve ;
- rapport.

Contrôles :

- format ;
- taille ;
- virus ;
- accès ;
- chiffrement ;
- expiration ;
- aperçu ;
- téléchargement autorisé.

---

# 49. Audio et notes vocales

Peuvent être activés pour :

- conversation utilisateur ;
- support ;
- terrain ;
- professionnel.

Ils doivent être :

- limités en durée ;
- compressés ;
- modérables ;
- protégés ;
- associés à la conversation.

---

# 50. Appels futurs

L’architecture peut réserver :

- appel audio ;
- appel vidéo ;
- appel relayé ;
- rendez-vous.

La V1 peut fonctionner sans appels intégrés.

---

# 51. Réactions et réponses

Fonctions :

- réponse à un message ;
- citation ;
- réaction ;
- mention ;
- message épinglé.

Les mentions respectent les membres autorisés au canal.

---

# 52. Recherche dans les messages

La recherche peut porter sur :

- texte ;
- dossier ;
- référence ;
- participant ;
- date ;
- pièce ;
- statut.

Les résultats sont filtrés par permission.

---

# 53. Archivage

Une conversation peut être :

```text
active
muted
archived
closed
restricted
removed
```

---

# 54. Blocage

Un utilisateur peut bloquer un autre utilisateur pour les conversations personnelles.

Le blocage ne doit pas empêcher :

- notification financière obligatoire ;
- message système ;
- communication institutionnelle autorisée ;
- support en cours.

---

# 55. Signalement

Catégories :

- harcèlement ;
- fraude ;
- spam ;
- menace ;
- usurpation ;
- contenu interdit ;
- atteinte à la vie privée ;
- autre.

Le signalement crée un dossier de modération.

---

# 56. Modération

Actions :

```text
review
warn
mute
restrict
remove
suspend
escalate
restore
```

Chaque action possède un motif et un audit.

---

# 57. Anti-spam

Contrôles :

- fréquence ;
- duplication ;
- volume ;
- liens ;
- comptes récents ;
- destinataires multiples ;
- signalements ;
- réputation ;
- automation.

---

# 58. Liens et destinations

Les liens peuvent être :

- analysés ;
- marqués ;
- bloqués ;
- ouverts avec avertissement ;
- limités par domaine.

Les liens sensibles doivent utiliser des jetons signés et expirants.

---

# 59. Confidentialité des aperçus

L’utilisateur peut choisir :

```text
Afficher le contenu complet
Afficher seulement le titre
Masquer le contenu sensible
```

Cette préférence s’applique à l’écran verrouillé.

---

# 60. Langues

Chaque message système utilise :

- langue du destinataire ;
- variante locale ;
- fuseau ;
- format de date ;
- devise.

Les modèles sont versionnés par langue.

---

# 61. Modèles de notification

Un modèle contient :

```text
code
channel
language
subject
title
body
action_label
action_route
variables
status
version
```

---

# 62. Variables de modèle

Exemples :

```text
{{user_name}}
{{amount}}
{{currency}}
{{campaign_name}}
{{live_title}}
{{case_reference}}
{{expires_at}}
```

Les variables doivent être validées.

---

# 63. Versionnement des modèles

États :

```text
draft
tested
approved
scheduled
active
retired
```

Une version active n’est pas modifiée en place.

---

# 64. Prévisualisation administrative

L’administration peut prévisualiser :

- mobile ;
- email ;
- SMS ;
- push ;
- desktop ;
- langue ;
- mode confidentiel.

---

# 65. Envoi de test

L’administration peut envoyer un test à :

- compte administrateur ;
- environnement de test ;
- appareil autorisé.

L’envoi de test ne doit pas déclencher une opération métier réelle.

---

# 66. Campagnes de communication

Wasplex peut créer des communications groupées pour :

- maintenance ;
- information produit ;
- changement de service ;
- campagne institutionnelle ;
- rappel ;
- support.

Elles restent distinctes des campagnes publicitaires rémunérées.

---

# 67. Segmentation des communications

Critères possibles :

- pays ;
- langue ;
- plan ;
- espace ;
- organisation ;
- fonctionnalité ;
- version d’application ;
- incident ;
- statut.

Aucun ciblage sensible non nécessaire.

---

# 68. Communications marketing

Elles exigent une préférence dédiée.

L’utilisateur peut :

- accepter ;
- refuser ;
- se désabonner ;
- choisir le canal.

Les communications transactionnelles restent séparées.

---

# 69. File d’envoi

Chaque message passe par :

```text
création
→ rendu
→ validation
→ file
→ prestataire
→ livraison
→ statut
```

---

# 70. Reprise et idempotence

Le module doit éviter :

- double notification ;
- double SMS ;
- double email ;
- double conversation ;
- double accusé.

Chaque envoi possède une clé d’idempotence.

---

# 71. Échecs

Causes :

- appareil invalide ;
- numéro incorrect ;
- email rejeté ;
- prestataire indisponible ;
- modèle invalide ;
- variable manquante ;
- destinataire désactivé.

Le système peut :

- réessayer ;
- changer de canal ;
- mettre en attente ;
- signaler ;
- abandonner.

---

# 72. Ordre de secours des canaux

Exemple configurable :

```text
in-app
→ push
→ SMS pour critique
→ email pour document
```

L’ordre dépend du type d’événement.

---

# 73. Notifications temps réel Wallet

Flux :

```text
transaction Grand Livre validée
→ outbox
→ notification
→ WebSocket
→ animation Wallet
→ historique
```

La notification ne doit jamais précéder la transaction confirmée.

---

# 74. Notifications de campagne

Flux :

```text
CampaignApproved
→ notification Studio
→ email éventuel
→ calendrier
→ activation
```

---

# 75. Notifications de Live rémunéré

Flux :

```text
place attribuée
→ notification
→ entrée
→ bloc validé
→ Wallet
→ notification
```

---

# 76. Notifications d’urgence

Une notification d’urgence peut :

- contourner le mode silencieux ;
- utiliser plusieurs canaux ;
- demander un accusé ;
- être répétée selon règle ;
- afficher une action immédiate.

Elle doit rester rare et justifiée.

---

# 77. Administration

Le back-office permet de :

- voir les files ;
- voir les échecs ;
- relancer ;
- suspendre un canal ;
- gérer les modèles ;
- gérer les prestataires ;
- consulter les statistiques ;
- traiter les signalements ;
- gérer le support ;
- fermer un canal ;
- auditer.

---

# 78. Kill switches

Exemples :

```text
disable_all_sms
disable_marketing_email
disable_private_messaging
disable_external_links
disable_voice_messages
disable_push_notifications
```

Les notifications critiques internes doivent disposer d’un canal de secours.

---

# 79. Capacités

## Utilisateur

```text
notifications.view.self
notifications.preferences.manage.self
messages.send.personal
messages.report
support.ticket.create
support.ticket.view.self
```

## Professionnel

```text
professional.messages.view
professional.messages.send
professional.attachments.upload
professional.threads.create
professional.threads.close
```

## Support

```text
support.tickets.view
support.tickets.assign
support.tickets.reply
support.tickets.resolve
```

## Administration

```text
communication.templates.manage
communication.channels.manage
communication.broadcasts.manage
communication.audit.view
communication.moderation.manage
```

---

# 80. Modèle de données

Entités recommandées :

```text
notification_events
notifications
notification_deliveries
notification_preferences
notification_channels
notification_templates
notification_template_versions
notification_batches
notification_failures

message_threads
message_thread_participants
messages
message_versions
message_receipts
message_attachments
message_reactions
message_reports
message_blocks
temporary_channels
relay_contacts

support_tickets
support_ticket_messages
support_assignments
support_satisfaction

communication_broadcasts
communication_broadcast_segments
communication_provider_events
communication_audit_events
```

---

# 81. Champs — Notification

```text
id
recipient_account_id
event_type
module
priority
title
body
action_route
status
created_at
read_at
acted_at
expires_at
```

---

# 82. Champs — Delivery

```text
id
notification_id
channel
provider
provider_reference
status
attempt_count
sent_at
delivered_at
failed_at
failure_code
```

---

# 83. Champs — Thread

```text
id
type
domain
subject_type
subject_id
organization_id
status
created_by
created_at
closed_at
expires_at
```

---

# 84. Champs — Message

```text
id
thread_id
sender_account_id
sender_space_id
message_type
body
status
reply_to_id
created_at
edited_at
removed_at
```

---

# 85. API notifications

```text
GET    /api/me/notifications
GET    /api/me/notifications/unread-count
POST   /api/me/notifications/{id}/read
POST   /api/me/notifications/read-all
POST   /api/me/notifications/{id}/archive

GET    /api/me/notification-preferences
PATCH  /api/me/notification-preferences
```

---

# 86. API messagerie

```text
GET    /api/messages/threads
POST   /api/messages/threads
GET    /api/messages/threads/{id}
POST   /api/messages/threads/{id}/messages
POST   /api/messages/threads/{id}/close
POST   /api/messages/{id}/report
POST   /api/messages/{id}/react
```

---

# 87. API support

```text
GET    /api/support/tickets
POST   /api/support/tickets
GET    /api/support/tickets/{id}
POST   /api/support/tickets/{id}/messages
POST   /api/support/tickets/{id}/close
POST   /api/support/tickets/{id}/reopen
POST   /api/support/tickets/{id}/satisfaction
```

---

# 88. API professionnel

```text
GET    /api/professional/messages/threads
POST   /api/professional/messages/threads
GET    /api/professional/messages/threads/{id}
POST   /api/professional/messages/threads/{id}/messages
POST   /api/professional/messages/threads/{id}/acknowledge
POST   /api/professional/messages/threads/{id}/close
```

---

# 89. API administration

```text
GET    /api/admin/communication/dashboard
GET    /api/admin/communication/deliveries
GET    /api/admin/communication/failures
POST   /api/admin/communication/deliveries/{id}/retry

GET    /api/admin/communication/templates
POST   /api/admin/communication/templates
PATCH  /api/admin/communication/templates/{id}
POST   /api/admin/communication/templates/{id}/publish

GET    /api/admin/communication/broadcasts
POST   /api/admin/communication/broadcasts
POST   /api/admin/communication/broadcasts/{id}/send

GET    /api/admin/support/tickets
POST   /api/admin/support/tickets/{id}/assign
POST   /api/admin/support/tickets/{id}/resolve
```

---

# 90. Événements métier

```text
NotificationCreated
NotificationQueued
NotificationSent
NotificationDelivered
NotificationRead
NotificationFailed

MessageThreadCreated
MessageSent
MessageDelivered
MessageRead
MessageReported
MessageRemoved
TemporaryChannelOpened
TemporaryChannelClosed

SupportTicketCreated
SupportTicketAssigned
SupportTicketReplied
SupportTicketResolved
SupportTicketReopened

CommunicationBroadcastCreated
CommunicationBroadcastSent
CommunicationProviderFailed
```

---

# 91. Sécurité

- authentification ;
- capacités ;
- participants vérifiés ;
- liens signés ;
- chiffrement en transit ;
- chiffrement au repos selon besoin ;
- fichiers protégés ;
- rate limiting ;
- anti-spam ;
- anti-replay ;
- idempotence ;
- audit ;
- aucune information sensible inutile dans push/SMS/email.

---

# 92. Performance

- files d’attente ;
- envoi asynchrone ;
- WebSocket ;
- pagination ;
- index ;
- cache des préférences ;
- rendu de modèles ;
- regroupement ;
- retries contrôlés ;
- archivage ;
- stockage séparé des grosses pièces.

---

# 93. Accessibilité

- lecteur d’écran ;
- taille de texte ;
- contraste ;
- annonces de nouvelles notifications ;
- navigation clavier ;
- alternatives aux sons ;
- transcription des vocaux future ;
- boutons clairement nommés.

---

# 94. Tests notifications

- création ;
- in-app ;
- push ;
- SMS ;
- email ;
- préférence ;
- horaire silencieux ;
- critique ;
- lecture ;
- action ;
- échec ;
- retry ;
- idempotence.

---

# 95. Tests messagerie

- création thread ;
- participants ;
- message ;
- lecture ;
- pièce ;
- réponse ;
- réaction ;
- fermeture ;
- canal temporaire ;
- blocage ;
- signalement ;
- aucune fuite inter-espace.

---

# 96. Tests Alertes

- contact relayé ;
- coordonnées masquées ;
- canal temporaire ;
- rendez-vous ;
- code ;
- clôture ;
- historique.

---

# 97. Tests Santé

- notification externe discrète ;
- conversation dans domaine Santé ;
- accès limité ;
- expiration ;
- audit ;
- aucune donnée médicale dans push.

---

# 98. Tests Wallet et Live

Wallet :

- notification après Grand Livre ;
- aucun crédit anticipé ;
- montant correct ;
- reçu.

Live :

- rappel ;
- place rémunérée ;
- bloc validé ;
- bloc rejeté ;
- Wallet ;
- annulation.

---

# 99. Tests support

- création ;
- pièce ;
- assignation ;
- attente utilisateur ;
- résolution ;
- réouverture ;
- satisfaction ;
- priorité ;
- audit.

---

# 100. Tests responsive

## Espace utilisateur

- mobile 320/360/390 ;
- interface mobile maintenue sur desktop ;
- centre de notifications ;
- conversations ;
- support.

## Espaces professionnels

- mobile terrain ;
- tablette ;
- desktop 1280/1440 ;
- messages liés aux dossiers ;
- tableaux ;
- pièces.

## Administration

- desktop complet ;
- tablette lisible ;
- mobile limité aux notifications critiques et actions urgentes.

---

# 101. Captures obligatoires

1. centre de notifications mobile ;
2. notification Wallet ;
3. préférences ;
4. horaires silencieux ;
5. boîte de messages ;
6. conversation ;
7. canal temporaire Alertes ;
8. conversation professionnelle desktop ;
9. accusé de réception ;
10. message Santé discret ;
11. ticket support ;
12. support agent ;
13. modèle de notification ;
14. prévisualisation push ;
15. file d’échec ;
16. broadcast ;
17. signalement ;
18. mobile/desktop/tablette.

---

# 102. Critères d’acceptation

Le module est accepté lorsque :

1. le centre de notifications existe ;
2. les canaux in-app, push, SMS et email sont séparés ;
3. les préférences sont configurables ;
4. les notifications critiques restent possibles ;
5. les horaires silencieux fonctionnent ;
6. les conversations privées sont séparées des commentaires publics ;
7. les conversations professionnelles sont liées aux dossiers ;
8. les canaux temporaires existent ;
9. les coordonnées peuvent être relayées ;
10. Alertes utilise un canal sécurisé ;
11. Santé reste séparé ;
12. Wallet notifie seulement après confirmation ;
13. Live notifie les blocs validés ;
14. le support possède des tickets ;
15. les modèles sont versionnés ;
16. les échecs sont visibles et rejouables ;
17. l’idempotence empêche les doubles envois ;
18. les pièces sont protégées ;
19. les interfaces responsive respectent la doctrine Wasplex ;
20. les tests critiques passent.

---

# 103. Ordre d’implémentation

## Phase 1 — Notifications in-app

- événements ;
- modèles ;
- centre ;
- états ;
- préférences.

## Phase 2 — Push, SMS et email

- prestataires ;
- files ;
- statuts ;
- échecs ;
- retries.

## Phase 3 — Messagerie de base

- threads ;
- participants ;
- messages ;
- pièces ;
- accusés.

## Phase 4 — Canaux temporaires

- finalité ;
- expiration ;
- contacts relayés ;
- clôture.

## Phase 5 — Support

- tickets ;
- assignation ;
- conversations ;
- résolution.

## Phase 6 — Intégrations métier

- Wallet ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Feed ;
- Live ;
- Studio Annonceur ;
- espaces professionnels.

## Phase 7 — Modération

- blocage ;
- signalement ;
- anti-spam ;
- sanctions.

## Phase 8 — Administration

- modèles ;
- broadcasts ;
- files ;
- prestataires ;
- audit.

## Phase 9 — Stabilisation

- sécurité ;
- performance ;
- accessibilité ;
- responsive ;
- tests ;
- captures.

---

# 104. Première verticale à livrer

```text
Campagne annonceur approuvée
→ événement CampaignApproved
→ notification in-app
→ push
→ annonceur ouvre le Studio
→ voit la campagne approuvée
→ accusé de lecture
```

Deuxième verticale :

```text
Correspondance Alertes confirmée
→ canal temporaire créé
→ coordonnées masquées
→ messages relayés
→ rendez-vous
→ QR de restitution
→ double confirmation
→ canal clôturé
→ audit
```

Troisième verticale :

```text
Bloc Live validé
→ Grand Livre
→ Wallet crédité
→ notification temps réel
→ toast +25 WP
→ historique
```

---

# 105. Directive pour Claude Code

1. lire Compte universel, Wallet, Fonds, Alertes, Santé, Carte, Live, Studio Annonceur et Espaces professionnels ;
2. auditer le nouveau dépôt ;
3. séparer notification, messagerie, commentaire public et support ;
4. construire d’abord les notifications in-app ;
5. utiliser l’outbox pour les événements critiques ;
6. ne jamais notifier un gain avant le Grand Livre ;
7. créer des canaux temporaires et des contacts relayés ;
8. garder Santé dans son domaine ;
9. versionner les modèles ;
10. implémenter l’idempotence ;
11. fournir migrations, API, tests et captures ;
12. respecter la doctrine responsive Wasplex ;
13. ne pas introduire de texte doctrinal ou de gouvernance bloquante.

---

# 106. Décision finale

Le système de communication Wasplex doit être :

```text
clair
contextuel
sécurisé
multicanal
non intrusif
traçable
adapté au mobile et au desktop
```

> **Wasplex doit transmettre la bonne information par le bon canal, permettre les échanges nécessaires sans exposer inutilement les personnes, et rattacher chaque communication sensible à une opération, un dossier ou une finalité précise.**
