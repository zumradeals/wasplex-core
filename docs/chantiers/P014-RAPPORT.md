# P014 — Rapport de chantier : Refonte visuelle (Connexion, Mon Espace, Wallet, Feed)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `a36837a`
**Objectif :** voir `docs/chantiers/P014-CHANTIER.md`.

---

## Fichiers modifiés

- `apps/platform/public/brand/wasplex-logo-transparent.png` (nouveau) + `README.md` mis à jour.
- `apps/platform/resources/css/app.css` : keyframes `wpxGlow`, `wpxShine`, `wpxPulseLogo`,
  garde `prefers-reduced-motion`.
- `apps/platform/resources/js/lib/comingSoon.ts` (nouveau) : petit composable partagé pour les
  actions inertes (« Bientôt disponible »), réutilisé dans 4 composants au lieu de dupliquer la
  logique de minuterie.
- `Pages/Identity/Landing.vue`, `Components/PhoneQuickConnect.vue` : écran Connexion/Inscription
  recomposé en un seul écran compact.
- `Pages/Identity/Login.vue`, `Register.vue` : logo transparent (plus de plaque blanche).
- `Pages/Identity/UserShell.vue` : nav bas en SVG, en-tête simplifié, onglet Mon Espace
  entièrement restructuré (carte profil, actions rapides, listes).
- `Components/SmartProfilePanel.vue` : ajout d'une barre de progression réelle + `percent` exposé.
- `Components/BecomeAdvertiserPanel.vue` : bandeau Studio Annonceur restylé.
- `Components/WalletPanel.vue` : carte de solde animée, actions inertes, stats réelles,
  historique restructuré.
- `Components/FeedPanel.vue` : en-tête (logo pulsant, onglets, pastille WP, ligne de progression
  réelle repositionnée), rail droit fusionné avec Alertes, avatar annonceur.
- `docs/chantiers/P014-CHANTIER.md` (nouveau, décisions et périmètre).

## Migrations, API, événements, permissions

Aucun — chantier strictement frontend, aucun endpoint ni contrat modifié.

## Décisions prises avec le fondateur

1. Éléments sans backend (retrait/dépôt/transfert, Carte Wasplex, parrainage, KYC) : visuel
   présent, actions inertes (« Bientôt disponible »), **aucune donnée fabriquée**.
2. Onglet de navigation « Fonds » conservé (pas remplacé par « Social »), juste rhabillé en SVG.
3. Geste `TapMatchGate` retiré du nouveau flux de connexion (mockup fourni par le fondateur ne le
   montre plus) — composant conservé dans le dépôt, non supprimé. **Point ouvert.**

## Tests exécutés

- `npm run types:check` — OK
- `npx eslint .` — OK
- `npm run format:check` — OK
- `php artisan test` — 209 tests, 2568 assertions, tous verts (non-régression backend confirmée)
- `npm run build` — build de production OK
- Parcours manuel réel (Laravel `serve` + Vite dev + Playwright headless, 390×844) : inscription
  → Feed → Mon Espace → Wallet, captures à chaque étape, deux comptes différents testés pour
  vérifier le rendu avec et sans nom affiché.

## Limites assumées (voir aussi §3 et §5 de P014-CHANTIER.md)

- Le Feed reste une carte `aspect-[9/16]` contenue dans la mise en page, pas un plein écran avec
  nav superposée comme la maquette — pour ne pas risquer de régression sur le moteur
  d'attention/gain déjà testé.
- Le champ « zone » du profil (mockup : « · Abobo ») n'est pas affiché : aucune donnée de ville
  fiable n'existe sur le compte (seule la zone approximative *auto-déclarée* du Profil intelligent
  existe, et elle est optionnelle) — remplacé par le seul numéro de téléphone, réel.
- Les statistiques Wallet « Aujourd'hui »/« Ce mois » sont calculées à partir de la page
  d'historique déjà chargée côté client (pas d'agrégation serveur dédiée) — exactes pour un
  compte à faible volume, potentiellement incomplètes au-delà d'une page.
- Aucune capture n'est jointe au commit (vérifications faites en local, voir captures Playwright
  non versionnées).

## Décisions ouvertes pour le fondateur

- Le geste décoratif `TapMatchGate` doit-il revenir ailleurs, ou est-il définitivement abandonné ?
- Faut-il committer les couleurs `Historique carte Wasplex` / `Parrainage` sur « — » (choix actuel,
  honnête) plutôt que masquer entièrement ces lignes tant qu'aucun module ne les alimente ?

## État Git

Commit à pousser sur `claude/wasplex-reconstruction-7ujym7`, PR **draft**, fusion **non
automatique** : le fondateur a explicitement exprimé son insatisfaction du design précédent, la
revue visuelle humaine avant fusion est donc jugée nécessaire pour ce chantier (contrairement aux
chantiers fonctionnels précédents).

## Chantier suivant recommandé

Selon la décision du fondateur : premier module backend derrière les placeholders inertes
(probablement Carte Wasplex ou retrait self-service Wallet, les deux les plus visibles).
