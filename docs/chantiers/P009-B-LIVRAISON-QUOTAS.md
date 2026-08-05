# P009-B — LIVRAISON PUBLICITAIRE ET QUOTAS

**Statut :** `in_progress`  
**Branche :** `agent/p009b-delivery-quotas`  
**Base :** `main@2d1dc7b14e3e1f00677faf7996b726af47a9a501`  
**Dépendances :** P004, P006, P007, P008 et P009-A déployés  
**Date d’ouverture :** 5 août 2026

## 1. Objet

P009-B construit le pont de distribution entre le Matching protégé P008 et le Super Moteur de valeur P009-A.

Il doit sélectionner une campagne réellement livrable, enregistrer une livraison publicitaire unique, consommer exactement une fois le quota applicable et préparer le contenu que P009-C affichera dans le Feed.

```text
session Feed authentifiée
→ classe et quota actifs
→ Matching P008 éligible
→ campagne approuvée, active et financée
→ créatif livrable
→ contrôle fréquence et fatigue
→ réservation courte de livraison
→ AdDelivered immuable
→ consommation idempotente du quota
→ candidat prêt pour le Feed P009-C
```

P009-B ne crédite aucun Wallet. Le démarrage de la tentative rémunérée et le règlement restent la responsabilité de P009-A.

## 2. Invariants

1. Une campagne non approuvée, suspendue, hors période, non financée ou sans créatif exploitable n’est jamais livrée.
2. Seul un Matching P008 `eligible`, appartenant au compte authentifié et lié à la version active de campagne, peut être livré.
3. L’annonceur ne reçoit jamais l’identité du compte livré.
4. Une livraison possède une clé d’idempotence unique et ne consomme le quota qu’une seule fois.
5. Le quota est consommé par l’événement serveur `AdDelivered`, jamais par une simple requête de sélection inaboutie.
6. Une sélection réservée mais non livrée expire sans consommer de quota.
7. Les contrôles de fréquence et de fatigue s’appuient sur P008 ; P009-B ne crée pas une seconde source de vérité concurrente.
8. Une livraison ne crée aucune transaction Ledger, aucune opération Wallet et aucune valeur utilisateur.
9. Le démarrage d’une tentative P009-A exige une livraison valide, mais la livraison seule ne démarre pas automatiquement une preuve d’attention.
10. Les quotas, classes, plans et versions économiques proviennent de P004 ; aucun nombre commercial n’est codé silencieusement dans P009-B.
11. Tous les compteurs sont atomiques, non négatifs et protégés contre les courses concurrentes.
12. Le rejeu d’une même livraison retourne le même résultat sans double consommation.

## 3. Sources canoniques

- `docs/ROADMAP-INDEX.md` ;
- `docs/chantiers/P004-RAPPORT.md` ;
- `docs/chantiers/P006-CHANTIER.md` ;
- `docs/chantiers/P008-CHANTIER.md` ;
- `docs/chantiers/P009-A-SUPER-MOTEUR-VALEUR.md` ;
- code déployé des modules `EconomicConfiguration`, `Campaign`, `Advertising` et `ValueEngine`.

Ordre d’autorité : décision explicite du fondateur → documents métier → roadmap → chantier → code.

## 4. Périmètre inclus

### 4.1. Session de distribution

- création ou reprise d’une session Feed authentifiée ;
- marché, devise, territoire et classe économique courante ;
- référence à la souscription et au compteur de quota actifs ;
- expiration courte et reprise idempotente ;
- aucun stockage du SmartProfile complet.

### 4.2. Sélection de candidats

- lecture des Matchings P008 éligibles du compte ;
- validation de la campagne P006/P007 et de sa version active ;
- validation du financement et de la réservation Wallet existante ;
- sélection d’un créatif actif et compatible avec le futur lecteur ;
- exclusion des campagnes déjà trop fréquentes ou fatiguées ;
- ordre déterministe et explicable ;
- aucun export de liste nominative.

### 4.3. Réservation courte de livraison

- verrou ou bail court avant émission de la réponse ;
- prévention de la double sélection concurrente ;
- expiration automatique du bail ;
- libération sans quota si la livraison n’est pas finalisée ;
- aucune réservation financière supplémentaire.

### 4.4. Événement `AdDelivered`

- enregistrement serveur append-only ;
- lien vers session, compte, Matching, campagne, version et créatif ;
- horodatage serveur ;
- clé d’idempotence ;
- état `delivered`, `expired`, `invalidated` ou `superseded` ;
- empreinte minimale du contexte livré ;
- événement interne destiné à P008, P009-A et au futur reporting.

### 4.5. Consommation des quotas P004

- résolution du compteur de quota actif ;
- consommation atomique d’une unité par livraison réussie ;
- protection contre le dépassement et les valeurs négatives ;
- conservation de la version économique appliquée ;
- rejeu sans double décrément ;
- absence de quota : aucune livraison rémunérable ;
- expiration d’une réservation de livraison : aucune consommation.

### 4.6. Mise à jour de fréquence et fatigue

- appel contractuel au module Advertising P008 après livraison ;
- incrément de fréquence uniquement après `AdDelivered` ;
- progression de fatigue selon la configuration publiée ;
- retrait de consentement ou suspension immédiatement opposable aux livraisons futures ;
- aucune divulgation de règle antifraude au client.

### 4.7. Contrat de sortie vers P009-C

La sortie minimale contient :

```text
delivery_id
feed_session_id
match_id
campaign_id
campaign_version_id
creative_id
creative_type
media_reference
cta_reference
explanation_reference
quota_remaining
value_preview
expires_at
```

`value_preview` provient du devis immuable calculable par P009-A, mais aucune tentative rémunérée n’est encore créée par la simple sélection.

## 5. Périmètre exclu

P009-B ne livre pas encore :

- le design final du Feed desktop ou mobile — P009-C ;
- le lecteur vidéo ou image — P009-C ;
- les heartbeats d’attention — P009-A/P009-D ;
- le règlement financier — P009-A ;
- l’antifraude multi-appareils avancée — P009-D/P010 ;
- WebSocket, animation Wallet et notifications temps réel — P009-E/P011 ;
- reporting complet annonceur ou fondateur — P009-F/P012 ;
- retraits utilisateur — P011.

## 6. Modèle de données proposé

- `advertising_feed_sessions` : session de distribution authentifiée ;
- `advertising_delivery_leases` : réservation courte et concurrentielle d’un candidat ;
- `advertising_deliveries` : livraison canonique et idempotente ;
- `advertising_delivery_events` : historique append-only des transitions ;
- `advertising_quota_consumptions` : preuve de consommation unique liée au compteur P004 ;
- `advertising_delivery_outbox_events` : événements fiables après commit.

Les tables P004, P006, P008 et P009-A restent propriétaires de leurs données. P009-B ne les duplique pas.

## 7. États

### Session Feed

```text
active → expired
   └──→ closed
```

### Bail de livraison

```text
reserved → delivered
    └────→ expired
    └────→ released
```

### Livraison

```text
prepared → delivered
    └────→ invalidated
    └────→ superseded
```

Une livraison `delivered` est immuable. Une correction crée un événement compensatoire sans réécrire l’historique.

## 8. Contrats applicatifs

### Entrées

- `ActiveEconomicEntitlementProjection` ;
- `EligibleAdvertisingMatchProjection` ;
- `ApprovedCampaignDeliveryProjection` ;
- `ActiveCampaignFundingProjection` ;
- `ActiveCreativeProjection` ;
- `AdvertisingFrequencyStateProjection`.

### Services

- `FeedSessionService` ;
- `AdvertisingCandidateSelector` ;
- `AdvertisingDeliveryLeaseService` ;
- `AdvertisingDeliveryService` ;
- `AdvertisingQuotaConsumptionService` ;
- `AdvertisingDeliveryOutbox`.

### Événements

- `FeedSessionStarted` ;
- `AdvertisingDeliveryReserved` ;
- `AdDelivered` ;
- `AdvertisingDeliveryExpired` ;
- `AdvertisingQuotaConsumed` ;
- `AdvertisingFrequencyAdvanced`.

Les événements transportent uniquement les identifiants techniques et les versions nécessaires.

## 9. API minimale

Les routes exactes peuvent être ajustées selon les conventions existantes, mais le contrat doit couvrir :

```text
POST /api/feed/sessions
POST /api/feed/sessions/{session}/next
GET  /api/feed/deliveries/{delivery}
POST /api/feed/deliveries/{delivery}/release
```

Exigences :

- authentification obligatoire ;
- isolation stricte du compte ;
- idempotency key obligatoire pour la sélection et la livraison ;
- aucune identité exposée à l’annonceur ;
- réponses adaptées au futur frontend P009-C ;
- erreurs métier structurées sans divulgation sensible.

## 10. Ordre de traitement obligatoire

```text
compte authentifié et actif
→ session Feed valide
→ souscription et classe actives
→ quota disponible
→ Matching P008 eligible et encore valide
→ campagne approuvée et dans sa période
→ version de campagne cohérente
→ financement et réservation actifs
→ créatif exploitable
→ fréquence et fatigue autorisées
→ bail de livraison
→ création idempotente de la livraison
→ consommation atomique du quota
→ mise à jour P008 fréquence/fatigue
→ événement outbox
→ réponse au Feed
```

Toute défaillance avant `AdDelivered` libère le bail et consomme zéro quota.

## 11. Cas de référence

### Utilisateur

```text
Classe : GOLD
Quota actif : disponible
Matching : eligible
Zone approximative : Cocody
Consentements : actifs
```

### Campagne

```text
Annonceur : Orange
Statut : approved
Financement : actif
Créatif : vidéo valide
Classes : GOLD et PLATINUM
Période : active
```

### Résultat attendu

- une session Feed est créée ;
- une livraison unique est produite ;
- `AdDelivered` est enregistré une fois ;
- le quota GOLD diminue exactement d’une unité ;
- la fréquence P008 progresse ;
- aucune transaction Ledger n’est créée ;
- aucun Wallet n’est crédité ;
- le futur lecteur P009-C peut démarrer ensuite une tentative P009-A.

## 12. Tests obligatoires

- session créée et reprise idempotente ;
- compte sans souscription ou classe active ;
- quota disponible, épuisé et concurrent ;
- deux requêtes simultanées ne consomment qu’une unité ;
- même clé d’idempotence retourne la même livraison ;
- Matching retiré, expiré, ineligible ou withheld ;
- consentement retiré entre sélection et livraison ;
- campagne suspendue, hors période ou version incohérente ;
- financement libéré ou réservation inactive ;
- créatif absent, suspendu ou incompatible ;
- fréquence atteinte et fatigue bloquante ;
- bail expiré sans consommation ;
- `AdDelivered` met à jour P008 une fois ;
- aucune opération Ledger/Wallet ;
- isolation inter-comptes ;
- outbox idempotente ;
- migrations et rollback PostgreSQL 17 ;
- Pint, Larastan niveau 8, Pest SQLite/PostgreSQL, Prettier, ESLint, TypeScript et Vite verts.

## 13. Critères d’acceptation

P009-B est acceptable lorsque :

1. un utilisateur authentifié avec quota disponible peut ouvrir une session Feed ;
2. le moteur ne sélectionne que des Matchings P008 encore éligibles ;
3. campagne, version, période, financement et créatif sont revalidés à la livraison ;
4. `AdDelivered` est créé exactement une fois ;
5. le quota P004 est consommé exactement une fois et ne devient jamais négatif ;
6. les courses concurrentes ne produisent ni double livraison ni double consommation ;
7. fréquence et fatigue P008 sont mises à jour après livraison seulement ;
8. une livraison échouée ou expirée consomme zéro quota ;
9. aucune transaction Ledger ni opération Wallet n’est créée ;
10. la sortie permet à P009-C d’afficher le contenu et à P009-A de démarrer ensuite une tentative rémunérée ;
11. les tests SQLite/PostgreSQL, le rollback, la sécurité et le build restent verts ;
12. le rapport de chantier et la procédure de déploiement sont fournis avant fusion.

## 14. Frontière avec les sous-phases suivantes

- **P009-C** consomme le contrat de livraison pour construire le Feed et le lecteur ;
- **P009-D** renforce les preuves, l’antifraude et les holds ;
- **P009-E** publie les événements en temps réel et anime le Wallet ;
- **P009-F** supervise la santé, les incidents et les rapprochements.

P009-B doit rester un module de distribution transactionnelle, sans devenir propriétaire du profil, de la campagne, du Ledger ou du Wallet.
