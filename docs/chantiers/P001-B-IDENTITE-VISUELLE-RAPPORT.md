# P001-B — RAPPORT DE CHANTIER

**Chantier :** P001-B — Identité visuelle fondatrice (rattrapage)
**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `ba827c41d89659324e871e7ab4308e5b41360e09` (P001 fusionné sur `main`)
**Statut :** `ready_for_review`

## Contexte

Après livraison de P001, le fondateur a demandé de vérifier si les écrans
livrés respectaient la charte graphique et la responsivité. Vérification
faite :

- **Responsivité** : conforme à la doctrine `docs/14` (`UserShell`
  mobile-first conservé sur desktop, `AdvertiserShell` responsive avec
  bascule `md:`, `AdminShell` desktop uniquement).
- **Charte graphique** : **non conforme**. `apps/platform/resources/css/app.css`
  (introduit en P000) définissait une palette et une typographie inventées
  localement (or `#d4a017`, noir `#0b0b0c`, police système), sans lien avec
  `docs/00-identite-visuelle-wasplex.md` — document au statut « Spécification
  produit et design à appliquer dès l'initialisation du dépôt », priorité
  **Fondatrice**, qui impose explicitement que cette identité soit intégrée
  « avant le développement du noyau » et qu'« aucun module Wasplex ne crée sa
  propre identité visuelle en dehors de ce socle ».

Décision du fondateur (2026-08-05) : ouvrir ce chantier de rattrapage avant
P002, conformément à `docs/chantiers/P001-B-IDENTITE-VISUELLE-CHANTIER.md`.

## Objectif

Remplacer la palette/typographie/rayons/ombres inventés par les tokens
officiels et les appliquer à tous les écrans déjà livrés (page technique
P000, shells P001).

## Réalisé

- `resources/css/tokens.css` : tokens officiels centralisés (`@theme`
  Tailwind 4) — couleurs du thème sombre (`--color-wpx-navy-950/850/750`,
  `--color-wpx-border-dark`, `--color-wpx-white-soft`,
  `--color-wpx-muted-dark`), accents de marque (`--color-wpx-blue/cyan/
  orange/gold`), couleurs du thème clair professionnel
  (`--color-wpx-canvas/surface/raised/text/text-muted/border` et variantes
  `-light` des accents pour le contraste), couleurs sémantiques
  (succès/avertissement/danger/info/attente, valeurs sombre + claire),
  rayons (`--radius-wpx-xs` à `-full`), ombres (`--shadow-wpx-card`,
  `--shadow-wpx-card-dark`), courbes de mouvement
  (`--ease-wpx-standard/emphasized/reward`) — tous repris littéralement de
  `docs/00-identite-visuelle-wasplex.md` §6, §7, §8, §12, §13.2, §15.2 ;
- `resources/css/themes.css` : police **Inter** auto-hébergée
  (`@fontsource-variable/inter`, fichiers woff2 embarqués au build, aucun
  appel réseau au runtime — conforme à la contrainte d'indépendance réseau
  actée en P000) ;
- `resources/css/app.css` : importe `tokens.css` puis `themes.css` avant
  Tailwind (structure recommandée par §22, simplifiée — voir Limites) ;
- application aux écrans existants :
  - **thème sombre** (identité principale utilisateur, §6/§20.1) :
    `Login`, `Register`, `UserShell` — fond bleu nuit `wpx-navy-950`,
    cartes `wpx-navy-850` avec ombre profonde, boutons primaires en
    dégradé bleu→cyan (§6.3 « action technologique »), badge « Wasplex »
    or sur navy ;
  - **thème clair professionnel avec navigation bleu nuit conservée**
    (§7.2/§20.5/§20.6) : `AdvertiserShell`, `AdminShell`,
    `AdminMfaChallenge`, page technique — sidebar/en-tête navy, contenu sur
    fond clair `wpx-canvas`, cartes blanches avec ombre légère, actions
    dangereuses en rouge clair contrasté (`wpx-danger-light`) ;
  - `SpaceSwitcher` : nouveau prop `variant: 'light' | 'dark'` pour
    s'adapter au shell qui l'intègre.
- Aucune couleur de marque codée en dur dans les fichiers `.vue` : toutes
  les valeurs passent par les classes Tailwind générées à partir de
  `tokens.css`.

## Exclus (documentés dans le chantier, non traités ici)

- Assets de marque réels (`public/brand/*.svg` : logo complet, mascotte
  martin-pêcheur, wordmark, icônes PWA) — fichiers de design propriétaires
  que je ne peux pas fabriquer de façon crédible ; le badge textuel
  « Wasplex » reste un repère provisoire en attendant les fichiers du
  fondateur.
- Composants fondateurs encore non nécessaires (§19) :
  `WalletChip`/`ProgressLine`/`RewardBadge`/`RewardToast`/`VideoFeedItem`…
  — hors périmètre tant que Wallet/Feed n'existent pas (P002+).
- Langage sonore et haptique (§16) et animation du martin-pêcheur (§15.4) —
  aucune interaction de crédit à confirmer avant P002/P003, et la mascotte
  réelle n'existe pas encore.
- Paquet partagé `packages/design-tokens/` (structure §22) — prématuré tant
  qu'un seul frontend (`apps/platform`) consomme les tokens ; ceux-ci
  restent centralisés dans `resources/css/tokens.css`, ce qui satisfait déjà
  l'exigence « aucune couleur codée localement ».
- Espacements : la grille officielle (§11, base 4px) correspond exactement
  à l'échelle native de Tailwind (`spacing-N` = N × 4px), donc aucun token
  dédié n'a été ajouté — simplification délibérée, pas un oubli.

## Invariants vérifiés

- aucune classe `wasplex-*` (ancienne palette inventée) ne subsiste dans
  `resources/js` ni `resources/css` (vérifié par recherche exhaustive) ;
- le thème sombre reste l'identité par défaut de l'espace utilisateur
  (`Login`, `Register`, `UserShell`) ;
- les portails professionnels (`AdvertiserShell`, `AdminShell`) conservent
  un repère bleu nuit (sidebar) même en thème clair ;
- aucune police distante chargée au runtime — `npm run build` confirme que
  les fichiers Inter sont émis localement dans `public/build/assets/*.woff2`.

## Tests exécutés

| Contrôle | Résultat |
|---|---|
| `npm run format` puis `format:check` (Prettier) | Réussi |
| `npm run lint:check` (ESLint) | Réussi |
| `npm run types:check` (vue-tsc) | Réussi |
| `npm run build` (Vite) | Réussi — fichiers Inter (woff2) embarqués localement |
| `composer test` (Pest 4) | Réussi — 31 tests, 153 assertions (aucune régression backend, changement purement frontend) |
| Parcours navigateur (Playwright/Chromium), captures aux largeurs 390/1024/1280/1440 | Réussi, captures ci-dessous |

## Captures

Dix captures prises via Chromium/Playwright, avant/après implicite par
comparaison avec les captures du rapport P001 initial :

1. Page technique (`/`, desktop 1024) — thème clair, badge navy/or.
2. `/login` (mobile 390 et desktop 1440) — thème sombre, bouton dégradé
   bleu→cyan.
3. `/register` (mobile 390) — thème sombre.
4. `/app` — `UserShell` (mobile 390 et desktop 1024, shell mobile conservé
   centré) — thème sombre, navigation basse or/bleu nuit.
5. `/studio` — `AdvertiserShell` (desktop 1280 et mobile 390) — sidebar
   navy, contenu clair, onglet Équipe réel.
6. `/admin/mfa-challenge` — enrôlement TOTP (desktop 1280) — thème clair,
   bouton dégradé bleu→cyan.
7. `/admin` — `AdminShell` après vérification MFA (desktop 1280) — sidebar
   navy, contenu clair, onglet Capacités réel.

## Limites restantes

1. Aucun asset de marque réel (logo, mascotte, wordmark, icônes PWA) —
   dépend de fichiers de design que seul le fondateur peut fournir.
   `public/brand/` reste à créer dans un chantier ultérieur une fois ces
   fichiers disponibles.
2. Mouvement (§15), son et haptique (§16) : seule la courbe
   `ease-wpx-standard` est appliquée aux transitions de boutons existants ;
   les animations de crédit/récompense n'ont pas de sens tant que Wallet et
   Feed n'existent pas.
3. `packages/design-tokens/` (structure §22) non créé — simplification
   documentée, à revisiter si un second frontend consommateur apparaît.
4. Larastan/PHPStan toujours non installable dans ce bac à sable réseau
   restreint (limite déjà documentée depuis P000, sans lien avec ce
   chantier).

## Risques

- Les valeurs de contraste (accents `-light` sur fond blanc, `-dark` sur
  fond navy) ont été reprises telles quelles de la spécification ; elles
  n'ont pas été vérifiées par un outil d'audit d'accessibilité automatisé
  dans ce chantier (§24 — à couvrir dans un futur chantier d'accessibilité
  dédié).

## Décisions ouvertes pour le fondateur

- Fournir les fichiers de marque réels (`public/brand/*.svg`) pour remplacer
  le badge textuel « Wasplex » par le logo/la mascotte officiels.
- Confirmer que le thème sombre par défaut pour `Login`/`Register`/
  `UserShell` correspond à l'intention (l'écran de connexion n'est pas
  explicitement classé par §20, ce choix suit la logique §4.4 qui associe
  le logo complet à « inscription » et « connexion »).

## Commit final proposé

```text
P001-B: applique l'identité visuelle officielle (docs/00) aux écrans P000/P001
```

## Chantier suivant recommandé

**P002 — Grand Livre minimal**, après validation de ce rattrapage visuel par
le fondateur.
