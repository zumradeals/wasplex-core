# RAPPORT — P010-B : Feed, vidéo réelle et défilement immersif

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `69e9a65` (P010 fusionné)
**Chantier :** `docs/chantiers/P010-B-FEED-VIDEO-DEFILEMENT.md`
**Spécifications :** `docs/08-feed-principal-wasplex.md` §16, §27, §83
**Statut :** ready_for_review

Demande explicite du fondateur : « le feed est un lieu où l'utilisateur scrolle vers le haut les
vidéos publicitaires […] la vidéo doit être bien visible et immersive ». `docs/08` §83 (« Critères
d'acceptation ») exige explicitement « le scroll vertical est fluide » et « le swipe abandonne
sans gain » — aucun des deux n'était livré en P009/P010 : la zone de contenu était un dégradé
décoratif, et le seul moyen de passer à la publicité suivante était un bouton « Passer ».

## 1. Réalisé

### 1.1. Vidéo réelle

- `ApprovedCampaignAudience` (value object, Campaigns) étendu d'un champ `creativeAssetId`, lu
  depuis `campaign_version.creative_configuration['asset_id']` — déjà stocké depuis P006, jamais
  exposé cross-module jusqu'ici.
- `AttentionService::present()` (Feed) résout l'actif réel via `CreativeAssetDirectoryContract`
  (module AdvertiserStudio, contrat existant depuis P007 — aucun nouveau contrat créé) et l'inclut
  dans la réponse (`creative: {url, type, duration}` ou `null`).
- `FeedPanel.vue` : la zone de contenu affiche désormais une vraie balise `<video>` (muette,
  `autoplay`, `loop`, `playsinline`) quand un actif vidéo est attaché, une `<img>` pour une image,
  et conserve le dégradé décoratif en dernier recours (campagnes de démonstration/tests sans
  média) — jamais d'écran cassé.

### 1.2. Défilement

- Geste tactile (`touchstart`/`touchend`, delta vertical) et molette (`wheel`) déclenchent
  exactement le même flux que le bouton « Passer » existant (`docs/08` §27 : swipe = abandon sans
  gain, réservation libérée, quota consommé si exposition réelle, publicité suivante sélectionnée
  automatiquement, sans confirmation). Le bouton reste visible (accessibilité, environnements sans
  tactile).

### 1.3. Bug corrigé pendant la construction

`muted` déclaré comme attribut HTML statique (`<video muted ...>`) n'était **pas** reflété par le
compilateur Vue sur l'élément réel — un `<video>` avec `autoplay` mais sans la propriété `muted`
effectivement posée reste bloqué par la politique d'autoplay du navigateur. Corrigé en liant
explicitement `:muted="true"` (binding de propriété plutôt qu'attribut statique), un contournement
documenté pour les éléments média sous Vue 3.

## 2. Décisions explicites (voir `docs/chantiers/P010-B-FEED-VIDEO-DEFILEMENT.md` §2)

1. Le bouton « Démarrer » et la bannière de gain avant lecture sont conservés (décision P009 déjà
   validée) — le défilement ne s'applique qu'après démarrage ou sur un état sans publicité.
2. `required_duration_ms` reste gouverné par `creative_configuration.duration_seconds` (P006/P009),
   pas par la durée technique du fichier vidéo — pas de changement de règle financière.
3. Pas de nouveau contrat cross-module — réutilisation de `CreativeAssetDirectoryContract`
   (existant depuis P007).
4. Le geste de défilement déclenche exactement le flux d'abandon existant (`abandon()`), pas une
   nouvelle règle serveur.
5. Pas de préchargement de la publicité suivante ni de pile de vidéos — une seule vidéo chargée à
   la fois, comme aujourd'hui.

## 3. Contrats internes

- `ApprovedCampaignAudienceContract` (Campaigns, étendu) : `ApprovedCampaignAudience` porte
  désormais `creativeAssetId` (nullable, valeur par défaut `null` pour compatibilité ascendante).
- `AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract` (existant, P007) :
  nouveau consommateur (Feed), surface inchangée.

## 4. API

`GET /api/feed/next`, `POST /api/feed/deliveries/{id}/start` : la réponse `delivery` inclut
désormais `creative` (`url`, `type`, `duration`) ou `null`.

## 5. Tests exécutés

- `php artisan test` (Pest 4) — **186 tests, 2141 assertions, aucune régression** (184 avant ce
  chantier + 2 nouveaux dans `tests/Feature/Feed/FeedCreativeTest.php`) : une livraison Feed
  expose l'actif créatif réel (url, type, durée) quand la campagne en a un ; une livraison sans
  actif attaché expose `creative: null` sans erreur.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- Parcours navigateur réel (Playwright/Chromium) contre serveur Laravel + Vite locaux, avec un
  fichier vidéo synthétique réel (généré via `ffmpeg`, encodage VP9/Opus dans un conteneur WebM —
  le build Chromium open-source utilisé par ce sandbox ne décode pas H.264/AAC, contrainte
  d'environnement documentée ci-dessous, sans rapport avec le code applicatif) attaché à une
  campagne approuvée et financée via les mêmes services que la suite de tests. Résultat : bannière
  de gain affichée sur la vraie vidéo immersive avant lecture ; clic sur « Démarrer » → vidéo
  réellement en cours de lecture (`readyState: 4`, `currentTime` progressant, confirmé par
  inspection directe de l'élément `<video>`) ; geste de défilement tactile simulé → livraison
  précédente `abandoned` sans gain (vérifié en base), publicité suivante affichée immédiatement,
  exactement le comportement de `docs/08` §27.

## 6. Fichiers modifiés/ajoutés

```text
apps/platform/app/Modules/Campaigns/Application/ValueObjects/ApprovedCampaignAudience.php   (modifié)
apps/platform/app/Modules/Campaigns/Application/Services/ApprovedCampaignAudienceService.php (modifié)
apps/platform/app/Modules/Feed/Application/Services/AttentionService.php                     (modifié)
apps/platform/app/Modules/Feed/Http/Controllers/User/FeedDeliveriesController.php            (modifié)
apps/platform/resources/js/Components/FeedPanel.vue                                          (modifié)
apps/platform/tests/Feature/Feed/FeedCreativeTest.php                                        (nouveau, 2 tests)
docs/chantiers/P010-B-FEED-VIDEO-DEFILEMENT.md, P010-B-RAPPORT.md                             (nouveaux)
```

## 7. Migrations, événements, permissions

Aucune migration, aucun événement, aucune nouvelle permission — raffinement UI/API pur.

## 8. Limites restantes

- Pas de préchargement de la publicité suivante (chargement à la demande uniquement).
- Le geste de défilement n'a pas de retour visuel dédié (pas d'indicateur "glissez pour passer") —
  raffinement visuel possible ultérieurement, non demandé explicitement.
- L'environnement de capture de ce chantier ne décode pas H.264/AAC (build Chromium open-source
  sans codecs propriétaires) — sans impact en production, où le navigateur réel de l'utilisateur
  (Chrome, Safari, etc.) décode nativement les formats usuels (H.264, VP9) ; documenté pour
  information, pas une limite du code livré.

## 9. État Git

`php artisan test` : 186/186. `pint --test` : vert. Frontend : format/lint/types/build verts.
Répertoire de travail propre après commit. Prêt pour push et PR.

## 10. Chantier suivant recommandé

P011 — Temps réel, rapprochement et retraits utilisateur.
