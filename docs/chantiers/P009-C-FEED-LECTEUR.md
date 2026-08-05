# P009-C — FEED UTILISATEUR ET LECTEUR PUBLICITAIRE

**Statut :** `in_progress`  
**Branche :** `agent/p009c-user-feed-player`  
**Base :** `main@d6fbab314531a8173ebbba4bc582dbe82355d883`  
**Dépendances :** P005, P007, P008, P009-A et P009-B déployés  
**Date d’ouverture :** 5 août 2026

## 1. Objet

P009-C construit l’expérience visible du premier Feed Wasplex à partir des livraisons préparées par P009-B.

```text
utilisateur authentifié
→ ouverture de « Pour toi »
→ création ou reprise de session Feed
→ préparation d’un candidat P009-B
→ préchargement du média sans quota
→ confirmation lorsque le média est réellement affichable
→ AdDelivered côté serveur
→ lecteur image ou vidéo
→ CTA, explication, masquer ou passer
→ prochain contenu
```

Le navigateur n’accorde aucun WP. Il présente uniquement le `value_preview` calculé par P009-A et attend les phases ultérieures pour la preuve d’attention et le crédit Wallet.

## 2. Principes fondateurs

1. Le Feed ne reçoit jamais le SmartProfile complet ni les données ayant conduit au Matching.
2. Un média préchargé mais jamais affiché ne consomme aucun quota.
3. `AdDelivered` est confirmé côté serveur uniquement lorsque le lecteur peut réellement présenter le média.
4. Une livraison confirmée reste immuable ; un swipe ou un dismiss ultérieur ne restaure pas silencieusement le quota.
5. Une préparation abandonnée est libérée sans quota.
6. Aucun mouvement Ledger, Wallet ou gain local n’est créé par P009-C.
7. Le gain potentiel est toujours présenté comme une valeur à obtenir sous conditions, jamais comme un solde acquis.
8. Le CTA est ouvert uniquement depuis la destination validée de la campagne.
9. Une seule vidéo peut jouer à la fois ; elle se met en pause quand l’onglet devient invisible.
10. Les préférences muet, économie de données et animations réduites sont respectées.
11. Une erreur réseau ou média ne doit pas pénaliser l’utilisateur.
12. L’interface est mobile-first tout en conservant un shell desktop cohérent.

## 3. Sources canoniques

- `docs/00-identite-visuelle-wasplex.md` ;
- `docs/08-feed-principal-wasplex.md` ;
- `docs/09-compte-universel-et-mon-espace-intelligent-wasplex.md` ;
- `docs/IMPLEMENTATION-ROADMAP-WASPLEX.md` ;
- `docs/chantiers/P009-B-LIVRAISON-QUOTAS.md` ;
- code déployé des modules `Distribution`, `Advertising`, `Identity`, `Wallet` et `ValueEngine`.

Ordre d’autorité : décision explicite du fondateur → notes métier → roadmap → chantier → code.

## 4. Périmètre inclus

### 4.1. Page « Pour toi »

- route utilisateur protégée `/mon-espace/pour-toi` ;
- shell Wasplex cohérent avec Mon Espace ;
- onglets supérieurs `Pour toi`, `Alertes` et `Explorer`, seuls `Pour toi` étant actif dans cette phase ;
- navigation basse vers Espace, Feed, Wallet et Profil ;
- résumé Wallet en lecture seule ;
- compteur de quota restant ;
- état d’absence de candidat et état quota atteint.

### 4.2. Session Feed

- création idempotente via P009-B ;
- conservation locale limitée de l’identifiant de session ;
- reprise après rechargement tant que la session est active ;
- nouvelle session si expiration ou changement de compte ;
- aucune donnée publicitaire privée stockée côté client.

### 4.3. Préparation et confirmation

- nouvel endpoint de préparation utilisant `reserveNext()` sans `deliver()` ;
- endpoint de confirmation utilisant `deliver()` ;
- conservation de l’endpoint P009-B historique `next` pour compatibilité ;
- confirmation après chargement image ou événement vidéo `canplay` ;
- libération d’une préparation en cas d’échec, timeout ou abandon avant affichage ;
- clés d’idempotence distinctes et stables par opération.

### 4.4. Lecteur publicitaire

- image plein écran avec état de chargement ;
- vidéo verticale avec `playsinline`, autoplay muet et commandes accessibles ;
- bouton muet/non muet ;
- pause lorsque la page est masquée ;
- relance manuelle si l’autoplay est bloqué ;
- aucun lancement simultané de plusieurs médias ;
- indicateur publicitaire explicite ;
- gain potentiel et quota restant ;
- CTA ;
- lien « Pourquoi cette publicité ? » ;
- bouton passer/masquer.

### 4.5. Réseau faible et économie de données

- détection informative de l’état en ligne/hors ligne ;
- timeout de chargement ;
- message clair et retry manuel ;
- préférence locale `Économie de données` ;
- pas de préchargement multiple ;
- buffer limité au contenu courant et, au maximum, au prochain candidat dans une phase ultérieure ;
- libération serveur si le média préparé devient inutilisable.

### 4.6. Dismiss et navigation

- une préparation non confirmée est libérée ;
- une livraison déjà confirmée reçoit un événement append-only `FeedItemDismissed` sans mutation financière ;
- le CTA et l’explication ne déclenchent aucun WP ;
- le passage au suivant ne crée jamais deux confirmations pour le même contenu.

### 4.7. Accessibilité

- actions nommées pour lecteur d’écran ;
- focus visible ;
- contraste conforme aux tokens Wasplex ;
- alternatives textuelles ;
- respect de `prefers-reduced-motion` ;
- progression et états non dépendants uniquement de la couleur ;
- commandes accessibles au clavier sur desktop.

## 5. Périmètre exclu

P009-C ne contient pas :

- la preuve d’attention qualifiée et ses heartbeats renforcés ;
- le règlement Ledger ou Wallet ;
- l’animation de crédit après commit ;
- l’antifraude multi-appareils avancée ;
- les commentaires, likes, partages et signalements complets ;
- les contenus Alertes, Santé, Explorer ou Live réels ;
- le reporting annonceur et fondateur ;
- les notifications temps réel ;
- les retraits utilisateur.

Les onglets non livrés restent visibles comme réservations d’architecture, clairement marqués indisponibles.

## 6. Contrats backend

### Route Inertia

```text
GET /mon-espace/pour-toi
```

Props minimales :

```text
account
activeSpace
spaces
walletSummary
feedConfig
endpoints
```

### API P009-C

```text
POST /api/feed/sessions
POST /api/feed/sessions/{session}/prepare
POST /api/feed/deliveries/{delivery}/confirm
GET  /api/feed/deliveries/{delivery}
POST /api/feed/deliveries/{delivery}/release
POST /api/feed/deliveries/{delivery}/dismiss
```

L’API historique suivante reste disponible :

```text
POST /api/feed/sessions/{session}/next
```

### Sortie lecteur

```text
delivery_id
feed_session_id
creative_type
media_reference
cta_reference
explanation_reference
quota_remaining
value_preview
status
expires_at
delivered_at
```

## 7. États frontend

```text
booting
session_ready
preparing
media_loading
awaiting_confirmation
active
paused
offline
empty
quota_exhausted
failed
```

Une erreur ne doit pas créer de boucle automatique agressive.

## 8. États d’une carte publicitaire

```text
prepared → media_ready → confirming → delivered → dismissed
    └──────────────→ released
    └──────────────→ failed
```

Les états frontend ne remplacent jamais les états canoniques de P009-B.

## 9. Expérience mobile

- média occupant la majorité de la hauteur utile ;
- contrôles superposés mais lisibles ;
- CTA accessible au pouce ;
- navigation basse stable ;
- aucune zone critique masquée par les barres système ;
- gestes verticaux optionnels, avec boutons toujours disponibles ;
- pas de scroll involontaire pendant une interaction lecteur.

## 10. Expérience desktop

- lecteur centré dans une colonne verticale ;
- panneaux latéraux discrets pour Wallet, quota et transparence ;
- commandes clavier ;
- conservation du langage visuel mobile sans étirer la vidéo sur tout l’écran.

## 11. Sécurité

- toutes les routes exigent compte authentifié, session d’identité et espace utilisateur ;
- isolation stricte par `account_id` ;
- aucune URL média fournie par le client ;
- aucune destination CTA fournie par le client ;
- validation des clés d’idempotence ;
- aucune confiance dans le statut frontend pour consommer le quota ;
- aucune valeur calculée ou créditée côté client ;
- aucun détail de règle antifraude dans les erreurs ;
- CSP et politiques de sécurité existantes conservées.

## 12. Événements

- `FeedPageOpened` — télémétrie locale minimale, sans donnée sensible ;
- `AdvertisingDeliveryPrepared` ;
- `AdDelivered` — déjà canonique P009-B ;
- `FeedItemDismissed` ;
- `AdvertisingDeliveryReleased` ;
- `FeedMediaFailed` — audit technique sans contenu privé.

## 13. Tests obligatoires

### Backend

- route Feed refusée hors authentification ;
- route Feed refusée hors espace utilisateur ;
- props Wallet et endpoints corrects ;
- préparation ne consomme aucun quota ;
- confirmation consomme exactement une unité ;
- confirmation rejouée reste idempotente ;
- libération avant confirmation consomme zéro ;
- dismiss après livraison ne restaure ni ne consomme une seconde fois ;
- média ou campagne devenu invalide bloque la confirmation ;
- aucune opération Ledger ou Wallet créée ;
- isolation entre comptes ;
- session expirée et quota épuisé ;
- rollback de migration inchangé.

### Frontend

- format Prettier ;
- ESLint ;
- TypeScript ;
- build Vite ;
- rendu image ;
- rendu vidéo ;
- autoplay bloqué ;
- hors ligne/retry ;
- économie de données ;
- reduced motion ;
- CTA absent ou présent ;
- lien d’explication ;
- état vide et quota atteint.

### Qualité globale

- Pint ;
- Larastan niveau 8 ;
- Pest SQLite ;
- Pest PostgreSQL 17 ;
- migrations et rollback ;
- captures mobile et desktop avant validation finale.

## 14. Critères d’acceptation

P009-C est acceptable lorsque :

1. un utilisateur ouvre `/mon-espace/pour-toi` depuis Mon Espace ;
2. une session Feed est créée ou reprise ;
3. un candidat P009-B est préparé sans consommer de quota ;
4. une image ou vidéo réellement chargeable est confirmée ;
5. `AdDelivered` consomme exactement une unité ;
6. le gain potentiel est visible mais jamais présenté comme acquis ;
7. le CTA et l’explication fonctionnent sans révéler le profil ;
8. un échec avant affichage libère la préparation ;
9. un swipe après affichage ne produit aucun WP et ne double pas le quota ;
10. l’application hors ligne affiche un état compréhensible ;
11. le Wallet résumé reste une projection serveur en lecture seule ;
12. aucun mouvement financier n’est créé par le client ;
13. les tests et la CI sont verts ;
14. les captures mobile et desktop démontrent le parcours.

## 15. Déploiement prévu

- fusion uniquement après autorisation fondatrice ;
- récupération de `main` sur le VPS ;
- Composer sous PHP 8.4 ;
- `npm ci` et build Vite ;
- migration uniquement si le schéma évolue ;
- caches Laravel reconstruits ;
- workers redémarrés ;
- vérification de la route `/mon-espace/pour-toi` ;
- recette avec une campagne de démonstration approuvée et un utilisateur éligible.

## 16. Rollback

1. mettre l’application en maintenance ;
2. revenir au commit P009-B déployé ;
3. reconstruire Composer et Vite ;
4. vider et reconstruire les caches ;
5. remettre l’application en ligne ;
6. conserver les livraisons P009-B déjà confirmées ;
7. ne jamais restaurer artificiellement un quota sans événement compensatoire contrôlé.
