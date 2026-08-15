# P018-A.2 — Live temps réel

**Décision :** 2026-08-15  
**Statut :** implémentation  
**Dépend de :** P018-A.1 — Live réservé au Studio annonceur

## 1. Objectif

Transformer la fondation Live de P018-A.1 en une vraie expérience audiovisuelle temps réel :

```text
Studio annonceur
→ aperçu caméra + micro
→ lancer le Live
→ publier audio/vidéo en WebRTC
→ recevoir des spectateurs
→ accepter ou refuser les demandes de montée
→ plusieurs intervenants visibles
→ faire redescendre un intervenant
→ terminer

Membre
→ Live
→ entrer dans la salle
→ voir et entendre le direct
→ demander à monter
→ si accepté, activer caméra + micro
→ participer
→ redescendre ou quitter
```

La vidéo devient la surface principale du Live. Les cartes de cycle de vie de P018-A.1 restent utiles pour la programmation et l'historique, mais elles ne représentent plus l'expérience en direct.

## 2. Transport média

P018-A.2 adopte **LiveKit** comme transport WebRTC derrière une frontière Wasplex.

Responsabilités LiveKit :

- salle audio/vidéo temps réel ;
- publication caméra et microphone ;
- souscription aux pistes distantes ;
- adaptation de qualité ;
- permissions de publication dynamiques ;
- reconnexion média.

Responsabilités Wasplex :

- identité ;
- organisation annonceur ;
- propriétaire du Live ;
- cycle de vie ;
- sessions spectateurs ;
- demandes de montée ;
- audit ;
- futur calcul d'attention et économie WP.

LiveKit n'est donc pas la source de vérité économique de Wasplex.

## 3. Salle et identités

Une salle média utilise la référence :

```text
wasplex-live-{live_id}
```

Une identité média utilise :

```text
account-{account_id}
```

Les secrets LiveKit restent côté Laravel. Le navigateur reçoit uniquement un jeton de connexion court.

## 4. Permissions

### Hôte

```text
roomJoin = true
canSubscribe = true
canPublish = true
canPublishData = false
```

### Spectateur

```text
roomJoin = true
canSubscribe = true
canPublish = false
canPublishData = false
```

### Spectateur accepté sur scène

Wasplex appelle l'API serveur `UpdateParticipant` et passe :

```text
canPublish = true
```

Quand l'hôte fait redescendre l'intervenant, ou quand l'intervenant redescend lui-même :

```text
canPublish = false
```

La dépublication des pistes est alors contrôlée par le transport média, tandis que Wasplex conserve l'historique de la décision.

## 5. Demandes de montée

Nouvelle table :

```text
live_stage_requests
```

États :

```text
pending
approved
rejected
withdrawn
lowered
```

Une demande contient :

- Live ;
- compte membre ;
- statut ;
- date de demande ;
- date de résolution ;
- compte ayant résolu la demande.

Événements d'audit principaux :

```text
LiveStageRequested
LiveStageWithdrawn
LiveStageApproved
LiveStageRejected
LiveStageLowered
LiveStageLeft
```

## 6. API média

### Spectateur

```text
POST   /api/lives/{live}/media-token
POST   /api/lives/{live}/stage-request
DELETE /api/lives/{live}/stage-request
POST   /api/lives/{live}/stage-request/leave
```

Le jeton spectateur n'est délivré qu'après une vraie session `live_viewer_sessions` active.

### Studio annonceur

```text
POST /api/advertiser/lives/{live}/media-token
GET  /api/advertiser/lives/{live}/stage-requests
POST /api/advertiser/lives/{live}/stage-requests/{stageRequest}/approve
POST /api/advertiser/lives/{live}/stage-requests/{stageRequest}/reject
POST /api/advertiser/lives/{live}/stage-requests/{stageRequest}/lower
```

Les routes Studio conservent le contrôle d'espace annonceur et les capacités organisationnelles de P018-A.1.

## 7. Interface

### Studio annonceur

Avant démarrage :

- aperçu caméra et microphone du navigateur ;
- titre, description et programmation ;
- bouton `Lancer le Live`.

Pendant le direct :

- vidéo dominante ;
- caméra et microphone ;
- compteur spectateurs ;
- demandes de montée ;
- accepter/refuser ;
- liste des intervenants montés ;
- faire redescendre ;
- pause/reprise/fin.

### Membre

Surface mobile verticale :

- vidéo dominante ;
- badge `LIVE` ;
- compteur spectateurs ;
- son ;
- bouton `Monter dans le Live` ;
- état de demande ;
- activation caméra/micro après acceptation ;
- bouton `Descendre du Live` ;
- sortie de salle.

## 8. Configuration

Variables serveur :

```text
LIVEKIT_URL=wss://live.wasplex.com
LIVEKIT_API_KEY=...
LIVEKIT_API_SECRET=...
```

Le démarrage d'un Live échoue explicitement si le transport média n'est pas configuré. Wasplex ne doit plus afficher un Live `live` sans diffusion réelle disponible.

Le client navigateur est chargé via un adaptateur Wasplex qui épingle `livekit-client` en version `2.21.0`. Cette frontière permet de remplacer ensuite le chargement CDN par le paquet npm sans modifier les composants métier.

La politique navigateur autorise caméra et microphone uniquement au même site (`self`) ; la géolocalisation reste désactivée.

## 9. Hors périmètre

P018-A.2 ne couvre pas encore :

- commentaires publics ;
- réactions flottantes ;
- cadeaux ;
- sponsorisation ;
- rémunération WP ;
- attention vérifiée ;
- replay/egress ;
- modération sociale avancée ;
- partage d'écran ;
- plus de six intervenants simultanés dans l'UI initiale.

Ces couches viennent après validation réelle annonceur → spectateur → montée sur scène.
