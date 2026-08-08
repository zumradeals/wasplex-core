# P015 — Rapport de chantier : Refonte visuelle Studio Annonceur

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `b51b64f`
**Objectif :** voir `docs/chantiers/P015-CHANTIER.md`.

---

## Fichiers modifiés

- `Pages/Identity/AdvertiserShell.vue` : sidebar en icônes SVG, logo transparent, en-tête pilule.
- `Components/AdvertiserStudioPanel.vue` : zone de dépôt de fichiers en glisser-déposer réel
  (limites réelles affichées : 10 Mo images, 200 Mo vidéos — corrigé par rapport au texte de la
  maquette qui indiquait à tort 50 Mo).
- `Components/CampaignsPanel.vue` + `CampaignPreviewPhone.vue` : stepper à cercles numérotés,
  ligne de progression réelle dans l'aperçu (dérivée de l'étape courante).
- `Components/AdvertiserWalletPanel.vue` : carte de statistiques réelles (dépensé au total,
  campagnes actives), état vide enrichi pour l'historique des dépôts.
- `Components/SpaceSwitcher.vue` : styles pilule (partagé mobile/web).
- **Nouveau** `Components/AdvertiserDashboardPanel.vue`, `Components/TeamPanel.vue`,
  `lib/campaignObjectives.ts`.
- `docs/chantiers/P015-CHANTIER.md` (nouveau).

## Migrations, API, événements, permissions

Aucun — chantier strictement frontend. Tous les nouveaux écrans consomment des endpoints déjà
existants et testés (`/advertiser/campaigns/report`, `/organizations/{id}/members`,
`/organizations/{id}/invitations`).

## Décisions prises avec le fondateur

1. Graphique « Vues — 14 derniers jours » : remplacé par un total réel (aucune donnée
   quotidienne n'existe côté backend), avec mention explicite de la limite.
2. Wallet annonceur « Dépensé ce mois » → renommé « Dépensé au total » (donnée réelle
   disponible uniquement en cumul, pas de ventilation mensuelle).
3. Équipe : formulaire d'invitation réel, code affiché avec bouton copier — **aucun écran
   d'acceptation d'invitation n'existe** dans l'app (recherché explicitement, absent). Limite
   documentée plutôt que masquée.

## Tests exécutés

- `npm run types:check`, `npx eslint .`, `npm run format:check` — OK
- `php artisan test` — 209 tests, 2568 assertions, tous verts (aucun changement backend)
- `npm run build` — OK
- Parcours manuel réel (Laravel `serve` + Vite dev + Playwright headless, 1440×900) : inscription
  → devenir annonceur → Tableau de bord → Marques (création marque + couleur) → Campagnes
  (création campagne, étape 1) → Wallet annonceur → Équipe (envoi d'une invitation réelle).

## Limites assumées

- Aucun suivi quotidien des vues (voir §3.1 de P015-CHANTIER.md).
- Aucun écran d'acceptation d'invitation — le code généré n'est utilisable que via l'API
  directement pour l'instant (voir §3.3).
- Typographies et guidelines de marque (endpoints réels existants) non exposées dans l'UI —
  absentes de la maquette fournie.

## État Git

Commit à pousser sur `claude/wasplex-reconstruction-7ujym7`, PR draft (revue visuelle avant
fusion, comme pour P014).

## Chantier suivant recommandé

Selon décision du fondateur : suivi quotidien des vues et/ou écran d'acceptation d'invitation.
