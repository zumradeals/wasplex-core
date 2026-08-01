# WASPLEX — MOTEUR DE MATCHING ET DISTRIBUTION PUBLICITAIRE

**Fichier cible :** `docs/04-publicite/04-moteur-matching-et-distribution-publicitaire-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Principe :** l’annonceur choisit des critères, Wasplex sélectionne une audience consentante sans révéler les personnes  
**Source du profil :** Mon Espace intelligent  
**Sortie :** campagne éligible, gain exact, réservation, diffusion et preuve

---

# 1. Objet

Ce document définit le moteur qui relie :

- profil volontaire ;
- consentements ;
- intérêts ;
- possessions ;
- usages ;
- projets ;
- territoires ;
- abonnement ;
- campagne ;
- budget ;
- Feed ;
- preuve d’attention ;
- Wallet.

Le moteur ne vend jamais une liste de personnes. Il produit une mise en correspondance protégée entre une campagne et une audience consentante.

# 2. Chaîne complète

```text
Mon Espace intelligent
→ profil volontaire
→ consentements actifs
→ taxonomies
→ segment agrégé
→ campagne
→ estimation
→ matching individuel protégé
→ distribution dans le Feed
→ progression réelle
→ événement qualifié
→ super moteur de valeur
→ Wallet crédité
```

# 3. Mon Espace intelligent

Mon Espace est la principale source déclarative du profil publicitaire.

L’utilisateur peut indiquer volontairement :

- produits possédés ;
- services utilisés ;
- réseau télécom principal et secondaire ;
- centres d’intérêt ;
- projets d’achat ;
- besoins ;
- métier ;
- compétences ;
- secteurs ;
- résidence ;
- lieu de travail ;
- zones d’intérêt ;
- langues ;
- formats préférés ;
- catégories acceptées.

Exemple :

```text
Réseau principal : Orange
Réseau secondaire : MTN
Résidence : Cocody
Travail : Plateau
Projet : acheter un téléphone dans 6 mois
Intérêt : offres Internet
Classe Wasplex : Gold
```

# 4. Questions intelligentes

Mon Espace peut présenter des cartes :

```text
Quel réseau utilisez-vous principalement ?
[Orange] [MTN] [Moov Africa] [Autre]
```

Chaque question explique :

- sa finalité ;
- son caractère facultatif ;
- son influence sur la publicité ;
- comment modifier la réponse ;
- que l’information n’est pas remise nominativement à l’annonceur.

# 5. Données utilisables

## Déclarées

- réponse directe ;
- date ;
- source ;
- niveau de fraîcheur ;
- possibilité de correction.

## Déduites autorisées

- intérêt probable ;
- fréquence optimale ;
- préférence de format ;
- score de pertinence.

Elles restent explicables, contestables et limitées.

# 6. Données interdites

Ne jamais utiliser commercialement :

- Santé ;
- SOS ;
- Alertes ;
- historique judiciaire ;
- KYC ;
- dette ;
- difficulté Fonds ;
- pauvreté ;
- religion ;
- politique ;
- grossesse supposée ;
- orientation sexuelle ;
- vulnérabilité ;
- position intime précise ;
- données de mineur non autorisées.

# 7. Consentements

Consentements séparés :

- personnalisation publicitaire ;
- profil volontaire ;
- localisation approximative ;
- localisation précise ;
- notifications ;
- communications commerciales ;
- partenaires spécifiques ;
- catégories spéciales.

Le moteur vérifie le consentement à chaque matching.

Le retrait bloque les nouveaux usages et laisse une preuve.

# 8. Taxonomies

Les critères viennent de taxonomies administrées.

```text
telecom.network.orange
telecom.network.mtn
device.smartphone.owner
device.smartphone.purchase_intent
geo.ci.abidjan.cocody
interest.mobile_internet
economic_class.gold
```

Les textes libres ne doivent pas devenir des règles techniques.

# 9. Distinctions obligatoires

Le moteur distingue :

```text
possède un téléphone
utilise Orange
aime l’automobile
possède un véhicule
prévoit un achat
vit à Cocody
travaille à Cocody
s’intéresse à Cocody
```

Aucune fusion automatique de ces notions.

# 10. Segment annonceur

L’annonceur choisit des critères autorisés.

Wasplex calcule :

- audience approximative ;
- portée ;
- rareté ;
- classes économiques ;
- coût ;
- événements possibles ;
- fréquence ;
- risque de segment trop petit.

Aucun identifiant individuel n’est transmis.

# 11. Taille minimale

Un segment trop petit doit être :

- élargi ;
- arrondi ;
- masqué ;
- refusé.

Le seuil dépend du territoire, du nombre de critères, de la rareté, de la classe et du risque de réidentification.

# 12. Anti-réidentification

Contrôles :

- nombre minimal de profils ;
- suppression de filtres trop précis ;
- arrondis ;
- limites de requêtes ;
- regroupement géographique ;
- détection des recherches inhabituelles ;
- aucune exportation de membres.

# 13. Exemple Orange Cocody

```text
Secteur : Télécommunications
Réseau : Orange
Pays : Côte d’Ivoire
Ville : Abidjan
Commune : Cocody
Budget : 100 000 FCFA
Classes : Gold et Platine
```

Le moteur vérifie :

- consentement ;
- réseau déclaré ;
- territoire ;
- classe active ;
- quota restant ;
- fréquence ;
- campagne active ;
- budget ;
- non-duplication ;
- sécurité.

# 14. Ciblage des classes

L’annonceur peut cibler :

- tous ;
- payants ;
- Premium ;
- Gold ;
- Platine ;
- combinaison.

Le choix influence le prix, l’audience, la rareté, le nombre d’événements et la répartition de l’enveloppe.

Il ne donne jamais accès aux identités.

# 15. Éligibilité individuelle

Avant de proposer une publicité :

```text
campagne approuvée
et active
et dans les dates
et budget disponible
et classe ciblée
et quota disponible
et consentement actif
et profil correspondant
et fréquence respectée
et sécurité conforme
et événement non déjà réservé
```

# 16. Score de matching

Facteurs possibles :

- correspondance exacte ;
- fraîcheur ;
- projet déclaré ;
- fréquence ;
- fatigue ;
- engagement ;
- territoire ;
- format préféré.

Le score ne doit jamais utiliser de données interdites ni produire une décision sensible hors publicité.

# 17. Priorité de distribution

```text
éligibilité dure
→ disponibilité budgétaire
→ pertinence
→ fréquence
→ diversité
→ fatigue publicitaire
→ opportunité de campagne
```

Le moteur doit éviter la répétition excessive et la domination d’un annonceur.

# 18. Fréquence

Limites configurables :

- campagne ;
- annonceur ;
- catégorie ;
- jour ;
- semaine ;
- cycle ;
- utilisateur.

Un annonceur ne doit pas contourner les limites en dupliquant ses campagnes.

# 19. Quota mensuel

Avant distribution :

- lire la classe ;
- lire le cycle ;
- vérifier le compteur ;
- refuser si quota épuisé.

Après affichage réel :

- consommer une unité ;
- écrire `AdDelivered`.

En cas de défaut Wasplex confirmé :

- restaurer l’unité ;
- écrire `AdQuotaRestored`.

# 20. Gain avant diffusion

Avant de montrer la publicité, le moteur obtient :

- prix de l’événement ;
- gain exact ;
- enveloppe source ;
- classe ;
- idempotency key ;
- réservation.

Le Feed affiche le montant exact promis.

# 21. Réservation de valeur

```text
enveloppe disponible
→ réservation
→ promesse de gain
```

Abandon ou invalidité :

```text
réservation libérée
→ aucun gain
```

Validation :

```text
réservation consommée
→ Wallet crédité
```

# 22. Barre de progression

La barre représente :

- temps visible réel ;
- lecture active ;
- seuil requis ;
- perte de focus ;
- interruption ;
- action attendue.

Elle n’est jamais décorative.

# 23. Événement qualifié

Formats :

- vidéo complétée ;
- durée minimale ;
- image visible ;
- clic valide ;
- appel initié ;
- itinéraire ;
- formulaire ;
- autre action catalogue.

Chaque format possède une preuve distincte.

# 24. Super moteur temps réel

Après validation serveur :

```text
preuve acceptée
→ événement qualifié
→ enveloppe consommée
→ part Wasplex reconnue
→ part utilisateur créditée
→ grand livre écrit
→ Wallet mis à jour
→ icône Wallet animée
→ compteur incrémenté
```

Tout doit être atomique ou compensable.

# 25. Retour visuel

Après confirmation serveur uniquement :

- pulse du Wallet ;
- compteur qui augmente ;
- son optionnel ;
- vibration optionnelle ;
- martin-pêcheur transportant la récompense ;
- animation courte et non bloquante.

# 26. Feed après quota

Aucune publicité commerciale.

Restent possibles :

- Alertes ;
- Santé ;
- conseils ;
- informations ;
- Live ;
- futurs contenus non publicitaires.

# 27. Modèle de données

```text
advertising_profile_answers
advertising_profile_facts
advertising_profile_fact_versions
advertising_consents
advertising_taxonomies
advertising_segments
advertising_segment_rules
advertising_segment_estimates
advertising_campaign_targeting
advertising_matches
advertising_delivery_reservations
advertising_deliveries
advertising_qualified_events
advertising_frequency_counters
advertising_fatigue_scores
advertising_match_audits
```

# 28. API Mon Espace

```text
GET    /api/me/advertising-profile
PATCH  /api/me/advertising-profile
GET    /api/me/advertising-profile/questions
POST   /api/me/advertising-profile/answers
GET    /api/me/advertising-consents
PATCH  /api/me/advertising-consents
GET    /api/me/advertising-profile/explanations
```

# 29. API annonceur

```text
POST   /api/advertiser/segments/estimate
GET    /api/advertiser/targeting/taxonomies
POST   /api/advertiser/campaigns/{id}/targeting
GET    /api/advertiser/campaigns/{id}/estimate
```

# 30. API Feed

```text
GET    /api/feed/next
POST   /api/feed/ads/{id}/delivered
POST   /api/feed/ads/{id}/progress
POST   /api/feed/ads/{id}/qualified
POST   /api/feed/ads/{id}/abandoned
```

# 31. Événements métier

```text
AdvertisingProfileUpdated
AdvertisingConsentGranted
AdvertisingConsentWithdrawn
SegmentEstimated
CampaignMatched
AdValueReserved
AdDelivered
AdProgressUpdated
QualifiedAttentionValidated
QualifiedAttentionRejected
AdValueReleased
AdQuotaConsumed
AdQuotaRestored
WalletRewardConfirmed
```

# 32. Administration

Configurer :

- taxonomies ;
- questions ;
- finalités ;
- seuils ;
- critères interdits ;
- fréquence ;
- fatigue ;
- score ;
- territoires ;
- classes ;
- quotas ;
- règles de réservation.

# 33. Tests

- profil volontaire ;
- retrait de consentement ;
- Orange/Cocody ;
- distinction possession/intérêt ;
- classe Gold ;
- quota épuisé ;
- segment trop petit ;
- réidentification ;
- fréquence ;
- budget insuffisant ;
- réservation ;
- abandon ;
- validation ;
- double crédit impossible ;
- Santé exclue ;
- KYC exclu ;
- animation après confirmation ;
- aucune publicité après quota.

# 34. Critères d’acceptation

1. Mon Espace alimente le profil ;
2. consentement vérifié ;
3. segments agrégés ;
4. aucune identité exposée ;
5. classes ciblables ;
6. ciblage payant ;
7. quota vérifié ;
8. gain exact calculé avant diffusion ;
9. réservation créée ;
10. barre liée à l’attention réelle ;
11. Wallet crédité immédiatement ;
12. double débit impossible ;
13. données sensibles exclues ;
14. reporting agrégé ;
15. tests verts.

# 35. Décision finale

Le moteur doit savoir :

```text
qui peut recevoir quoi
à quel moment
pour quelle raison
avec quel gain
sur quelle enveloppe
et avec quelle preuve
```

sans jamais remettre la personne à l’annonceur.
