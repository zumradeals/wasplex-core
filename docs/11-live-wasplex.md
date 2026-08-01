# WASPLEX — LIVE, ATTENTION VÉRIFIÉE ET ÉCONOMIE EN TEMPS RÉEL

**Fichier cible recommandé :** `docs/10-live/00-live-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module audiovisuel, social, commercial, institutionnel et économique  
**Dépendances :** Feed, Compte universel, Mon Espace, Abonnements, Matching publicitaire, Wallet & Grand Livre, Super moteur unifié de valeur, Alertes, Santé, Partenaires et Carte Wasplex  
**Référence économique :** 1 WP = 1 FCFA  
**Durée initiale recommandée d’un bloc rémunéré :** 5 minutes

---

# 1. Objet

Ce document définit le module Live Wasplex :

- création et programmation ;
- diffusion en direct ;
- spectateurs et invitations ;
- Live sponsorisé rémunéré ;
- ciblage d’audience ;
- budget payé et réservé ;
- blocs d’attention vérifiée ;
- places rémunérées limitées ;
- créateurs et présentateurs ;
- interactions ;
- modération ;
- replay ;
- reporting ;
- administration ;
- API, données, événements et tests.

---

# 2. Principe fondamental

Tous les Live ne rémunèrent pas.

Seul un :

```text
Live sponsorisé rémunéré
```

peut distribuer des WP aux spectateurs.

La chaîne obligatoire est :

```text
Segment
→ devis
→ paiement
→ budget réservé
→ programmation
→ place rémunérée
→ attention vérifiée
→ bloc validé
→ Grand Livre
→ Wallet
→ reporting
```

---

# 3. Types de Live

## 3.1. Live standard

- utilisateur, créateur, partenaire ou organisation ;
- aucun WP spectateur par défaut ;
- commentaires et réactions ;
- accès gratuit ou payant possible ;
- replay selon politique.

## 3.2. Live sponsorisé rémunéré

- annonceur identifié ;
- segment défini ;
- budget sécurisé ;
- enveloppe spectateurs ;
- gain exact par bloc ;
- places rémunérées limitées ;
- reporting agrégé.

## 3.3. Live commercial

- présentation de produits ou services ;
- CTA, catalogue et commandes ;
- cashback partenaire possible ;
- rémunération du temps seulement avec campagne sponsorisée distincte.

## 3.4. Live partenaire

- partenaire vérifié ;
- offre, réduction ou cashback ;
- opérations Carte Wasplex ;
- reporting agrégé.

## 3.5. Live institutionnel

- institution vérifiée ;
- information publique ;
- badge officiel ;
- aucune rémunération par défaut.

## 3.6. Live Alertes ou urgence

- priorité selon protection ;
- aucune priorité vitale achetable ;
- aucun WP par défaut ;
- modération renforcée.

## 3.7. Live Santé

- prévention, information ou conférence ;
- aucune donnée médicale personnelle exposée ;
- aucun ciblage commercial fondé sur une pathologie sensible.

---

# 4. Segment annonceur

Avant la programmation publique, l’annonceur choisit des critères autorisés.

Exemple :

```text
Pays : Côte d’Ivoire
Ville : Abidjan
Commune : Cocody
Réseau principal déclaré : Orange
Projet : achat d’un téléphone
Classes : Gold et Platine
```

Critères possibles :

- pays, région, ville, commune ;
- langue ;
- intérêts ;
- usages déclarés ;
- projets déclarés ;
- catégories ;
- classe économique ;
- abonnement payant ;
- disponibilité horaire.

Wasplex ne remet jamais la liste des personnes à l’annonceur.

---

# 5. Ciblage des classes économiques

L’annonceur peut cibler :

- tous les utilisateurs éligibles ;
- uniquement les abonnements payants ;
- Premium ;
- Gold ;
- Platine ;
- plusieurs classes.

Le ciblage direct des abonnements payants est plus cher, car il représente un signal d’engagement utilisateur.

---

# 6. Protection contre la réidentification

Un segment trop petit doit être :

- élargi ;
- regroupé ;
- arrondi ;
- masqué ;
- refusé.

Les combinaisons permettant d’isoler une personne sont interdites.

---

# 7. Simulation avant paiement

L’annonceur peut simuler :

- date et heure ;
- durée ;
- segment ;
- audience estimée ;
- places rémunérées ;
- nombre de blocs ;
- gain par bloc ;
- plafond individuel ;
- budget ;
- cachet créateur ;
- options ;
- reliquat prévisionnel.

La simulation ne crée aucune promesse publique.

---

# 8. Paiement avant programmation officielle

Parcours adopté :

```text
Brouillon
→ ciblage
→ durée
→ estimation
→ devis
→ paiement
→ réservation du budget
→ validation
→ programmation officielle
→ invitations
```

Un Live sponsorisé rémunéré ne doit pas être annoncé comme confirmé avant sécurisation du budget.

---

# 9. États du financement

```text
draft
quoted
awaiting_payment
paid
funds_reserved
approved
scheduled
active
partially_consumed
completed
refundable
refunded
cancelled
disputed
```

---

# 10. Modèle économique adopté

Pour le budget sponsorisé net :

```text
50 % Wasplex
50 % enveloppe spectateurs
```

Exemple :

```text
Budget net :              100 000 FCFA
Part Wasplex :             50 000 FCFA
Enveloppe spectateurs :    50 000 FCFA
```

Le cachet du créateur est séparé de l’enveloppe spectateurs.

---

# 11. Répartition par classe

Pour un Live général :

```text
Gratuit : 10 %
Premium : 20 %
Gold : 35 %
Platine : 35 %
```

Pour Gold et Platine seulement :

```text
Gold : 50 %
Platine : 50 %
```

Les poids sont normalisés entre les classes sélectionnées.

---

# 12. Bloc d’attention

Décision initiale :

```text
1 bloc = 5 minutes d’attention vérifiée
```

La durée reste configurable par campagne.

Valeurs possibles :

- 2 minutes ;
- 5 minutes ;
- 10 minutes ;
- autre durée autorisée.

---

# 13. Calcul du gain d’un bloc

```text
Gain par bloc
= enveloppe spectateurs
÷ nombre de blocs financés
```

Exemple :

```text
Enveloppe : 50 000 FCFA
Blocs financés : 2 000
Gain : 25 WP par bloc
```

---

# 14. Plafond individuel

Chaque Live sponsorisé définit :

- gain par bloc ;
- nombre maximal de blocs ;
- gain maximal ;
- durée maximale rémunérée.

Exemple :

```text
Bloc : 5 minutes
Gain : 25 WP
Maximum : 12 blocs
Gain maximal : 300 WP
Durée maximale rémunérée : 60 minutes
```

---

# 15. Information avant l’entrée

Le spectateur voit :

```text
Live sponsorisé Orange
Durée prévue : 60 minutes
Gain : 25 WP par bloc de 5 minutes
Gain maximal : 300 WP
Places rémunérées limitées
```

Il doit également voir :

- conditions ;
- statut de sa place ;
- règles de validation ;
- possibilité de regarder sans rémunération.

---

# 16. Places rémunérées limitées

Deux statuts sont possibles.

## Place rémunérée

- budget disponible ;
- place attribuée ;
- réservation active ;
- gain affiché ;
- blocs validables.

## Place non rémunérée

- accès possible au Live ;
- aucun gain promis ;
- statut clairement indiqué.

Aucun spectateur ne doit croire qu’il gagne des WP sans place financée.

---

# 17. Liste d’attente

Lorsque les places rémunérées sont pleines :

- inscription facultative ;
- ordre configurable ;
- notification lorsqu’une place se libère ;
- délai court pour accepter ;
- aucun gain avant activation.

---

# 18. Réservation progressive

Le moteur réserve uniquement le prochain bloc.

```text
Entrée
→ réservation bloc 1
→ bloc 1 validé
→ crédit
→ réservation bloc 2
```

Si le spectateur quitte :

- blocs validés conservés ;
- bloc incomplet libéré.

---

# 19. Session spectateur

Champs principaux :

```text
live_viewer_session_id
live_id
user_id
device_session_id
rewarded_status
economic_class
started_at
last_heartbeat_at
validated_attention_ms
current_block_index
validated_blocks_count
earned_amount
status
```

États :

```text
created
waiting
admitted
watching
paused
backgrounded
attention_check
completed
left
disconnected
expired
rejected
blocked
```

---

# 20. Attention vérifiable

Un bloc est validé lorsque :

- le Live est suffisamment visible ;
- l’application est au premier plan ;
- le flux est réellement lu ;
- les heartbeats sont cohérents ;
- la session est unique ;
- l’appareil est cohérent ;
- la durée requise est atteinte ;
- le contrôle ponctuel éventuel est réussi ;
- aucun signal majeur de fraude n’est confirmé.

---

# 21. Méthodes intrusives interdites par défaut

Ne pas utiliser par défaut :

- caméra du spectateur ;
- microphone du spectateur ;
- reconnaissance faciale ;
- suivi oculaire ;
- biométrie comportementale intrusive.

Toute évolution de ce type exige une décision séparée.

---

# 22. Heartbeats

Le client transmet des signaux limités.

Le serveur vérifie :

- séquence ;
- durée ;
- visibilité ;
- lecture ;
- latence ;
- duplication ;
- vitesse impossible ;
- appareil ;
- session.

Les heartbeats ne suffisent pas seuls à prouver l’attention.

---

# 23. Contrôles ponctuels

Exemples :

- bouton de présence ;
- réaction demandée ;
- sondage ;
- question sur le Live ;
- mini-quiz ;
- action simple.

Règles :

- fréquence raisonnable ;
- accessibilité ;
- aucune question sensible ;
- aucun harcèlement du spectateur.

---

# 24. Affichage pendant le Live

```text
Bloc 3 / 12
04:12 / 05:00
Gain déjà obtenu : 50 WP
Prochain gain : +25 WP
```

États visuels :

```text
active
paused
checking
validating
credited
rejected
```

---

# 25. Validation d’un bloc

```text
durée atteinte
→ preuve soumise
→ validation
→ capture de la réservation
→ écriture au Grand Livre
→ Wallet crédité
→ animation
→ réservation du bloc suivant
```

---

# 26. Bloc incomplet

En V1, le prorata n’est pas appliqué.

```text
3 minutes sur 5
→ 0 WP pour ce bloc
```

Les blocs précédemment validés restent acquis.

---

# 27. Crédit Wallet

Après confirmation serveur :

- compteur Wallet mis à jour ;
- icône Wallet animée ;
- toast `+25 WP` ;
- historique ajouté ;
- total du Live actualisé.

Aucun crédit côté client.

---

# 28. Problème réseau du spectateur

- session suspendue ;
- délai de reprise ;
- temps non vérifié non compté ;
- réservation maintenue brièvement ;
- libération après expiration.

---

# 29. Panne imputable à Wasplex

- blocs déjà validés conservés ;
- réservations libérées ou prolongées ;
- reprise idempotente ;
- compensation possible ;
- incident audité.

---

# 30. Fin du budget

Lorsque l’enveloppe est épuisée :

- aucune nouvelle place rémunérée ;
- les réservations existantes sont honorées ;
- le Live peut continuer sans gain ;
- le changement de statut est affiché clairement.

---

# 31. Annulation avant démarrage

- invitations annulées ;
- enveloppe libérée ;
- remboursement ou crédit annonceur ;
- frais non remboursables séparés ;
- notifications.

---

# 32. Arrêt après démarrage

- blocs validés payés ;
- réservations ouvertes résolues ;
- solde restant remboursé ou crédité ;
- reporting final ;
- cause enregistrée.

---

# 33. Retard

Le système gère :

- délai de grâce ;
- notification ;
- nouvelle heure ;
- maintien ou libération des réservations ;
- annulation automatique configurable.

Aucun bloc ne démarre avant la diffusion réelle.

---

# 34. Rôles d’un Live

Un Live peut avoir :

- propriétaire ;
- annonceur ;
- créateur ;
- présentateur ;
- coanimateur ;
- modérateur ;
- producteur ;
- invité.

Ces rôles possèdent des capacités distinctes.

---

# 35. Rémunération du créateur

Modèles possibles :

- cachet fixe ;
- part de la part Wasplex ;
- commission commerciale ;
- bonus de performance ;
- contrat externe.

Interdit :

- prélever silencieusement le cachet sur l’enveloppe spectateurs annoncée.

---

# 36. Éligibilité pour diffuser

Critères configurables :

- compte actif ;
- âge ;
- KYC ;
- ancienneté ;
- historique ;
- absence de sanction ;
- capacité ;
- organisation ;
- pays ;
- catégorie ;
- dépôt de garantie éventuel.

---

# 37. Création du Live

Champs :

- titre ;
- description ;
- catégorie ;
- langue ;
- date ;
- durée ;
- miniature ;
- intervenants ;
- visibilité ;
- accès ;
- replay ;
- sponsorisation ;
- segment ;
- CTA ;
- partenaires ;
- règles de modération.

---

# 38. États du Live

```text
draft
submitted
under_review
approved
funding_pending
scheduled
ready
live
paused
ended
cancelled
suspended
archived
```

---

# 39. Invitations et rappels

Canaux :

- Feed ;
- push ;
- Mon Espace ;
- lien ;
- Carte ;
- espace organisationnel.

Rappels configurables :

- 24 heures ;
- 1 heure ;
- 10 minutes ;
- démarrage.

---

# 40. Accès gratuit, payant ou privé

Un Live peut être :

- public ;
- non répertorié ;
- privé ;
- réservé à une organisation ;
- sur invitation ;
- payant ;
- inclus dans un abonnement ;
- réservé à une Carte.

Un billet payé ne doit pas être confondu avec la récompense spectateur.

---

# 41. Commentaires et réactions

Fonctions :

- commentaires ;
- réponses ;
- réactions ;
- épinglage ;
- suppression ;
- signalement ;
- ralentissement ;
- blocage ;
- mode abonnés ;
- mode vérifiés.

Les réactions ne produisent pas automatiquement de WP.

---

# 42. Sondages et quiz

Ils peuvent être :

- informatifs ;
- interactifs ;
- sponsorisés ;
- rémunérés séparément.

Lorsqu’ils sont rémunérés :

- budget distinct ;
- règle distincte ;
- preuve ;
- plafond ;
- gain annoncé avant l’action.

---

# 43. Appels à l’action

Exemples :

- appeler ;
- visiter ;
- acheter ;
- réserver ;
- s’inscrire ;
- demander une offre ;
- ouvrir un partenaire ;
- télécharger.

Chaque CTA possède :

- destination ;
- sécurité ;
- consentement ;
- métrique ;
- éventuelle valeur distincte.

---

# 44. Événements valorisables

```text
LIVE_ATTENTION_BLOCK
LIVE_POLL_RESPONSE
LIVE_QUIZ_SUCCESS
LIVE_CTA_VALIDATED
LIVE_TICKET_PURCHASE
LIVE_CREATOR_PAYMENT
LIVE_PARTNER_CASHBACK
LIVE_REFUND
```

Chaque événement possède sa propre règle, son budget et sa preuve.

---

# 45. Cadeaux et pourboires

L’architecture doit pouvoir accueillir :

- cadeaux numériques ;
- pourboires ;
- soutien ;
- achats visuels.

Les règles économiques finales seront définies séparément.

---

# 46. Live commercial et partenaire

Fonctions possibles :

- catalogue ;
- produit épinglé ;
- commande ;
- paiement ;
- offre partenaire ;
- cashback ;
- Carte Wasplex ;
- livraison ;
- support.

Les achats ne sont jamais validés par un simple commentaire.

---

# 47. Live institutionnel, Alertes et Santé

## Institutionnel

- compte vérifié ;
- badge officiel ;
- intervenants autorisés ;
- territoire ;
- archivage ;
- audit.

## Alertes

- consignes publiques ;
- point officiel ;
- aucune priorité vitale vendue ;
- aucune exposition d’une victime ou d’une enquête.

## Santé

- prévention et information ;
- aucun diagnostic individuel public ;
- aucune donnée médicale personnelle ;
- aucun ciblage publicitaire sur pathologie sensible.

---

# 48. Modération avant diffusion

Contrôles :

- identité ;
- catégorie ;
- pays ;
- titre ;
- miniature ;
- sponsor ;
- destination ;
- contenu prévu ;
- droits ;
- niveau de risque.

---

# 49. Modération en direct

Outils :

- modérateurs humains ;
- détection automatique ;
- délai de diffusion ;
- ralentissement ;
- filtrage ;
- exclusion ;
- pause ;
- arrêt ;
- suspension de la rémunération.

---

# 50. Arrêt d’urgence

Capacités :

```text
live.pause
live.terminate
live.comments.freeze
live.rewarding.suspend
```

L’arrêt doit :

- empêcher de nouveaux blocs ;
- préserver les blocs validés ;
- résoudre les réservations ;
- produire un audit ;
- notifier les parties.

---

# 51. Signalements et sanctions

Catégories :

- fraude ;
- violence ;
- danger ;
- harcèlement ;
- désinformation ;
- produit interdit ;
- usurpation ;
- atteinte à la vie privée ;
- droit d’auteur ;
- problème technique.

Sanctions :

- avertissement ;
- limitation ;
- suspension ;
- retrait du replay ;
- revue financière ;
- perte du droit de diffuser ;
- fermeture d’un espace.

---

# 52. Replay et extraits

Le replay possède ses propres états :

```text
processing
available
restricted
removed
expired
```

Il n’est pas rémunéré comme le direct sans campagne distincte.

Les extraits deviennent des contenus Feed séparés.

---

# 53. Qualité vidéo et réseau faible

Prévoir :

- qualité adaptative ;
- plusieurs résolutions ;
- audio seul ;
- CDN ;
- transcodage ;
- reprise ;
- latence standard ou faible ;
- heartbeats légers.

Le temps rémunéré est validé côté serveur.

---

# 54. Modèle de données

Entités recommandées :

```text
lives
live_versions
live_schedules
live_roles
live_guests
live_stream_sessions
live_viewer_sessions
live_viewer_heartbeats
live_attention_blocks
live_attention_proofs
live_reward_campaigns
live_reward_envelopes
live_reward_places
live_reward_waitlists
live_reward_reservations
live_interactions
live_comments
live_comment_reports
live_polls
live_poll_responses
live_quizzes
live_quiz_responses
live_ctas
live_cta_events
live_tickets
live_gifts
live_creator_compensations
live_moderation_cases
live_replays
live_clips
live_reports
live_audit_events
```

---

# 55. Champs essentiels — Live

```text
id
owner_space_id
type
title
description
category
language
visibility
status
scheduled_at
planned_duration
started_at
ended_at
replay_policy
created_at
```

---

# 56. Champs essentiels — Campagne rémunérée

```text
id
live_id
advertiser_id
campaign_id
status
budget_amount
currency
spectator_envelope
block_duration_seconds
block_reward_amount
max_blocks_per_viewer
max_reward_per_viewer
rewarded_places
rule_version_id
```

---

# 57. Champs essentiels — Bloc d’attention

```text
id
viewer_session_id
block_index
reservation_id
required_duration_ms
validated_duration_ms
status
reward_amount
value_attempt_id
credited_at
```

---

# 58. API spectateur

```text
GET    /api/lives
GET    /api/lives/{id}
POST   /api/lives/{id}/join
POST   /api/lives/{id}/leave

POST   /api/lives/{id}/viewer-heartbeats
POST   /api/lives/{id}/attention-checks/{check}/respond

GET    /api/lives/{id}/reward-status
POST   /api/lives/{id}/reward-waitlist
DELETE /api/lives/{id}/reward-waitlist

POST   /api/lives/{id}/comments
POST   /api/lives/{id}/reactions
POST   /api/lives/{id}/report
```

---

# 59. API créateur

```text
POST   /api/creator/lives
PATCH  /api/creator/lives/{id}
POST   /api/creator/lives/{id}/submit
POST   /api/creator/lives/{id}/start
POST   /api/creator/lives/{id}/pause
POST   /api/creator/lives/{id}/end

POST   /api/creator/lives/{id}/guests
POST   /api/creator/lives/{id}/polls
POST   /api/creator/lives/{id}/ctas
GET    /api/creator/lives/{id}/report
```

---

# 60. API annonceur

```text
POST   /api/advertiser/lives
POST   /api/advertiser/lives/{id}/segment-estimate
POST   /api/advertiser/lives/{id}/quote
POST   /api/advertiser/lives/{id}/fund
POST   /api/advertiser/lives/{id}/schedule
GET    /api/advertiser/lives/{id}/budget
GET    /api/advertiser/lives/{id}/report
POST   /api/advertiser/lives/{id}/cancel
```

---

# 61. API administration

```text
GET    /api/admin/lives/dashboard
GET    /api/admin/lives
GET    /api/admin/lives/{id}

POST   /api/admin/lives/{id}/approve
POST   /api/admin/lives/{id}/reject
POST   /api/admin/lives/{id}/pause
POST   /api/admin/lives/{id}/terminate

GET    /api/admin/live-reward-campaigns
POST   /api/admin/live-reward-campaigns/{id}/suspend
POST   /api/admin/live-reward-campaigns/{id}/resume

GET    /api/admin/live-moderation
POST   /api/admin/live-moderation/{id}/resolve
```

---

# 62. Événements métier

```text
LiveCreated
LiveSubmitted
LiveApproved
LiveScheduled
LiveStarted
LivePaused
LiveEnded
LiveCancelled
LiveTerminated

LiveRewardCampaignQuoted
LiveRewardCampaignFunded
LiveRewardCampaignReserved
LiveRewardPlaceGranted
LiveRewardPlaceReleased
LiveAttentionBlockStarted
LiveAttentionBlockValidated
LiveAttentionBlockRejected
LiveAttentionBlockCredited

LiveViewerJoined
LiveViewerLeft
LiveViewerWaitlisted
LiveCommentCreated
LivePollAnswered
LiveQuizCompleted
LiveCtaValidated
LiveReplayAvailable
LiveReported
LiveModerationActionApplied
```

---

# 63. Intégration avec le Grand Livre

Comptes recommandés :

```text
live.advertiser.budget.available
live.advertiser.budget.reserved
live.spectator.envelope
live.spectator.reward.reserved
live.wasplex.revenue
live.creator.payable
live.ticket.revenue
live.gift.revenue
live.partner.cashback
live.refundable
```

Aucune valeur n’est créditée directement depuis le service de streaming.

---

# 64. Reporting annonceur

L’annonceur voit :

- audience estimée ;
- invitations ;
- spectateurs uniques ;
- places rémunérées ;
- liste d’attente ;
- durée moyenne validée ;
- blocs financés ;
- blocs consommés ;
- blocs rejetés ;
- blocs restants ;
- gain total distribué ;
- budget consommé ;
- reliquat ;
- classes agrégées ;
- territoires agrégés ;
- sondages ;
- CTA ;
- abandons.

Aucune identité nominative.

---

# 65. Reporting créateur et administration

## Créateur

- spectateurs ;
- pic ;
- durée ;
- interactions ;
- revenus ;
- modération ;
- replay.

## Administration

- Lives actifs ;
- budgets ;
- réservations ;
- WP distribués ;
- part Wasplex ;
- créateurs ;
- incidents ;
- fraude ;
- modération ;
- annulations ;
- remboursements ;
- compte de suspense.

---

# 66. Antifraude

Signaux :

- sessions multiples ;
- multi-appareils ;
- heartbeats synthétiques ;
- présence impossible ;
- automation ;
- comptes liés ;
- bloc validé trop vite ;
- collusion créateur-spectateur ;
- audience artificielle.

Décisions :

```text
allow
monitor
hold
review
deny
```

---

# 67. Sécurité et accessibilité

Sécurité :

- authentification ;
- jetons de stream ;
- URLs signées ;
- MFA ;
- idempotence ;
- anti-replay ;
- rate limiting ;
- audit ;
- contrôle des invités ;
- aucun crédit côté client.

Accessibilité :

- sous-titres ;
- transcription ;
- lecteur d’écran ;
- contraste ;
- audio seul ;
- réduction des animations ;
- délai suffisant pour les contrôles d’attention.

---

# 68. Tests obligatoires

## Live standard

- création ;
- programmation ;
- démarrage ;
- spectateur ;
- commentaire ;
- pause ;
- fin ;
- replay.

## Sponsorisation

- segment ;
- estimation ;
- devis ;
- paiement ;
- réservation ;
- blocage sans budget ;
- invitations ;
- remboursement.

## Attention

- bloc de 5 minutes ;
- progression ;
- arrière-plan ;
- heartbeat ;
- contrôle ponctuel ;
- validation ;
- crédit ;
- bloc incomplet ;
- plafond ;
- budget épuisé.

## Places rémunérées

- attribution ;
- saturation ;
- place non rémunérée ;
- liste d’attente ;
- libération ;
- réattribution.

## Économie

- partage 50/50 ;
- classes ;
- gain par bloc ;
- cachet créateur séparé ;
- double crédit impossible ;
- réservation progressive ;
- reliquat ;
- Grand Livre ;
- Wallet.

## Modération et sécurité

- signalement ;
- exclusion ;
- arrêt ;
- récompense suspendue ;
- replay retiré ;
- heartbeat falsifié ;
- URL volée ;
- autre session ;
- données sensibles.

---

# 69. Critères d’acceptation

Le module est accepté lorsque :

1. plusieurs types de Live existent ;
2. seuls les Live sponsorisés rémunérés distribuent des WP ;
3. le segment est défini avant programmation ;
4. le budget est payé et réservé ;
5. la programmation publique est bloquée sans financement ;
6. le partage 50/50 est appliqué ;
7. les classes économiques sont prises en compte ;
8. le bloc initial est de 5 minutes ;
9. le gain est annoncé avant l’entrée ;
10. les places rémunérées sont limitées ;
11. les spectateurs non rémunérés sont informés ;
12. la réservation est progressive ;
13. l’attention est vérifiée ;
14. les blocs incomplets ne sont pas payés ;
15. le Wallet est crédité après confirmation ;
16. le plafond individuel fonctionne ;
17. le cachet créateur est séparé ;
18. les reliquats sont traçables ;
19. les priorités vitales ne sont pas achetables ;
20. la modération peut interrompre le Live ;
21. le replay est séparé du direct rémunéré ;
22. les tests critiques passent.

---

# 70. Ordre d’implémentation

## Phase 1 — Live standard

- modèles ;
- programmation ;
- streaming ;
- spectateurs ;
- commentaires ;
- modération.

## Phase 2 — Campagne sponsorisée

- segment ;
- estimation ;
- devis ;
- financement ;
- réservation.

## Phase 3 — Places rémunérées

- admission ;
- capacité ;
- liste d’attente ;
- statut.

## Phase 4 — Attention

- sessions ;
- heartbeats ;
- contrôles ;
- blocs.

## Phase 5 — Valeur

- réservation progressive ;
- capture ;
- Grand Livre ;
- Wallet ;
- reporting.

## Phase 6 — Créateurs et interactions

- rôles ;
- cachets ;
- sondages ;
- quiz ;
- CTA.

## Phase 7 — Partenaires, Alertes et Santé

- offres ;
- badges ;
- priorités ;
- confidentialité.

## Phase 8 — Replay et stabilisation

- replay ;
- clips ;
- réseau faible ;
- sécurité ;
- performance ;
- accessibilité.

---

# 71. Première verticale à livrer

```text
Annonceur Orange
→ segment Gold et Platine à Cocody
→ Live de 60 minutes
→ bloc de 5 minutes à 25 WP
→ budget payé
→ enveloppe réservée
→ Live programmé
→ utilisateur Gold admis
→ bloc 1 validé
→ Wallet +25 WP
→ bloc 2 réservé
→ utilisateur quitte
→ bloc incomplet libéré
→ reporting mis à jour
```

---

# 72. Directive pour Claude Code

1. lire Feed, Matching, Abonnements, Wallet, Super moteur, Carte, Alertes et Santé ;
2. auditer le nouveau dépôt ;
3. construire le Live standard avant la rémunération ;
4. séparer streaming, financement, attention et Wallet ;
5. bloquer la programmation sponsorisée sans budget ;
6. utiliser des blocs de 5 minutes configurables ;
7. réserver un seul bloc à la fois ;
8. ne jamais créditer côté client ;
9. séparer le cachet créateur ;
10. coder les places non rémunérées et la liste d’attente ;
11. fournir migrations, API et tests ;
12. fournir une démonstration complète.

---

# 73. Décision finale

Le Live Wasplex doit transformer une audience définie et financée en une expérience interactive, mesurable et honnête.

> **Un spectateur ne gagne des WasPoints que lorsqu’un annonceur ou un financeur a préalablement payé une campagne Live rémunérée, qu’une place de récompense lui a été attribuée, qu’un bloc complet d’attention a été vérifié et que la valeur a été inscrite au Grand Livre.**
