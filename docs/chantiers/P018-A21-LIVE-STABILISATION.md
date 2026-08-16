# P018-A.2.1 — Stabilisation du Live temps réel

**Statut :** correction de stabilisation avant P018-B  
**Base :** P018-A.2 — LiveKit / WebRTC  
**Impact économique :** aucun — pas de WP, Wallet, sponsorisation ni Grand Livre

## Objectif

P018-A.2.1 corrige les écarts observés lors du premier test réel LiveKit sans élargir le périmètre fonctionnel du Live standard.

Le comportement cible reste :

```text
Hôte annonceur
→ rejoint la room
→ état temps réel connecté
→ active caméra / microphone indépendamment
→ diffuse

Spectateur
→ rejoint la room sans publier
→ regarde l'hôte sans apparaître lui-même
→ demande à monter
→ l'hôte accepte
→ cette connexion précise obtient canPublish=true
→ le spectateur active caméra / microphone
→ il apparaît sur scène
```

## Corrections

- la connexion LiveKit est considérée établie dès `room.connect()` ; l'autorisation caméra/micro ne maintient plus l'écran sur « Connexion au direct… » ;
- les états connexion, caméra et microphone sont affichés séparément ;
- le participant local d'un simple spectateur n'est pas rendu dans la grille vidéo ;
- lorsqu'aucun intervenant distant n'est encore visible, le spectateur voit un état d'attente explicite ;
- chaque montage du composant génère un `connection_id` UUID ;
- l'identité LiveKit est dérivée côté serveur de l'account, du rôle technique (`host`/`viewer`) et de cette connexion ;
- une demande de scène mémorise l'identité LiveKit exacte à promouvoir ;
- l'acceptation et la descente modifient uniquement les permissions de cette connexion, pas toutes les connexions du compte ;
- les anciennes demandes de scène sans identité fournisseur conservent un fallback compatible avec l'identité A.2 historique.

## Données

`live_stage_requests` reçoit :

```text
provider_participant_identity nullable string(190)
```

Le champ est nullable uniquement pour compatibilité avec les lignes créées avant A.2.1. Les nouvelles demandes issues de l'interface temps réel le renseignent systématiquement.

## Garde-fous

- `connection_id` est obligatoire et validé comme UUID avant émission d'un token média ou création d'une demande de scène ;
- le navigateur ne choisit jamais directement l'identité LiveKit finale ;
- l'identité est reconstruite côté Laravel avec le compte authentifié ;
- une invitation acceptée sur une connexion n'accorde pas automatiquement la publication à une autre connexion du même compte ;
- aucun secret LiveKit n'est exposé.

## Validation attendue

- syntaxe PHP ;
- formatage PHP / Vue ;
- TypeScript / build frontend ;
- tests Live existants ;
- nouveaux tests : identités distinctes par connexion, `connection_id` requis, promotion ciblée de la connexion ayant demandé la scène.

Les essais caméra/micro réels multi-appareils seront rejoués lors de la stabilisation finale du module Live complet.
