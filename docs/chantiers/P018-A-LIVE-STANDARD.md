# P018-A — Fondation Live standard

**Statut :** implémentation initiale corrigée par P018-A.1  
**Décision initiale :** 2026-08-15  
**Décision de cadrage P018-A.1 :** création et pilotage exclusivement depuis le Studio annonceur  
**Source principale :** `docs/11-live-wasplex.md` complétée par `docs/chantiers/P018-A1-LIVE-ANNONCEUR.md`

## 1. Objectif

Ouvrir le module Live Wasplex avec une première verticale volontairement simple et indépendante de l'économie WP, tout en respectant la séparation des espaces :

```text
Annonceur
→ active son espace annonceur
→ Studio annonceur
→ crée ou programme un Live
→ démarre la salle

Membre
→ Feed
→ onglet Live
→ voit les Lives publics en cours / à venir
→ rejoint
→ quitte
```

P018-A construit le **cycle de vie métier** du Live avant la sponsorisation et avant l'intégration d'un fournisseur de transport vidéo.

## 2. Doctrine d'espace

La création d'un Live Wasplex n'est pas une fonction de l'espace membre.

La règle P018-A.1 est :

- **création, programmation et pilotage : Studio annonceur uniquement** ;
- **consultation et présence : espace membre / Feed Live** ;
- l'API créateur exige un espace annonceur actif ;
- les droits sont contrôlés dans le contexte de l'organisation annonceur active ;
- un Live appartient à une organisation annonceur, même si un compte nominatif reste l'acteur créateur pour l'audit.

Les anciennes routes `/api/creator/lives` sont retirées. Les routes de pilotage sont désormais sous `/api/advertiser/lives`.

## 3. Inclus

- création d'un Live depuis le Studio annonceur ;
- rattachement à l'organisation annonceur active ;
- titre, description, catégorie, langue, visibilité ;
- programmation facultative ;
- durée prévue ;
- états `draft`, `scheduled`, `live`, `paused`, `ended` ;
- démarrage, pause, reprise et fin par le créateur dans le bon contexte annonceur ;
- isolation entre organisations annonceurs ;
- liste des Lives publics programmés/en cours côté membre ;
- entrée et sortie d'un spectateur ;
- compteur de sessions spectateurs actives ;
- session de diffusion provider-neutral ;
- audit append-only des événements essentiels ;
- page Inertia `/live` spectateur mobile-first ;
- section Live dans le Studio annonceur ;
- bouton Live du haut du Feed raccordé à `/live` ;
- tests de cycle de vie, séparation membre/annonceur, isolation organisation, présence spectateur et non-impact Ledger.

## 4. Explicitement hors périmètre

P018-A ne contient pas :

- récompense WP ;
- sponsorisation ;
- budget annonceur Live ;
- réservation économique ;
- bloc d'attention rémunéré ;
- écriture Ledger ;
- commentaires ou réactions publics ;
- sondages/quiz ;
- billetterie ;
- cadeaux/pourboires ;
- replay média ;
- modération sociale avancée ;
- fournisseur vidéo réel.

## 5. Transport média

La table `live_stream_sessions` conserve la frontière de transport :

```text
provider = pending_adapter
```

P018-A ne simule pas une diffusion vidéo inexistante. L'interface indique clairement que la salle, les présences et le cycle de diffusion sont opérationnels tandis que le **transport média externe** sera branché dans un lot dédié.

## 6. Données

### `lives`

- compte créateur nominatif ;
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
- fournisseur ;
- référence fournisseur future ;
- début/pause/fin.

### `live_viewer_sessions`

- Live ;
- membre ;
- statut ;
- entrée ;
- dernière présence ;
- sortie.

### `live_audit_events`

Journal des créations, programmations, démarrages, pauses, reprises, fins et entrées/sorties spectateurs.

## 7. API P018-A.1

### Spectateur

```text
GET  /api/lives
GET  /api/lives/{live}
POST /api/lives/{live}/join
POST /api/lives/{live}/leave
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
```

### Page spectateur

```text
GET /live
```

La surface créateur est intégrée dans `/studio`.

## 8. Sécurité

- authentification obligatoire ;
- session non révoquée ;
- espace annonceur actif obligatoire pour les routes Studio Live ;
- capacités `advertiser.campaign.view/manage` contrôlées sur l'organisation active tant qu'une capacité Live dédiée n'est pas introduite ;
- le Live créé porte `advertiser_organization_id` ;
- un changement d'espace annonceur ne permet pas de voir ni piloter les Lives d'une autre organisation ;
- seul le compte créateur pilote le Live dans P018-A ;
- les brouillons ne sont jamais distribués dans la surface membre ;
- seules les salles `live` ou `paused` sont joignables ;
- le public voit le nom de l'organisation annonceur, pas une identité technique interne ;
- audit de toutes les transitions importantes.

## 9. Garantie économique

P018-A n'appelle ni le Grand Livre ni le Wallet. La création, la présence et la durée d'une session Live standard **ne créent aucun WP**.

## 10. Suite de P018

La progression recommandée reste :

1. **P018-A.2 — transport média réel** derrière l'interface provider-neutral ;
2. **P018-B — interactions et modération de base** ;
3. sponsorisation et financement ;
4. places rémunérées ;
5. attention vérifiée ;
6. valeur/Wallet ;
7. replay et stabilisation.

Les phases économiques ne démarrent qu'après validation réelle du Live standard annonceur → spectateur.
