# P018-A — Live standard Wasplex

**Statut :** implémentation temps réel en P018-A.2  
**Décision initiale :** 2026-08-15  
**Cadrage P018-A.1 :** création et pilotage exclusivement depuis le Studio annonceur  
**Transport P018-A.2 :** LiveKit / WebRTC  
**Sources :** `docs/11-live-wasplex.md`, `P018-A1-LIVE-ANNONCEUR.md`, `P018-A2-LIVE-TEMPS-REEL.md`

## 1. Objectif

Le Live standard est une vraie salle audiovisuelle temps réel, sans économie WP dans cette phase :

```text
Annonceur
→ espace annonceur actif
→ Studio annonceur
→ crée ou programme un Live
→ prévisualise caméra + micro
→ lance la diffusion WebRTC
→ pilote la scène et les invités

Membre
→ Feed
→ Live
→ voit les directs publics
→ rejoint
→ regarde et écoute
→ peut demander à monter
→ quitte
```

## 2. Doctrine d'espace

- création, programmation et pilotage : **Studio annonceur uniquement** ;
- consultation et présence : espace membre / Feed Live ;
- le Live appartient à l'organisation annonceur active ;
- le compte créateur reste l'acteur nominatif pour l'audit ;
- aucun bouton de création Live n'est exposé dans l'espace membre.

## 3. Inclus après P018-A.2

- création, programmation, démarrage, pause, reprise et fin ;
- rattachement à l'organisation annonceur active ;
- isolation entre organisations ;
- liste des Lives publics ;
- sessions spectateurs Wasplex ;
- transport audio/vidéo WebRTC LiveKit ;
- jetons média courts signés côté Laravel ;
- aperçu caméra + microphone côté Studio ;
- lecteur temps réel mobile-first côté membre ;
- hôte audio/vidéo ;
- demande d'un spectateur pour monter ;
- acceptation/refus par l'hôte ;
- promotion dynamique `canPublish=true` ;
- descente par l'hôte ou l'intervenant ;
- plusieurs intervenants vidéo dans la scène ;
- audit append-only des décisions essentielles ;
- aucun impact Ledger/Wallet.

## 4. Transport média

P018-A.1 avait volontairement laissé :

```text
provider = pending_adapter
```

P018-A.2 remplace cette frontière par :

```text
provider = livekit
provider_session_reference = wasplex-live-{live_id}
```

Un Live ne peut plus passer à l'état `live` si `LIVEKIT_URL`, `LIVEKIT_API_KEY` et `LIVEKIT_API_SECRET` ne sont pas configurés.

LiveKit transporte l'audio et la vidéo. Wasplex reste la source de vérité pour les identités, organisations, présences, demandes de scène, audit et futures règles économiques.

## 5. Permissions média

### Hôte

```text
roomJoin=true
canSubscribe=true
canPublish=true
```

### Spectateur

```text
roomJoin=true
canSubscribe=true
canPublish=false
```

### Intervenant accepté

Le backend Wasplex appelle l'API LiveKit `UpdateParticipant` pour accorder `canPublish=true`. La révocation de cette permission fait redescendre l'intervenant et dépublie ses pistes.

Les jetons de connexion sont courts afin de limiter la réutilisation de droits anciens, particulièrement en auto-hébergement.

## 6. Données

### `lives`

- compte créateur ;
- organisation annonceur ;
- titre/description ;
- catégorie/langue ;
- visibilité ;
- statut ;
- programmation ;
- durée prévue ;
- dates de début/fin ;
- politique replay.

### `live_stream_sessions`

- Live ;
- statut transport ;
- fournisseur `livekit` ;
- référence de salle ;
- début/pause/fin.

### `live_viewer_sessions`

- Live ;
- membre ;
- statut ;
- entrée ;
- dernière présence ;
- sortie.

### `live_stage_requests`

- Live ;
- membre ;
- `pending`, `approved`, `rejected`, `withdrawn`, `lowered` ;
- demande ;
- résolution ;
- acteur de résolution.

### `live_audit_events`

Journal des transitions du Live, entrées/sorties et décisions de scène.

## 7. API

### Spectateur

```text
GET    /api/lives
GET    /api/lives/{live}
POST   /api/lives/{live}/join
POST   /api/lives/{live}/leave
POST   /api/lives/{live}/media-token
POST   /api/lives/{live}/stage-request
DELETE /api/lives/{live}/stage-request
POST   /api/lives/{live}/stage-request/leave
```

### Studio annonceur

```text
GET   /api/advertiser/lives
POST  /api/advertiser/lives
PATCH /api/advertiser/lives/{live}
POST  /api/advertiser/lives/{live}/schedule
POST  /api/advertiser/lives/{live}/start
POST  /api/advertiser/lives/{live}/pause
POST  /api/advertiser/lives/{live}/resume
POST  /api/advertiser/lives/{live}/end
POST  /api/advertiser/lives/{live}/media-token
GET   /api/advertiser/lives/{live}/stage-requests
POST  /api/advertiser/lives/{live}/stage-requests/{stageRequest}/approve
POST  /api/advertiser/lives/{live}/stage-requests/{stageRequest}/reject
POST  /api/advertiser/lives/{live}/stage-requests/{stageRequest}/lower
```

## 8. Sécurité

- authentification obligatoire ;
- session non révoquée ;
- espace annonceur actif pour les routes Studio ;
- capacités annonceur contrôlées ;
- cohérence Live ↔ organisation active ;
- seul le créateur du Live pilote la scène en A.2 ;
- un spectateur doit avoir une session Wasplex active avant de recevoir un jeton média ;
- secrets LiveKit jamais exposés au navigateur ;
- jetons média courts ;
- caméra et microphone autorisés uniquement à l'origine Wasplex elle-même ;
- audit de toutes les décisions de montée/descente.

## 9. Garantie économique

P018-A.2 ne crée aucun WP, n'appelle ni le Wallet ni le Grand Livre, et ne réserve aucun budget annonceur.

## 10. Hors périmètre et suite

Restent hors de P018-A.2 :

- commentaires et réactions publics ;
- modération sociale avancée ;
- sponsorisation ;
- places rémunérées ;
- attention vérifiée ;
- cadeaux/pourboires ;
- replay média ;
- egress/enregistrement ;
- partage d'écran.

Progression recommandée :

1. validation réelle du transport P018-A.2 en environnement LiveKit ;
2. **P018-B — interactions et modération de base** ;
3. sponsorisation et financement ;
4. places rémunérées et attention vérifiée ;
5. Wallet / Grand Livre ;
6. replay et stabilisation.
