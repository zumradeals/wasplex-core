# P001-B — IDENTITÉ VISUELLE FONDATRICE (RATTRAPAGE)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `ba827c41d89659324e871e7ab4308e5b41360e09` (P001 fusionné sur `main`)
**Statut :** `in_progress`

## Contexte

Audit demandé par le fondateur après livraison de P001 : les écrans livrés
en P000 (page technique) et P001 (shells Identity) utilisent une palette et
une typographie **inventées localement**
(`apps/platform/resources/css/app.css` : or `#d4a017`, noir `#0b0b0c`,
police système), sans lien avec `docs/00-identite-visuelle-wasplex.md`, qui
porte le statut « Spécification produit et design à appliquer dès
l'initialisation du dépôt », priorité **Fondatrice**, et affirme
explicitement :

> « Cette identité doit être intégrée avant le développement du noyau,
> afin que le noyau, le Feed, le Wallet et tous les futurs tableaux de bord
> héritent du même langage. »
> « Aucun module Wasplex ne crée sa propre identité visuelle en dehors de
> ce socle. »

Décision du fondateur (2026-08-05) : ouvrir ce chantier de rattrapage avant
P002, plutôt que de laisser la dette visuelle s'accumuler sur davantage
d'écrans.

## Objectif

Remplacer la palette, la typographie, les rayons et les espacements
inventés en P000 par les tokens officiels de
`docs/00-identite-visuelle-wasplex.md`, et les appliquer aux écrans déjà
livrés (page technique, `Login`, `Register`, `UserShell`,
`AdvertiserShell`, `AdminShell`, `AdminMfaChallenge`).

## Inclus

- tokens CSS : couleurs (thème sombre bleu nuit comme identité principale
  de l'application utilisateur ; thème clair professionnel pour Studio
  Annonceur et Administration, conformément à §7.2 — en-tête/navigation
  bleu nuit conservée), couleurs sémantiques, rayons, espacements, courbes
  de mouvement (§21) ;
- police **Inter** auto-hébergée (`@fontsource-variable/inter`, aucun appel
  réseau au runtime, cohérent avec la décision P000 d'indépendance
  réseau) ;
- séparation `tokens.css` / `themes.css` / `app.css` (structure recommandée
  §22, sans le paquet `packages/design-tokens/` complet — inutile tant
  qu'un seul frontend consomme les tokens, cf. limites) ;
- application aux composants existants : boutons, cartes, formulaires,
  navigation, badge « Wasplex », page technique ;
- thème sombre appliqué à `UserShell`/`Login`/`Register` (identité
  principale utilisateur, §6 et §20.1) ; thème clair professionnel à
  bandeau bleu nuit conservé pour `AdvertiserShell`/`AdminShell`/
  `AdminMfaChallenge` (§7.2 et §20.5/§20.6).

## Exclus

- assets de marque réels (`public/brand/*.svg` : logo complet, mascotte
  martin-pêcheur, wordmark, icônes PWA) — ce sont des fichiers de design
  propriétaires que je ne peux pas fabriquer de façon crédible ; le badge
  textuel « Wasplex » est conservé comme repère provisoire en attendant les
  fichiers du fondateur ;
- composants fondateurs non encore nécessaires (§19) : `WalletChip`,
  `ProgressLine`, `RewardBadge`, `RewardToast`, `VideoFeedItem`… — hors
  périmètre tant que Wallet/Feed n'existent pas (P002+) ;
- langage sonore et haptique (§16) — aucune interaction de crédit à
  confirmer avant P002/P003 ;
- animation du martin-pêcheur (§15.4) — dépend de la mascotte réelle et du
  crédit Wallet, hors périmètre ;
- paquet partagé `packages/design-tokens/` — prématuré avec un seul
  frontend consommateur.

## Invariants

- aucune couleur de marque codée en dur hors des tokens centralisés ;
- le thème sombre reste l'identité par défaut de l'espace utilisateur ;
- les portails professionnels conservent un repère bleu nuit (en-tête ou
  navigation) même en thème clair ;
- aucune police distante chargée au runtime (contrainte réseau déjà actée
  en P000, réaffirmée ici avec Inter auto-hébergée).

## Preuves attendues

- `npm run format:check`, `npm run lint:check`, `npm run types:check`,
  `npm run build` ;
- `composer test` (aucune régression backend attendue, changement
  purement frontend) ;
- captures des écrans mis à jour aux largeurs de référence mobile/desktop.
