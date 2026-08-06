# P010-B — Feed : vidéo réelle et défilement immersif

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `50ef227` (P010 en cours de fusion)
**Demande explicite du fondateur :** « le feed est un lieu où l'utilisateur scrolle vers le haut
les vidéos publicitaires […] la vidéo doit être bien visible et immersive » — insertion entre P010
et P011, même convention que P001-C/P004-B/P004-C (chantier de raffinement UI injecté sans
renuméroter la roadmap).

## Constat (état réel du dépôt après P009/P010)

`docs/08-feed-principal-wasplex.md` §83 (« Critères d'acceptation ») exige explicitement :

- « le scroll vertical est fluide » ;
- « le swipe abandonne sans gain ».

Aucun des deux n'était livré : `FeedPanel.vue` affichait un dégradé décoratif à la place du média
réel (décision explicite `docs/chantiers/P009-CHANTIER.md`, non couverte alors), et le seul moyen
de passer à la publicité suivante était un bouton « Passer » — pas un geste de défilement.

## 1. Objectif

```text
publicité réservée (gain déjà annoncé, P009)
→ démarrage explicite ("Démarrer", gain avant lecture, inchangé)
→ vidéo réelle de la bibliothèque créative (P005) jouée, immersive, muette (autoplay navigateur)
→ barre fine sous l'en-tête, progression réelle (inchangée, P009/P010)
→ geste de défilement vertical (swipe tactile / molette) = abandon sans gain (docs/08 §27/§83)
→ publicité suivante sélectionnée automatiquement
```

## 2. Décisions explicites

1. **Le bouton « Démarrer » et la bannière de gain avant lecture sont conservés** — décision déjà
   validée en P009 (`docs/07` §15 : gain annoncé avant réservation), le fondateur n'a pas demandé
   de la retirer. Le défilement ne s'applique qu'*après* le démarrage ou sur un état sans
   publicité, pas pour contourner l'annonce du gain.
2. **`required_duration_ms` reste gouverné par `creative_configuration.duration_seconds`
   (P006/P009), pas par la durée technique du fichier vidéo.** Faire dépendre le seuil de gain de
   la durée réelle du fichier serait un changement de règle financière non demandé — hors
   périmètre de ce raffinement UI. La vidéo est bouclée (`loop`) si elle est plus courte que la
   durée requise, et simplement coupée par l'avancement automatique si elle est plus longue.
3. **Pas de nouveau contrat cross-module.** `CreativeAssetDirectoryContract` existe déjà
   (`app/Modules/AdvertiserStudio`, consommé depuis P007 par `CampaignReviewsController`) et
   expose exactly ce qu'il faut (`url`, `type`, `duration`) — Feed le réutilise tel quel.
4. **Le geste de défilement déclenche exactement le flux d'abandon existant** (`docs/08` §27 :
   swipe = session abandonnée, aucun gain, réservation libérée, quota consommé si exposition
   réelle, publicité suivante sélectionnée) — même méthode `abandon()` que le bouton « Passer »,
   qui reste présent (accessibilité, absence de tactile en test/desktop).
5. **Pas de préchargement de la publicité suivante ni de pile de vidéos.** Une seule vidéo est
   chargée à la fois (comme aujourd'hui) — le préchargement anticipé est une optimisation de
   performance hors périmètre de ce raffinement.

## 3. Modèle de données

Aucune migration. `ApprovedCampaignAudience` (value object, P008) est étendu d'un champ
`creativeAssetId` (nullable), lu depuis `campaign_version.creative_configuration['asset_id']` —
déjà stocké depuis P006, jamais exposé cross-module jusqu'ici.

## 4. Contrats internes

- `ApprovedCampaignAudienceContract` (Campaigns, étendu) : `ApprovedCampaignAudience` porte
  désormais `creativeAssetId`.
- `AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract` (existant, P007) :
  nouveau consommateur (Feed), sans modification de sa surface.

## 5. API

`GET /api/feed/next`, `POST /api/feed/deliveries/{id}/start` : la réponse `delivery` inclut
désormais un objet `creative` (`url`, `type`, `duration`) quand un média est disponible ; `null`
sinon (aucune publicité de démonstration sans média réel ne doit planter l'écran — état géré
explicitement côté client).

## 6. UI

- `FeedPanel.vue` : `<video>` réelle (muette, `autoplay`, `loop`, `playsinline`) remplaçant le
  dégradé décoratif quand `creative.type === 'video'` ; image statique sinon ; dégradé conservé en
  dernier recours si aucun média n'est attaché (campagnes de démonstration/tests).
- Geste de défilement : `touchstart`/`touchend` (delta vertical vers le haut) et `wheel`
  (`deltaY > seuil`) déclenchent le même code que « Passer ». Le bouton reste visible.

## 7. Tests obligatoires

Le contrat étendu expose l'identifiant de l'actif créatif sans révéler d'autre donnée annonceur
non déjà exposée ; l'API `/feed/next` inclut l'URL du média quand un actif existe, `null` sinon ;
le geste de défilement (simulé via l'appel HTTP `abandon` qu'il déclenche) libère l'enveloppe sans
gain, exactement comme le bouton existant (aucune nouvelle règle serveur, donc pas de nouveau test
serveur dédié au geste lui-même — la couverture porte sur l'exposition de l'actif créatif).

## 8. Critères de fin

Tests Pest verts (existants + extension du contrat), Pint vert, qualité frontend verte, capture
Playwright montrant une vraie vidéo jouée dans le cadre immersif, rapport, `docs/ROADMAP-INDEX.md`
inchangé (raffinement hors numérotation principale, comme P001-C/P004-B/P004-C), PR en brouillon,
CI verte, merge, resynchronisation de branche.
