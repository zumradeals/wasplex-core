# P018-B — Interactions et modération de base du Live

**Statut :** implémentation préparée après P018-A.2.1  
**Base :** P018-A.2.1 — transport LiveKit/WebRTC stabilisé  
**Temps réel social :** Laravel Reverb / Echo  
**Impact économique :** aucun — pas de WP, Wallet, Grand Livre, sponsorisation ni place rémunérée

## 1. Objectif

P018-B transforme le Live audiovisuel déjà fonctionnel en une expérience sociale temps réel minimale, tout en conservant les responsabilités techniques séparées :

```text
LiveKit
→ audio / vidéo / permissions média

Wasplex + PostgreSQL
→ commentaires persistés
→ réponses
→ réglages d'interaction
→ signalements
→ restrictions locales au Live
→ audit

Laravel Reverb / Echo
→ nouveaux commentaires
→ mises à jour de commentaires
→ réactions éphémères
→ changements de réglages
→ restrictions de commentaire
→ compteur spectateurs
```

Wasplex reste la source de vérité. Les Data Channels LiveKit ne sont pas utilisés pour le social.

## 2. Périmètre fonctionnel

### Membre spectateur

Un membre ayant rejoint le Live peut :

- lire les commentaires visibles ;
- écrire un commentaire de 300 caractères maximum ;
- répondre à un commentaire visible ;
- envoyer une réaction légère (`❤️`, `👏`, `🔥`, `😊`) ;
- signaler un commentaire d'un autre compte ;
- continuer à regarder s'il est mis en silence ;
- recevoir en temps réel les nouveaux commentaires, réactions, changements de modération et le compteur spectateurs.

Un simple compte authentifié qui n'a pas de session spectateur active ne peut pas lire ni publier dans le canal social du Live.

### Hôte annonceur

Le créateur du Live, depuis l'organisation annonceur active propriétaire, peut :

- écrire et répondre ;
- épingler ou désépingler un commentaire ;
- masquer un commentaire ;
- ouvrir ou fermer les commentaires spectateurs ;
- choisir un mode lent de `0`, `2`, `5` ou `10` secondes ;
- mettre un membre en silence pour ce Live uniquement ;
- lever ce silence ;
- continuer à publier un message d'hôte lorsque les commentaires spectateurs sont fermés.

P018-B ne crée pas encore de rôle de co-modérateur séparé : le propriétaire reste le seul acteur de modération locale, conformément au garde-fou de P018-A.

## 3. Données

### `live_comments`

- Live ;
- auteur ;
- commentaire parent facultatif ;
- corps limité à 300 caractères ;
- état épinglé ;
- état masqué ;
- acteurs d'épinglage/masquage ;
- timestamps.

Les commentaires ne sont pas supprimés physiquement par la modération de base : ils sont masqués afin de conserver une trace cohérente.

### `live_comment_reports`

- Live ;
- commentaire ;
- compte déclarant ;
- catégorie ;
- note facultative ;
- unicité par commentaire + déclarant.

### `live_interaction_settings`

- Live unique ;
- commentaires activés/désactivés ;
- délai du mode lent ;
- dernier acteur de modification.

En absence de ligne, les valeurs par défaut restent :

```text
comments_enabled = true
slow_mode_seconds = 0
```

### `live_comment_restrictions`

- Live ;
- compte ;
- restriction active/inactive ;
- motif facultatif ;
- acteur de création ;
- levée et acteur de levée.

La restriction est locale au Live. Elle ne constitue pas une sanction globale de compte.

## 4. Événements temps réel

Canal privé :

```text
live.{liveId}
```

Accès autorisé uniquement :

- au propriétaire du Live ; ou
- à un membre disposant d'une `live_viewer_session` active (`watching` / `paused`).

Événements :

```text
live.comment.created
live.comment.updated
live.settings.updated
live.restriction.updated
live.reaction
live.viewer-count.changed
```

Les réactions sont éphémères et best-effort. Les commentaires et décisions de modération sont persistés avant leur diffusion temps réel.

Une panne Reverb ne doit pas annuler une écriture déjà persistée : la diffusion est traitée comme une couche de confort temps réel et l'état canonique reste récupérable par API.

## 5. API spectateur

```text
GET  /api/lives/{live}/comments
POST /api/lives/{live}/comments
POST /api/lives/{live}/reactions
POST /api/lives/{live}/comments/{comment}/report
```

Garde-fous :

- session spectateur active ;
- Live en cours pour publier/réagir ;
- commentaire parent appartenant au même Live et encore visible ;
- commentaires non gelés ;
- compte non silencé ;
- mode lent respecté ;
- throttling HTTP complémentaire.

## 6. API Studio annonceur

```text
GET    /api/advertiser/lives/{live}/comments
POST   /api/advertiser/lives/{live}/comments
POST   /api/advertiser/lives/{live}/reactions
PATCH  /api/advertiser/lives/{live}/interactions
POST   /api/advertiser/lives/{live}/comments/{comment}/pin
POST   /api/advertiser/lives/{live}/comments/{comment}/hide
POST   /api/advertiser/lives/{live}/participants/{account}/silence
DELETE /api/advertiser/lives/{live}/participants/{account}/silence
```

Toutes ces routes conservent :

- l'organisation annonceur active ;
- la capacité `advertiser.campaign.manage` ;
- la cohérence Live ↔ organisation ;
- le propriétaire du Live comme acteur de modération P018-B.

## 7. Interface

`LiveRealtimeRoom.vue` conserve la grille audiovisuelle et reçoit un composant social séparé :

```text
LiveInteractionsPanel.vue
```

Ce composant affiche dans la zone vidéo :

- derniers commentaires en surimpression ;
- commentaire épinglé ;
- réponse légère ;
- champ `Écrire un commentaire…` ;
- boutons `❤️` et `👏` ;
- animations éphémères des réactions ;
- message de fermeture du chat ;
- message de silence individuel ;
- pour l'hôte : ouverture/fermeture du chat, mode lent, épinglage, masquage, silence/réactivation.

Le compteur spectateurs visible dans la barre `LIVE` reçoit désormais les mises à jour Reverb.

## 8. Audit

Les décisions importantes ajoutent des événements append-only dans `live_audit_events`, notamment :

```text
LiveCommentReported
LiveCommentPinned
LiveCommentUnpinned
LiveCommentHidden
LiveInteractionSettingsUpdated
LiveViewerSilenced
LiveViewerUnsilenced
```

Les réactions et chaque commentaire ordinaire ne remplissent pas l'audit métier afin d'éviter un journal de contrôle inutilement bruyant.

## 9. Sécurité et anti-abus minimal

- aucun HTML interprété côté rendu Vue ;
- corps de commentaire limité à 300 caractères ;
- contrôle serveur de la présence dans le Live ;
- canal Reverb privé ;
- validation stricte des types de réactions et catégories de signalement ;
- ralentissement serveur par dernier commentaire persisté ;
- throttling HTTP ;
- un membre ne peut pas se signaler lui-même ;
- l'hôte ne peut pas se mettre lui-même en silence ;
- un commentaire ou un participant d'un autre Live est refusé ;
- les actions Studio restent isolées par organisation annonceur.

## 10. Hors périmètre

Restent explicitement hors P018-B :

- sanctions globales de compte ;
- dossiers de risque inter-modules ;
- modérateurs multiples / coanimateurs avec capacités dédiées ;
- détection automatique ou IA de contenu ;
- listes de mots interdites globales ;
- mode abonnés ou vérifiés ;
- sponsorisation ;
- places rémunérées ;
- attention vérifiée ;
- WP / Wallet / Grand Livre ;
- cadeaux et pourboires ;
- replay / recording / egress ;
- partage d'écran.

La modération sociale avancée et les sanctions transversales restent rattachées à P020.

## 11. Prérequis de déploiement

Le dépôt possède déjà Reverb/Echo grâce à P011. Avant déploiement production de P018-B, vérifier sans exposer les secrets :

- `BROADCAST_CONNECTION=reverb` ;
- paramètres serveur Reverb ;
- variables `VITE_REVERB_*` du navigateur ;
- process `reverb:start` supervisé ;
- proxy WebSocket Nginx ;
- origine WebSocket autorisée ;
- Redis si le scaling Reverb est activé.

LiveKit reste configuré séparément pour le média.

## 12. Validation attendue

- `php -l` sur les nouveaux/modifiés PHP ;
- Pint ;
- Prettier ;
- ESLint ;
- Vue/TypeScript ;
- build Vite ;
- tests Laravel/PostgreSQL/Redis ;
- test commentaire avant/après entrée dans le Live ;
- test réponse ;
- test réaction sans écriture économique ;
- test signalement ;
- test fermeture du chat ;
- test silence/réactivation local au Live ;
- test masquage/épinglage ;
- test isolation entre organisations ;
- test compteur spectateurs temps réel ;
- test réel multi-appareils lors de la clôture complète du module Live.
