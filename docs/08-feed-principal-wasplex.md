# WASPLEX — FEED PRINCIPAL

**Fichier cible recommandé :** `docs/07-feed/00-feed-principal-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique prête au codage  
**Position dans l’écosystème :** écran d’entrée quotidien et moteur d’engagement principal  
**Navigation officielle :** Feed — Fonds — Wallet — Alertes — Mon Espace  
**Dépendances :** Identité, Abonnements, Profil publicitaire, Matching, Campagnes, Super moteur de valeur, Wallet, Alertes  
**Référence économique :** 1 WP = 1 FCFA  
**Principe central :** le Feed doit donner une expérience fluide de vidéo verticale tout en distinguant clairement publicité rémunérée, information utile, alerte, contenu institutionnel et futur Live

---

# 1. OBJET DU DOCUMENT

Ce document définit le Feed principal de Wasplex.

Le Feed doit réunir dans une même expérience :

- les publicités vidéo verticales ;
- les gains en WasPoints ;
- la barre de progression d’attention ;
- le crédit du Wallet en temps réel ;
- les alertes communautaires ;
- les avis officiels ;
- les informations Santé ;
- les conseils utiles ;
- les cercles Alertes ;
- le rail vertical discret ;
- les insertions plein écran ;
- les interactions sociales ;
- les contenus Explorer ;
- la future entrée Live ;
- la modération ;
- la sécurité ;
- la performance sur réseau faible ;
- l’administration et les statistiques.

Le Feed ne doit jamais mélanger les règles économiques des différents types de contenu.

---

# 2. VISION PRODUIT

Le Feed Wasplex doit être compris immédiatement :

```text
Je fais défiler
→ je vois une publicité pertinente
→ je connais le gain avant de commencer
→ la barre prouve mon attention
→ je termine
→ le Wallet central s’anime
→ mes WP sont crédités
→ je continue le Feed
```

Parallèlement :

```text
Une alerte importante apparaît
→ elle ne consomme pas mon quota publicitaire
→ elle ne produit aucun WP
→ elle peut être suivie, partagée ou signalée
```

Le Feed est donc à la fois :

- un espace commercial ;
- un canal de rémunération ;
- un canal de protection ;
- un canal d’information ;
- un futur canal de diffusion Live.

---

# 3. PLACE DANS LA NAVIGATION

Barre principale :

```text
Feed
Fonds
Wallet
Alertes
Mon Espace
```

Le Wallet est central et visuellement dominant.

Le Feed est l’écran par défaut après ouverture, sauf :

- reprise d’une opération critique ;
- notification Alertes prioritaire ;
- deep link explicite ;
- tâche d’onboarding obligatoire.

---

# 4. STRUCTURE GÉNÉRALE DU FEED

Écran principal :

```text
Barre supérieure
├── Pour toi
├── Alertes
└── Explorer

Zone de cercles Alertes
Zone de contenu plein écran
Rail vertical discret
Actions sociales
Barre de progression publicitaire
Indicateur de gain
Navigation principale basse
```

La barre basse reste visible ou réapparaît selon le comportement UX retenu.

---

# 5. ONGLETS SUPÉRIEURS

## 5.1. Pour toi

Contient principalement :

- publicités compatibles ;
- contenus institutionnels autorisés ;
- conseils utiles ;
- Alertes injectées selon priorité ;
- contenu futur non publicitaire ;
- Live futur signalé.

## 5.2. Alertes

Au toucher :

- affiche les cercles Alertes ;
- donne accès aux alertes proches ;
- catégories ;
- avis officiels ;
- personnes ;
- véhicules ;
- objets ;
- Santé ;
- alertes suivies.

Le contenu reste dans l’univers Feed, avec accès au module Alertes complet.

## 5.3. Explorer

Contient :

- catégories commerciales ;
- contenus publics autorisés ;
- campagnes à découvrir ;
- partenaires ;
- tendances agrégées ;
- recherches ;
- contenus thématiques ;
- futur Live.

Explorer ne doit pas contourner les règles de quota et de consentement.

---

# 6. TYPES DE CONTENU

Codes initiaux :

```text
advertisement
community_alert
official_notice
health_notice
safety_advice
partner_content
explorer_content
live_preview
```

Le module Live sera défini séparément.

Chaque type possède :

- règles d’éligibilité ;
- priorité ;
- format ;
- durée ;
- actions ;
- règles économiques ;
- règles de mesure ;
- politique de conservation ;
- politique de modération.

---

# 7. PUBLICITÉ

Une publicité peut être :

- vidéo verticale ;
- image plein écran ;
- carrousel futur ;
- bannière intégrée future ;
- contenu interactif ;
- CTA.

La première version doit privilégier la vidéo verticale.

Une publicité possède :

- annonceur ;
- campagne ;
- création ;
- version ;
- média ;
- format ;
- durée ;
- gain ;
- action attendue ;
- ciblage ;
- dates ;
- budget ;
- fréquence ;
- état ;
- règles de preuve.

---

# 8. ALERTE COMMUNAUTAIRE

Une alerte communautaire :

- ne produit aucun WP ;
- ne consomme pas le quota publicitaire ;
- ne consomme pas le budget annonceur ;
- ne déclenche aucune métrique publicitaire qualifiée ;
- possède une projection publique sûre ;
- peut être suivie, partagée ou signalée ;
- peut être insérée selon priorité.

---

# 9. AVIS OFFICIEL

Un avis officiel provient d’une institution vérifiée.

Il peut concerner :

- sécurité ;
- disparition ;
- circulation ;
- météo ;
- santé publique ;
- recherche de véhicule ;
- autre information autorisée.

Il doit afficher :

- source ;
- badge officiel ;
- territoire ;
- date ;
- durée de validité ;
- statut.

---

# 10. INFORMATION SANTÉ

Une information Santé dans le Feed :

- est générale ou institutionnelle ;
- ne contient pas de dossier médical personnel ;
- ne cible pas commercialement une pathologie ;
- ne produit pas de WP sauf décision future distincte ;
- ne consomme pas le quota publicitaire.

Exemples :

- conseil de prévention ;
- campagne institutionnelle ;
- appel au sang vérifié ;
- information sanitaire publique.

---

# 11. CERcles ALERTES

Lorsque l’utilisateur touche Alertes en haut du Feed, afficher une rangée de cercles.

Exemple :

```text
[Proche] [Objets] [Véhicules] [Personnes] [Santé] [Officiel]
```

Chaque cercle peut présenter :

- icône ;
- miniature ;
- badge nouveau ;
- catégorie ;
- priorité ;
- zone ;
- bordure ;
- état vu/non vu.

Au toucher :

- ouverture plein écran ;
- navigation verticale ;
- accès au détail ;
- suivre ;
- partager ;
- signaler.

Les alertes résolues, retirées ou expirées disparaissent.

---

# 12. RAIL VERTICAL DISCRET

Le rail est positionné sur le côté droit.

Il affiche des alertes courtes ou prioritaires sans masquer :

- les actions sociales ;
- le texte principal ;
- la barre de progression ;
- le CTA ;
- la navigation basse.

Contenu :

- icône ;
- miniature ;
- badge ;
- animation légère ;
- priorité ;
- durée.

Actions :

- ouvrir fiche compacte ;
- ouvrir module Alertes ;
- masquer le rail temporairement si autorisé.

---

# 13. INSERTIONS PLEIN ÉCRAN

Le Feed peut insérer entre les publicités :

- alerte ;
- avis officiel ;
- conseil ;
- information Santé ;
- contenu partenaire non commercial ;
- contenu Explorer.

Cadence initiale :

```text
après 5 à 10 publicités
→ une insertion utile possible
```

La cadence est configurable.

Une alerte P0 peut interrompre immédiatement la cadence.

---

# 14. PRIORITÉ DE PROTECTION

Ordre :

```text
P0 — vitale immédiate
P1 — protection publique
P2 — sensible contrôlée
P3 — communauté
P4 — visibilité renforcée
```

La priorité de protection est indépendante du paiement.

Une alerte payante ne dépasse jamais P0, P1 ou P2.

---

# 15. FILES DE CONTENU

Files distinctes :

```text
Protection vitale
Institutionnel
Publicitaire
Communautaire
Visibilité renforcée
Explorer
```

Le moteur de composition du Feed choisit entre ces files selon :

- priorité ;
- pertinence ;
- cadence ;
- quota ;
- consentement ;
- fraîcheur ;
- diversité ;
- fréquence ;
- fatigue.

---

# 16. RÈGLES ÉCONOMIQUES PAR TYPE

| Type | Consomme quota pub | Produit WP | Consomme budget annonceur |
|---|---:|---:|---:|
| Publicité qualifiée | Oui | Oui si validée | Oui si validée |
| Publicité abandonnée | Oui après exposition réelle | Non | Non |
| Alerte | Non | Non | Non |
| Avis officiel | Non | Non | Non |
| Santé institutionnelle | Non | Non | Non |
| Conseil utile | Non | Non | Non |
| Visibilité renforcée Alertes | Non pour le lecteur | Non | Paiement auteur séparé |
| Aperçu annonceur (propre organisation) | Non | Non | Non |

L'annonceur et tout membre actif de son équipe peuvent voir leur propre
campagne dans le Feed, en priorité basse par rapport au contenu réel, mais
ne peuvent jamais en être récompensés : aucune réservation d'enveloppe
budgétaire, aucune consommation de quota, aucun gain WP.

---

# 17. QUOTAS PUBLICITAIRES

Configuration initiale :

```text
Gratuit : 120 / mois
Premium : 300 / mois
Gold : 600 / mois
Platine : 900 / mois
```

Le quota représente toutes les publicités réellement présentées.

Il ne représente pas seulement les publicités terminées.

---

# 18. CONSOMMATION DU QUOTA

Événement :

```text
AdDelivered
```

Une unité est consommée après exposition réelle minimale.

Ne comptent pas :

- préchargement ;
- média hors écran ;
- erreur avant affichage ;
- contenu non publicitaire.

Comptent :

- publicité réellement commencée ;
- publicité balayée après seuil ;
- publicité terminée.

---

# 19. FIN DU QUOTA

Lorsque le quota est épuisé :

- aucune nouvelle publicité commerciale ;
- aucune publicité cachée sans gain ;
- Feed continue avec contenu non publicitaire ;
- affichage du prochain renouvellement ;
- proposition de plan facultative.

Message :

> **Quota publicitaire atteint — renouvellement dans X jours.**

---

# 20. GAIN AFFICHÉ AVANT LA PUBLICITÉ

Avant le démarrage :

```text
Regardez jusqu’à la fin
Gain : 175 WP
Durée : 30 secondes
```

Le gain provient du Super moteur.

Il doit être :

- financé ;
- réservé ;
- exact ;
- lié à la classe économique ;
- non modifiable pendant la session.

---

# 21. DÉMARRAGE D’UNE PUBLICITÉ

Flux :

```text
Feed demande un contenu
→ Matching sélectionne une campagne
→ Super moteur calcule
→ valeur réservée
→ session d’attention créée
→ média chargé
→ publicité affichée
```

Sans réservation confirmée :

- aucun gain promis ;
- publicité rémunérée non démarrée.

---

# 22. BARRE DE PROGRESSION

La barre est fine, visible et liée au temps d’attention validable.

Elle doit :

- commencer à zéro ;
- progresser avec la lecture visible ;
- se mettre en pause si nécessaire ;
- ne pas avancer hors écran ;
- refléter la durée requise ;
- atteindre 100 % uniquement au seuil qualifiant.

La barre ne doit pas être décorative.

---

# 23. ÉTATS VISUELS DE LA BARRE

```text
inactive
loading
active
paused
attention_lost
validating
completed
rejected
```

Affichage possible :

- temps restant ;
- gain ;
- progression ;
- message de reprise ;
- statut de validation.

---

# 24. SESSION D’ATTENTION

Champs principaux :

```text
attention_session_id
value_attempt_id
campaign_id
creative_id
user_id
device_session_id
required_duration_ms
visible_duration_ms
progress_percent
status
started_at
last_heartbeat_at
completed_at
```

---

# 25. HEARTBEATS

Le client envoie des signaux périodiques limités.

Le serveur vérifie :

- séquence ;
- temps ;
- visibilité ;
- activité ;
- média ;
- signature ;
- duplication ;
- vitesse impossible ;
- appareil ;
- session.

Les heartbeats ne doivent pas saturer le réseau.

---

# 26. PERTE DE FOCUS

Si l’application passe en arrière-plan :

- progression suspendue ;
- gain non acquis ;
- réservation maintenue pendant un délai ;
- reprise possible ;
- expiration si délai dépassé.

Si la publicité reste visible dans un mode autorisé, une règle spécifique peut être définie plus tard.

---

# 27. SWIPE AVANT LA FIN

Si l’utilisateur balaie :

- session abandonnée ;
- aucun gain ;
- réservation libérée ;
- quota consommé si exposition réelle ;
- prochaine publicité sélectionnée.

Une confirmation n’est pas nécessaire pour chaque abandon.

---

# 28. COMPLÉTION

Lorsque la barre atteint 100 % :

```text
preuve soumise
→ état validating
→ validation serveur
→ grand livre
→ Wallet
```

Le client ne crédite jamais localement.

---

# 29. ANIMATION DE GAIN

Après confirmation serveur :

1. barre validée ;
2. martin-pêcheur ou élément visuel transporte le gain ;
3. icône Wallet centrale pulse ;
4. compteur augmente ;
5. toast `+175 WP` ;
6. historique mis à jour.

L’animation doit être courte.

Options utilisateur :

- son ;
- vibration ;
- animations réduites.

---

# 30. ÉCHEC DE VALIDATION

Cas :

- session invalide ;
- preuve incohérente ;
- campagne suspendue ;
- réservation expirée ;
- duplication ;
- fraude ;
- erreur système.

Affichage :

- raison générale ;
- aucun faux crédit ;
- retry uniquement si autorisé ;
- restauration du quota si défaut Wasplex avéré.

---

# 31. ACTIONS SOCIALES

Actions initiales :

- aimer ;
- commenter ;
- partager ;
- enregistrer ;
- ouvrir CTA ;
- masquer ;
- signaler ;
- pourquoi cette publicité ?

Les actions n’accordent pas automatiquement de WP.

---

# 32. « POURQUOI CETTE PUBLICITÉ ? »

Afficher une explication simple :

```text
Cette publicité vous est proposée parce que :
- vous avez choisi Orange comme réseau principal ;
- vous avez indiqué Cocody comme zone d’intérêt ;
- votre abonnement Gold fait partie de l’audience ciblée.
```

Ne jamais révéler :

- score interne complet ;
- règles antifraude ;
- données sensibles ;
- identité d’autres utilisateurs.

L’utilisateur peut corriger son profil ou retirer le consentement.

---

# 33. COMMENTAIRES

Les commentaires peuvent être activés par campagne.

Fonctions :

- texte ;
- réponses ;
- likes ;
- signalement ;
- modération ;
- fermeture par l’annonceur sous contrôle ;
- tri.

Interdits :

- collecte forcée de coordonnées ;
- harcèlement ;
- fraude ;
- données sensibles ;
- usurpation.

---

# 34. PARTAGE

Le partage peut utiliser :

- lien public ;
- application ;
- messagerie ;
- QR futur ;
- copie.

Le partage ne doit pas :

- transférer le profil ciblé ;
- créditer automatiquement ;
- contourner les règles de campagne.

---

# 35. CTA

Exemples :

- appeler ;
- envoyer SMS ;
- WhatsApp si autorisé ;
- visiter site ;
- itinéraire ;
- formulaire ;
- acheter ;
- réserver ;
- télécharger.

Chaque CTA possède :

- type ;
- destination ;
- validation ;
- sécurité ;
- métrique ;
- coût éventuel ;
- consentement.

---

# 36. CTA FACTURABLE

Un CTA peut être un événement qualifié séparé.

Exemple :

```text
Vidéo complétée
→ 50 WP

Demande d’appel validée
→ événement distinct selon campagne
```

Le catalogue doit éviter les doubles rémunérations non prévues.

---

# 37. PRÉCHARGEMENT

Le Feed peut précharger :

- média suivant ;
- miniature ;
- métadonnées ;
- sous-titres.

Le préchargement :

- ne consomme aucun quota ;
- ne réserve pas nécessairement longtemps la valeur ;
- doit respecter les données mobiles ;
- s’adapte au réseau.

---

# 38. BUFFER DU FEED

Le client maintient un petit buffer :

```text
contenu courant
contenu suivant
éventuellement contenu précédent
```

Éviter :

- précharger dix vidéos ;
- bloquer trop de budget ;
- consommer trop de données ;
- créer des réservations inutiles.

---

# 39. RÉSEAU FAIBLE

Prévoir :

- qualité adaptative ;
- compression ;
- miniatures ;
- reprise ;
- cache limité ;
- timeout ;
- mode économie de données ;
- sous-titres légers ;
- feedback clair.

Une publicité non lisible ne doit pas pénaliser l’utilisateur.

---

# 40. MODE ÉCONOMIE DE DONNÉES

Options :

- résolution réduite ;
- préchargement désactivé ;
- lecture au toucher ;
- images préférées à la vidéo lorsque campagne compatible ;
- statistiques de consommation.

L’annonceur doit savoir si son format est compatible.

---

# 41. ACCESSIBILITÉ

- sous-titres ;
- contraste ;
- taille de texte ;
- lecteur d’écran ;
- actions accessibles ;
- réduction des animations ;
- vibration désactivable ;
- commandes claires ;
- alternatives au son.

La progression ne doit pas dépendre uniquement d’une couleur.

---

# 42. AUTOPLAY

Règle initiale :

- vidéo démarre quand suffisamment visible ;
- son selon préférence ;
- sous-titres disponibles ;
- pause hors écran ;
- pas de lecture simultanée.

---

# 43. GESTION DU SON

Préférences :

- mémoriser muet/non muet ;
- indicateur clair ;
- ne pas forcer un volume élevé ;
- respecter le mode silencieux.

Le son n’est pas requis pour valider l’attention sauf règle spéciale explicitement annoncée.

---

# 44. DIVERSITÉ DU FEED

Le moteur doit éviter :

- même annonceur en boucle ;
- même catégorie répétée ;
- mêmes créations ;
- saturation d’une campagne ;
- domination des campagnes les mieux financées ;
- répétition excessive d’une alerte.

Facteurs :

- fréquence ;
- fatigue ;
- diversité ;
- pertinence ;
- budget ;
- fraîcheur.

---

# 45. FATIGUE PUBLICITAIRE

Compteurs :

- par campagne ;
- par annonceur ;
- par catégorie ;
- par jour ;
- par semaine ;
- par cycle.

Le moteur peut réduire la probabilité d’affichage sans modifier le quota.

---

# 46. FRÉQUENCE

Exemple configurable :

```text
maximum 2 fois / jour
maximum 5 fois / semaine
```

Une même campagne peut produire plusieurs gains uniquement si le contrat et la fréquence l’autorisent.

---

# 47. FEED SESSION

Une session Feed possède :

```text
feed_session_id
user_id
device_session_id
started_at
ended_at
country
app_version
network_type
content_count
ad_count
useful_insertion_count
```

Elle sert à :

- ordonnancement ;
- déduplication ;
- reprise ;
- mesure ;
- sécurité.

---

# 48. REPRISE DE SESSION

À la réouverture :

- reprendre proprement ;
- ne pas recréditer ;
- vérifier les tentatives ;
- relâcher les réservations expirées ;
- restaurer le contenu si pertinent ;
- éviter de revenir systématiquement sur une publicité abandonnée.

---

# 49. ALGORITHME DE COMPOSITION

Étapes :

```text
1. vérifier urgence P0
2. vérifier insertion institutionnelle
3. vérifier quota publicitaire
4. demander candidats Matching
5. appliquer fréquence et diversité
6. vérifier budget et réservation
7. injecter contenu
8. précharger suivant
```

Aucun calcul publicitaire lourd ne doit bloquer le scroll.

---

# 50. CANDIDATS PUBLICITAIRES

Le Matching retourne une liste limitée :

- campagne ;
- pertinence ;
- classe ;
- gain ;
- budget ;
- fréquence ;
- format ;
- média ;
- expiration.

Le Feed ne reçoit pas les données privées ayant servi au matching.

---

# 51. EXPLORER

Fonctions initiales :

- recherche ;
- catégories ;
- partenaires ;
- campagnes publiques ;
- nouveautés ;
- offres locales ;
- tendances.

Explorer respecte :

- quota ;
- consentement ;
- fréquence ;
- protection des données ;
- modération.

---

# 52. RECHERCHE

Recherche sur :

- annonceurs ;
- catégories ;
- produits ;
- partenaires ;
- contenus publics ;
- alertes publiques.

Ne pas permettre la recherche d’utilisateurs à partir de données publicitaires privées.

---

# 53. LIVE — RÉSERVATION D’ARCHITECTURE

Le Feed peut afficher :

- badge Live ;
- aperçu ;
- cercle Live ;
- événement programmé.

Le Live sera défini séparément.

Ne pas coder dans le Feed :

- cadeaux ;
- revenus créateurs ;
- modération Live complète ;
- paiement Live ;

avant sa spécification.

---

# 54. MODÉRATION PUBLICITAIRE

Avant diffusion :

- annonceur vérifié ;
- campagne soumise ;
- média analysé ;
- catégorie ;
- texte ;
- destination ;
- claims ;
- pays ;
- restrictions ;
- validation.

États :

```text
draft
submitted
under_review
approved
rejected
suspended
expired
```

---

# 55. SIGNALEMENT

L’utilisateur peut signaler :

- contenu trompeur ;
- violence ;
- fraude ;
- produit interdit ;
- contenu inapproprié ;
- destination dangereuse ;
- répétition ;
- problème technique.

Un signalement ne modifie pas directement le budget.

---

# 56. MASQUER UNE PUBLICITÉ

Actions :

- masquer cette publicité ;
- moins de cette catégorie ;
- masquer cet annonceur ;
- expliquer pourquoi.

Le moteur doit respecter ce choix selon la politique.

---

# 57. MINEURS

Règles renforcées :

- catégories limitées ;
- pas de ciblage comportemental avancé ;
- pas de produits financiers ;
- pas de manipulation ;
- pas d’inférences sensibles ;
- fréquence réduite ;
- modération renforcée.

---

# 58. CONFIDENTIALITÉ

Le Feed ne reçoit jamais :

- documents KYC ;
- dossier Santé ;
- SOS ;
- dossier policier ;
- pauvreté ;
- dette ;
- coordonnées privées ;
- position exacte inutile.

Il reçoit seulement une décision de matching et les explications autorisées.

---

# 59. ANALYTICS

Métriques publicitaires :

- impressions réelles ;
- livraisons ;
- démarrages ;
- progression ;
- complétions ;
- abandons ;
- événements qualifiés ;
- CTA ;
- fréquence ;
- portée ;
- coût ;
- gain distribué.

Métriques Feed :

- temps de session ;
- latence ;
- buffer ;
- erreurs ;
- contenu utile ;
- alertes ouvertes ;
- quota ;
- réseau.

---

# 60. REPORTING ANNONCEUR

Afficher de manière agrégée :

- budget ;
- événements ;
- portée ;
- fréquence ;
- complétion ;
- CTA ;
- géographie agrégée ;
- classes ciblées ;
- coût ;
- période.

Aucune liste nominative.

---

# 61. ADMINISTRATION FEED

Dashboard :

- campagnes actives ;
- budget ;
- livraisons ;
- complétions ;
- anomalies ;
- quotas ;
- latence ;
- alertes injectées ;
- contenus institutionnels ;
- modération ;
- fraude ;
- réservations expirées.

---

# 62. CONFIGURATION ADMINISTRATIVE

Configurer :

- cadence utile ;
- types de contenu ;
- priorités ;
- quotas ;
- seuil d’exposition ;
- fréquence ;
- fatigue ;
- préchargement ;
- qualité média ;
- durée heartbeats ;
- timeout ;
- animation ;
- modération ;
- pays ;
- classes.

---

# 63. MODÈLE DE DONNÉES

Entités recommandées :

```text
feed_sessions
feed_items
feed_item_versions
feed_item_sources
feed_composition_rules
feed_delivery_candidates
feed_deliveries
feed_delivery_events
feed_attention_sessions
feed_attention_heartbeats
feed_attention_proofs
feed_user_interactions
feed_comments
feed_comment_reports
feed_shares
feed_saved_items
feed_frequency_counters
feed_fatigue_counters
feed_user_preferences
feed_explanations
feed_moderation_cases
feed_audit_events
```

Les campagnes, Alertes, Wallet et Value Engine restent dans leurs domaines.

---

# 64. CHAMPS — FEED ITEM

```text
id
type
source_module
source_id
version_id
status
priority
territory
starts_at
ends_at
published_at
metadata
```

---

# 65. CHAMPS — FEED DELIVERY

```text
id
feed_session_id
feed_item_id
user_id
position
delivery_reason
matched_segment_reference
economic_class
value_attempt_id
status
delivered_at
```

Le `matched_segment_reference` ne révèle aucune donnée personnelle à l’annonceur.

---

# 66. CHAMPS — ATTENTION SESSION

```text
id
delivery_id
user_id
device_session_id
required_duration_ms
validated_duration_ms
progress_percent
status
started_at
last_heartbeat_at
completed_at
```

---

# 67. API FEED

```text
POST   /api/feed/sessions
GET    /api/feed/next
GET    /api/feed/items/{id}

POST   /api/feed/deliveries/{id}/visible
POST   /api/feed/deliveries/{id}/dismiss
POST   /api/feed/deliveries/{id}/complete

POST   /api/feed/attention/{id}/heartbeat
POST   /api/feed/attention/{id}/complete
POST   /api/feed/attention/{id}/abandon

POST   /api/feed/items/{id}/like
POST   /api/feed/items/{id}/save
POST   /api/feed/items/{id}/share
POST   /api/feed/items/{id}/report
POST   /api/feed/items/{id}/hide

GET    /api/feed/items/{id}/comments
POST   /api/feed/items/{id}/comments

GET    /api/feed/why/{delivery_id}
```

---

# 68. API EXPLORER

```text
GET    /api/explorer
GET    /api/explorer/categories
GET    /api/explorer/search
GET    /api/explorer/partners
GET    /api/explorer/trending
```

---

# 69. API ADMINISTRATION

```text
GET    /api/admin/feed/dashboard
GET    /api/admin/feed/configuration
PATCH  /api/admin/feed/configuration

GET    /api/admin/feed/deliveries
GET    /api/admin/feed/attention
GET    /api/admin/feed/moderation
POST   /api/admin/feed/moderation/{id}/resolve

GET    /api/admin/feed/insertions
POST   /api/admin/feed/insertions
PATCH  /api/admin/feed/insertions/{id}
```

---

# 70. ÉVÉNEMENTS MÉTIER

```text
FeedSessionStarted
FeedItemSelected
FeedItemDelivered
AdDelivered
FeedItemDismissed
AttentionSessionStarted
AttentionHeartbeatRecorded
AttentionCompleted
QualifiedAttentionValidated
QualifiedAttentionRejected
FeedItemLiked
FeedItemShared
FeedItemSaved
FeedItemReported
FeedUsefulContentInserted
FeedPriorityAlertInserted
WalletRewardAnimationRequested
```

---

# 71. OUTBOX ET TEMPS RÉEL

Événements confirmés :

- gain Wallet ;
- suspension campagne ;
- alerte prioritaire ;
- retrait contenu ;
- quota ;
- modération.

Le client doit pouvoir recevoir :

- WebSocket ;
- push ;
- polling de secours.

---

# 72. CACHE

Peut contenir :

- médias publics ;
- taxonomies ;
- configuration ;
- miniatures ;
- contenu suivant.

Ne pas mettre en cache sans protection :

- gain ;
- réservation ;
- solde ;
- consentement ;
- décision sensible.

---

# 73. SÉCURITÉ

- sessions signées ;
- UUID ;
- idempotence ;
- anti-replay ;
- rate limiting ;
- validation média ;
- liens sûrs ;
- destination contrôlée ;
- webhooks signés ;
- audit ;
- permissions ;
- protection des commentaires ;
- aucune confiance dans le client.

---

# 74. ANTIFRAUDE

Signaux :

- lecture accélérée ;
- heartbeats impossibles ;
- multi-appareils ;
- répétition ;
- émulateur ;
- automation ;
- session partagée ;
- perte de focus anormale ;
- clics synthétiques ;
- volume impossible.

Décisions :

```text
allow
monitor
hold
review
deny
```

---

# 75. PERFORMANCE

Objectifs :

- démarrage rapide ;
- scroll fluide ;
- réserve limitée ;
- API paginée ;
- médias CDN ;
- qualité adaptative ;
- index ;
- calcul Matching hors chemin critique lourd ;
- cache de règles ;
- préchargement raisonnable.

La cohérence économique reste prioritaire.

---

# 76. TESTS FONCTIONNELS

- ouverture Feed ;
- Pour toi ;
- Alertes ;
- Explorer ;
- cercles ;
- rail ;
- insertion ;
- vidéo ;
- gain ;
- swipe ;
- complétion ;
- Wallet ;
- quota ;
- CTA ;
- commentaire ;
- partage ;
- signalement.

---

# 77. TESTS ÉCONOMIQUES

- gain connu ;
- réservation ;
- abandon ;
- capture ;
- double crédit impossible ;
- budget insuffisant ;
- quota consommé ;
- quota épuisé ;
- quota restauré ;
- aucune valeur sur Alerte ;
- aucune valeur sur avis officiel.

---

# 78. TESTS D’ATTENTION

- lecture normale ;
- pause ;
- arrière-plan ;
- réseau coupé ;
- reprise ;
- accélération ;
- double heartbeat ;
- heartbeat en retard ;
- durée incohérente ;
- completion exacte ;
- expiration.

---

# 79. TESTS ALERTES

- P0 interrompt ;
- P1 prioritaire ;
- P4 ne dépasse pas P0 ;
- cercles ;
- rail ;
- plein écran ;
- aucune donnée sensible ;
- résolution retire le contenu ;
- aucun WP.

---

# 80. TESTS RÉSEAU FAIBLE

- 2G/3G simulé ;
- vidéo basse qualité ;
- timeout ;
- reprise ;
- préchargement désactivé ;
- mode économie ;
- défaut Wasplex sans pénalité.

---

# 81. TESTS DE SÉCURITÉ

- autre utilisateur ;
- session falsifiée ;
- gain modifié client ;
- durée falsifiée ;
- replay ;
- CTA dangereux ;
- commentaire abusif ;
- média non autorisé ;
- consentement retiré ;
- donnée Santé absente.

---

# 82. TESTS VISUELS

Captures minimales :

1. Feed vidéo ;
2. onglets supérieurs ;
3. gain avant lecture ;
4. barre active ;
5. validation ;
6. Wallet animé ;
7. historique ;
8. cercles Alertes ;
9. rail ;
10. insertion plein écran ;
11. quota atteint ;
12. Explorer ;
13. commentaire ;
14. mode réseau faible ;
15. mobile 320/360/390 ;
16. tablette si supportée.

---

# 83. CRITÈRES D’ACCEPTATION

Le Feed est accepté lorsque :

1. il est l’écran principal ;
2. Pour toi, Alertes et Explorer existent ;
3. le scroll vertical est fluide ;
4. le gain est connu avant lecture ;
5. la réservation existe ;
6. la barre représente l’attention ;
7. le swipe abandonne sans gain ;
8. la complétion crédite le Wallet ;
9. l’animation dépend du serveur ;
10. le quota est respecté ;
11. aucune publicité après quota ;
12. Alertes ne consomme aucun quota ;
13. Alertes ne produit aucun WP ;
14. les cercles existent ;
15. le rail existe ;
16. les insertions existent ;
17. P0 peut interrompre ;
18. la publicité ne révèle aucune identité ;
19. le réseau faible est supporté ;
20. les commentaires sont modérés ;
21. l’administration contrôle la cadence ;
22. les tests critiques passent.

---

# 84. ORDRE D’IMPLÉMENTATION

## Phase 1 — Coquille Feed

- écran ;
- navigation ;
- item abstrait ;
- swipe ;
- buffer.

## Phase 2 — Publicité simple

- média ;
- campagne ;
- affichage ;
- CTA ;
- interactions.

## Phase 3 — Attention

- session ;
- barre ;
- heartbeats ;
- complétion ;
- abandon.

## Phase 4 — Valeur

- devis ;
- réservation ;
- grand livre ;
- Wallet ;
- animation.

## Phase 5 — Quotas et abonnements

- consommation ;
- compteurs ;
- fin de quota ;
- upgrade.

## Phase 6 — Matching

- critères ;
- consentement ;
- classes ;
- explication.

## Phase 7 — Alertes

- cercles ;
- rail ;
- insertion ;
- priorité.

## Phase 8 — Explorer et interactions

- recherche ;
- catégories ;
- commentaires ;
- partage ;
- sauvegarde.

## Phase 9 — Administration

- cadence ;
- modération ;
- métriques ;
- configuration.

## Phase 10 — Stabilisation

- réseau faible ;
- accessibilité ;
- sécurité ;
- performance ;
- captures.

---

# 85. PREMIÈRE VERTICALE À LIVRER

```text
Utilisateur Gold
→ campagne Orange Cocody
→ matching réussi
→ gain 175 WP affiché
→ valeur réservée
→ vidéo 30 s
→ barre complète
→ preuve validée
→ Wallet +175 WP
→ animation
→ historique
→ budget campagne diminué
→ reporting mis à jour
```

Cette démonstration doit fonctionner réellement.

---

# 86. DIRECTIVE POUR CLAUDE CODE

1. lire les notes Abonnements, Matching, Modèle publicitaire, Wallet et Super moteur ;
2. auditer le nouveau dépôt ;
3. créer une abstraction `FeedItem` ;
4. ne pas mélanger tous les contenus dans une seule table métier ;
5. construire le parcours publicitaire vertical ;
6. relier la progression au serveur ;
7. ne jamais créditer côté client ;
8. respecter les quotas ;
9. intégrer les Alertes sans gain ;
10. produire les écrans ;
11. exécuter les tests ;
12. fournir une vidéo ou captures de la verticale.

---

# 87. DÉCISION FINALE

Le Feed Wasplex doit être capable de présenter des contenus différents tout en respectant leurs natures.

La règle finale est :

```text
Publicité
→ attention prouvée
→ valeur

Alerte
→ protection
→ aucune valeur publicitaire

Information
→ utilité
→ aucune confusion commerciale
```

Le Feed doit rester fluide, captivant, transparent et financièrement exact.

> **Une publicité n’est rémunérée que lorsqu’elle est financée, réservée, réellement regardée, validée et inscrite au grand livre. Une alerte reste prioritaire lorsqu’une vie ou la protection publique l’exige.**
