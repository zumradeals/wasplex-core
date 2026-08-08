# P016 — Chantier : Refonte visuelle Administration centrale

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `9f292c0` (main, après fusion P015)
**Déclencheur :** maquette fournie par le fondateur (`design_handoff_admin/Wasplex Admin.html`
+ `README.md`), orientée non-technique — le fondateur lui-même n'est pas technique.

## Objectif

Refondre l'expérience visuelle et fonctionnelle de 3 écrans de l'Administration centrale
(`AdminShell.vue`) pour qu'un non-technicien les comprenne sans explication :

1. **Vue d'ensemble** (remplace `AdminDashboardPanel.vue`).
2. **Ciblage publicitaire** (remplace le libellé « Matching », `AdminMatchingPanel.vue`).
3. **Informations de profil** (remplace le libellé « Profil intelligent / SmartProfile »,
   `AdminSmartProfilePanel.vue`).

Principes directeurs de la maquette : langage humain (jamais de jargon type `taxonomy`,
`code technique`, `draft/published` affiché brut), contrôles visuels (sliders, interrupteurs,
badges colorés) plutôt que formulaires bruts, contexte avant l'action (encadré d'explication en
haut de chaque écran).

## Périmètre inclus

- `Pages/Identity/AdminShell.vue` : sidebar sombre restylée (icônes SVG ligne au lieu d'emoji,
  en-tête pilule « Fondateur » + déconnexion rouge), cohérente avec `AdvertiserShell.vue` (P015).
  Renommage des 3 libellés ci-dessus dans la navigation. **Les 12 entrées de navigation existantes
  sont conservées** (voir décision ci-dessous).
- `Components/AdminDashboardPanel.vue` → contenu de « Vue d'ensemble ».
- `Components/AdminMatchingPanel.vue` → contenu de « Ciblage publicitaire ».
- `Components/AdminSmartProfilePanel.vue` → contenu de « Informations de profil ».
- Réutilisation stricte des tokens `resources/css/tokens.css` déjà en place (aucun nouveau
  token). Correspondance mockup → tokens réels : `#075CCF` → `wpx-blue-light`, `#137A50` →
  `wpx-success-light`, `#9A5B00` → `wpx-warning-light`, `#B42318` → `wpx-danger-light`,
  `#53657D` → `wpx-text-muted`, `#10233F` → `wpx-text`, `#07182D`/`#0E2542` →
  `wpx-navy-950`/`wpx-navy-850`, `#2BC4DE` → `wpx-cyan`, `#F5F7FA` → `wpx-canvas`.

## Périmètre exclu (décision du fondateur)

Le fondateur a tranché explicitement (question posée avant codage) : **aucun nouveau backend,
même minime**, pour cette refonte. Conséquences directes :

- Pas de nouvel endpoint pour compter les utilisateurs (aucune route `/admin/accounts` n'existe
  aujourd'hui) → la carte KPI « Utilisateurs » affiche **« Bientôt disponible »**.
- Le graphique « Nouveaux utilisateurs — 14 jours » de la maquette n'a aucun endpoint réel
  (même si `accounts.created_at` existe en base) → remplacé par un encart **« Bientôt
  disponible »**, jamais par des barres inventées.
- Le concept de **retrait (« withdrawal »)** n'existe nulle part dans le code (vérifié par
  recherche exhaustive : aucune table, modèle, contrôleur ou route). La carte KPI « Retraits en
  attente » et la ligne « À traiter » correspondante de la maquette sont **absentes** de
  l'implémentation — remplacées par « Bientôt disponible » sur la carte KPI, et **omises** (pas
  de ligne fictive à 0) du bloc « À traiter ».
- La « Répartition des WP distribués » par catégorie (Publicités vues / Parrainage / Bonus &
  récompenses) n'existe pas : aucune ventilation par source n'est exposée par
  `LedgerReportingContract`, et les concepts « Parrainage » et « Bonus » n'existent nulle part
  dans le backend. Remplacé par les sections réelles déjà disponibles aujourd'hui dans l'ancien
  `AdminDashboardPanel.vue` (Grand Livre, Feed, Abonnements), restylées en cartes cohérentes avec
  la nouvelle direction visuelle, plutôt que par un graphique inventé.
- Bandeau de statut système : aucune supervision par module (`ModuleHealth`,
  `/admin/modules/health`) n'existe. Seul `GET /api/health` existe (vérifie uniquement
  PostgreSQL + Redis). Le bandeau est branché sur cet endpoint réel existant (aucune modification
  backend requise, endpoint déjà public), avec un intitulé honnête (« Tout fonctionne
  normalement » basé sur l'infrastructure, pas une supervision par module).

Autres exclusions :

- Aucune consolidation de la navigation admin (12 entrées) vers les 8 entrées « aspirationnelles »
  visibles dans la maquette (celle-ci ne montre que le contexte visuel de la sidebar, pas une
  demande explicite de fusionner/supprimer des écrans existants : Capacités, Grand Livre,
  Abonnements, Annonceurs, Revue de campagnes, Feed, Rapprochement, Organisations, Audit restent
  des entrées séparées et fonctionnelles). Seuls les 3 libellés explicitement cités dans le
  handoff sont renommés.
- Aucun changement de permissions/capacités : les mêmes capacités qu'aujourd'hui protègent les
  mêmes actions (`admin.matching.*`, `admin.smartprofile.*`, etc.) — seule l'interface change.
- Aucune migration, aucun nouvel événement.

## Écrans détaillés

### Vue d'ensemble
- Bandeau de statut (réel, `GET /api/health`).
- 4 cartes KPI : Utilisateurs (Bientôt disponible), WP en circulation (réel,
  `/admin/dashboard/summary` → `ledger.net_by_account_type`), Campagnes actives + en attente de
  validation (réel, `summary.campaigns.active_campaigns` + `count()` de
  `/admin/campaign-reviews`), Retraits en attente (Bientôt disponible).
- Bloc « À traiter » : campagnes en attente (réel, avec noms de marque via
  `/admin/campaign-reviews`, CTA « Examiner » qui bascule l'onglet vers Revue de campagnes),
  nouveaux comptes annonceur en attente de vérification (réel, `/admin/advertisers` filtré sur
  `status === 'draft'` — le statut `pending_verification` est une constante définie mais jamais
  assignée par `AdvertiserProfileService` aujourd'hui ; `draft` est l'état réellement utilisé par
  `AdminAdvertisersPanel.vue` pour proposer le bouton « Vérifier », donc le filtre retenu ici
  correspond au comportement réel du produit. CTA « Voir » qui bascule vers Annonceurs).
- Section réelle (remplace le graphique 14 jours + répartition WP inventés) : cartes Grand Livre
  (équilibre, transactions), Feed (WP distribués, attention qualifiée), Abonnements (actifs,
  revenu total) — données identiques à l'ancien `AdminDashboardPanel.vue`, restylées.

### Ciblage publicitaire
- Encadré d'explication (contexte + limite : Feed pas encore branché).
- 3 réglages en sliders (nombre max de publicités, fenêtre en heures, seuil de lassitude), valeur
  affichée en grand à droite.
- Un seul CTA « Publier ces réglages » : crée un brouillon puis le publie immédiatement en une
  seule action (au lieu du flux en 2 étapes actuel), avec rappel de la date de la version
  actuellement publiée. Toujours une nouvelle version — jamais de modification en place.
- « Ce qui s'est passé récemment » : 3 compteurs (Éligibles / Non éligibles / En attente) réels
  (`decisionCounts` existant), présentés en grandes cartes chiffrées au lieu d'une liste brute.
- La table historique brute de toutes les configurations est retirée de la vue par défaut (jugée
  trop technique pour un non-tech) ; seule la configuration courante (dernière publiée ou
  brouillon en cours) est affichée via les sliders.

### Informations de profil
- Encadré d'explication en haut.
- Informations groupées par catégorie humaine (`CATEGORY_LABELS` existant), cartes avec
  interrupteur : actif = badge vert + interrupteur allumé (clic → suspendre) ; en attente
  (`draft` ou `suspended`) = ligne surlignée + bouton « Activer ».
- Le champ « code technique » n'est plus un champ par défaut à la création : généré
  automatiquement (`categorie.libelle-slugifié`), visible seulement derrière une bascule
  « Mode avancé » optionnelle.
- « Autorisations demandées aux utilisateurs » (remplace « Finalités de consentement ») : cartes
  avec texte exact entre guillemets, badge de statut, lien « Modifier le texte » qui ouvre un
  formulaire d'édition inline (crée une nouvelle version, la publie immédiatement — même
  mécanique de versionnage qu'aujourd'hui, mais qui n'est plus le point d'entrée visuel
  principal). Bouton « + Nouvelle autorisation » ajouté pour ne pas perdre la capacité actuelle de
  créer une finalité de toutes pièces.

## Tests prévus

- `npm run types:check`, `npx eslint .`, `npm run format:check`.
- `php artisan test` (aucun changement backend attendu : suite doit rester à 209 tests verts).
- Parcours Playwright réel sur les 3 écrans avec captures.

## Risques / limites assumées

- Écran « Vue d'ensemble » moins riche que la maquette sur 4 points précis (utilisateurs,
  graphique 14 jours, retraits, répartition WP) — décision explicite du fondateur de ne pas
  construire de nouveau backend pour cette refonte visuelle.
- La suppression de la table historique complète des configurations de Matching de la vue par
  défaut réduit la visibilité de l'historique (toujours consultable en base, juste plus affiché
  dans cette UI simplifiée).
