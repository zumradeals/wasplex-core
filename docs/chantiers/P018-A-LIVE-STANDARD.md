# P018-A — Fondation Live standard

**Statut :** implémentation initiale  
**Décision :** 2026-08-15  
**Source principale :** `docs/11-live-wasplex.md`

## 1. Objectif

Ouvrir le module Live Wasplex avec une première verticale volontairement simple et indépendante de l'économie WP :

```text
Membre authentifié
→ crée un Live
→ programme ou démarre immédiatement
→ ouvre la salle
→ un autre membre rejoint
→ pause / reprise
→ fin
→ sessions spectateurs clôturées
→ audit
```

P018-A construit le **cycle de vie métier** du Live avant la sponsorisation et avant l'intégration d'un fournisseur de transport vidéo.

## 2. Inclus

- création d'un Live standard ;
- titre, description, catégorie, langue, visibilité ;
- programmation facultative ;
- durée prévue ;
- états `draft`, `scheduled`, `live`, `paused`, `ended` ;
- démarrage, pause, reprise et fin par le propriétaire ;
- liste des Lives publics programmés/en cours ;
- entrée et sortie d'un spectateur ;
- compteur de sessions spectateurs actives ;
- session de diffusion provider-neutral ;
- audit append-only des événements essentiels ;
- page Inertia `/live` mobile-first ;
- tests de cycle de vie, isolation propriétaire, présence spectateur et non-impact Ledger.

## 3. Explicitement hors périmètre

P018-A ne contient pas :

- récompense WP ;
- sponsorisation ;
- budget annonceur ;
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

## 4. Transport média

La table `live_stream_sessions` introduit dès maintenant une frontière de transport avec :

```text
provider = pending_adapter
```

P018-A ne simule pas une diffusion vidéo inexistante. L'interface indique clairement que la salle, les présences et le cycle de diffusion sont opérationnels tandis que le **transport média externe** sera branché dans un lot dédié.

Cette séparation évite de coupler le domaine Live à un SDK particulier avant le choix du fournisseur.

## 5. Données

### `lives`

- propriétaire ;
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

## 6. API P018-A

### Spectateur

```text
GET  /api/lives
GET  /api/lives/{live}
POST /api/lives/{live}/join
POST /api/lives/{live}/leave
```

### Créateur

```text
GET   /api/creator/lives
POST  /api/creator/lives
PATCH /api/creator/lives/{live}
POST  /api/creator/lives/{live}/schedule
POST  /api/creator/lives/{live}/start
POST  /api/creator/lives/{live}/pause
POST  /api/creator/lives/{live}/resume
POST  /api/creator/lives/{live}/end
```

### Page

```text
GET /live
```

## 7. Sécurité

- authentification obligatoire ;
- session non révoquée ;
- seul le propriétaire contrôle son Live ;
- les brouillons ne sont pas distribués dans la liste publique ;
- seules les salles `live` ou `paused` sont joignables ;
- aucune donnée privée du propriétaire n'est exposée, seulement son nom d'affichage ;
- audit de toutes les transitions importantes.

## 8. Garantie économique

P018-A n'appelle ni le Grand Livre ni le Wallet. La création, la présence et la durée d'une session Live standard **ne créent aucun WP**.

## 9. Suite de P018

La progression recommandée est maintenant :

1. **P018-A.2 — transport média réel** derrière l'interface provider-neutral ;
2. **P018-B — interactions et modération de base** ;
3. sponsorisation et financement ;
4. places rémunérées ;
5. attention vérifiée ;
6. valeur/Wallet ;
7. replay et stabilisation.

Les phases économiques ne démarrent qu'après validation réelle du Live standard.
