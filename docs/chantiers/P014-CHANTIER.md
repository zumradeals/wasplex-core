# P014 — Refonte visuelle (Connexion, Mon Espace, Wallet, Feed)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `a36837a` (P011-C mergé — écran self-service « Devenir annonceur »)
**Dépendances :** P001-B (identité visuelle officielle, tokens), P001-C (accueil public, connexion
téléphone, Feed-first), P008 (Profil intelligent), P009 (Wallet utilisateur, Feed), P011 (temps
réel Wallet).
**Statut :** proposed

---

## 1. Origine et décision du fondateur

Le fondateur, insatisfait du design actuel, a fourni un dossier de handoff produit par un outil
de design (« Claude Design ») : 4 maquettes HTML haute-fidélité (Connexion/Inscription, Mon
Espace, Wallet, Feed) et un nouveau logo officiel **à fond réellement transparent**
(`wasplex-logo-transparent.png`, vérifié RGBA avec canal alpha réel — voir
`apps/platform/public/brand/README.md`).

Le handoff annonce explicitement les couleurs/typographies/espacements/rayons comme définitifs
et repris des tokens déjà en place (`resources/css/tokens.css`) — vérifié : toutes les valeurs
hex du handoff correspondent exactement aux tokens existants, aucune couleur nouvelle n'est
introduite.

## 2. Objectif

Recréer fidèlement les 4 écrans de la maquette dans l'environnement existant
(`apps/platform`, Vue + Inertia + Tailwind), en réutilisant les endpoints et données réels déjà
en place, sans construire de nouveau module backend.

## 3. Décisions explicites (arbitrées avec le fondateur avant codage)

### 3.1. Éléments visuels sans backend

La maquette montre plusieurs éléments pour lesquels aucune donnée réelle n'existe aujourd'hui :

- Wallet : boutons Retirer / Déposer / Transférer (`GET/POST /api/me/wallet*` n'expose que le
  solde et l'historique — aucun retrait/dépôt/transfert self-service n'existe côté utilisateur) ;
- Carte Wasplex (spécifiée dans `docs/10-carte-wasplex.md`, aucun module implémenté) ;
- Parrainage (code de parrainage, compteur de filleuls, bonus « +200 WP/filleul ») ;
- Vérification d'identité / KYC (lien « Vérifier mon identité », tuile « Identité & KYC »).

**Décision du fondateur** : le visuel de la maquette est repris tel quel (mêmes cartes, mêmes
boutons), mais toute action sans API réelle est **inerte** (désactivée ou affiche « Bientôt
disponible » au clic) — cohérent avec le traitement déjà en place des onglets Fonds/Alertes.
**Aucune donnée n'est fabriquée** : pas de faux code de parrainage, pas de faux historique Carte
Wasplex, pas de faux montant. Ces sections reviendront avec leur propre chantier backend.

### 3.2. Onglet de navigation « Fonds »

La maquette remplace l'onglet « Fonds » (module documenté ailleurs dans `docs/`, déjà relié au
champ réel `fonds_eligible` sur les plans d'abonnement — voir `SubscriptionPanel.vue`) par un
onglet « Social », concept absent de toute la documentation.

**Décision du fondateur** : conserver l'onglet **Fonds** (toujours un placeholder « bientôt
disponible » aujourd'hui), seulement rhabillé avec une icône SVG ligne cohérente avec le reste du
rail au lieu de l'emoji 🎯. L'onglet « Social » de la maquette n'est pas introduit dans ce
chantier.

### 3.3. Geste décoratif « appuie sur la bonne image » (`TapMatchGate`)

P001-C (§5.1) avait introduit ce geste comme signature visuelle nostalgique, explicitement documenté
comme non sécuritaire. La nouvelle maquette de connexion, fournie directement par le fondateur
comme référence définitive de cet écran, ne le montre plus.

Le composant `TapMatchGate.vue` reste dans le dépôt (non supprimé, au cas où il doive revenir
ailleurs) mais n'est plus intégré dans le nouveau flux de connexion/inscription. Point ouvert
signalé au fondateur dans le rapport de fin de chantier plutôt que remplacé silencieusement.

## 4. Périmètre inclus

- Nouveau logo transparent intégré (connexion, Feed) — voir `public/brand/README.md`.
- Écran Connexion/Inscription : `Landing.vue` + `PhoneQuickConnect.vue` recomposés en un écran
  compact unique (plus de scroll séparé « Commencer maintenant » → section connexion), 3 cartes
  de valeur, segmented control Connexion/Inscription, champs téléphone (indicatif séparé) et mot
  de passe, bouton dégradé bleu→cyan, footer compte universel.
- Rail de navigation bas de `UserShell.vue` : icônes SVG ligne au lieu des emoji, bouton Wallet
  central surélevé en dégradé orange→or.
- Onglet Mon Espace : carte profil (avatar initiales, statut, lien identité inerte), grille
  d'actions rapides 2×2 (Wallet, Abonnement, Carte Wasplex inerte, Identité & KYC inerte),
  bandeau Studio Annonceur (réutilise `BecomeAdvertiserPanel.vue` déjà existant), carte Profil
  intelligent (réutilise `SmartProfilePanel.vue`, pourcentage réel dérivé des données existantes
  — pas de bonus WP par tâche fabriqué), carte Abonnement simple (réutilise l'abonnement actif
  de `SubscriptionPanel.vue` sans comparatif de tiers gamifié), carte Partager & inviter
  (inerte), liste Compte & sécurité / Services Wasplex (inerte).
- Onglet Wallet : carte de solde animée (respiration lumineuse + reflet, `prefers-reduced-motion`
  respecté), 3 boutons d'action (inertes), 3 statistiques (Aujourd'hui/Ce mois calculés depuis
  l'historique réel déjà chargé ; Carte Wasplex inerte à 0), section Nos offres (upgrade
  abonnement réel + Carte Wasplex inerte), historique restructuré.
- Feed : en-tête repensé (logo animé en pulsation douce, onglets Pour toi/Explorer, pastille de
  solde WP, ligne de progression réelle de l'attention repositionnée sous l'en-tête), rail
  d'actions droit qui inclut désormais l'icône Alertes (inerte, badge de compte) en plus des
  actions sociales réelles (like/commentaire/enregistrer/partager), avatar carré annonceur ajouté
  au bloc de bas d'écran.

## 5. Périmètre exclu

- Tout nouveau module backend (Carte Wasplex, Parrainage, KYC, retrait/dépôt/transfert
  self-service utilisateur) — hors sujet de ce chantier design, reviendra avec ses propres
  migrations/services/tests financiers.
- L'onglet « Social » de la maquette (remplacé par le maintien de « Fonds », §3.2).
- La réintégration du geste `TapMatchGate` (§3.3) — point ouvert.
- La réarchitecture du Feed en plein écran avec navigation superposée (la maquette montre un
  Feed plein écran avec la barre de nav flottant par-dessus la vidéo) : le Feed reste une carte
  `aspect-[9/16]` contenue dans la mise en page actuelle de `UserShell`, pour ne pas risquer de
  régression sur le moteur d'attention/gain déjà fonctionnel et testé. Écart de fidélité assumé
  et documenté plutôt que fait silencieusement.
- Les barres latérales Studio Annonceur / Administration (`AdvertiserShell.vue`,
  `AdminShell.vue`) — non couvertes par la maquette fournie, hors périmètre.
- Toute déclinaison graphique du logo autre que celle fournie (mascotte isolée seule, monochrome,
  wordmark seul, favicon) — toujours absentes, voir `public/brand/README.md`.

## 6. Pages, routes et migrations concernées

Aucune migration, aucun événement, aucune capacité nouvelle : chantier strictement frontend,
réutilisant les endpoints existants (`/api/me/wallet`, `/api/me/wallet/history`,
`/api/me/smart-profile`, `/api/subscriptions/*`, `/api/organizations`, `/api/me/spaces`,
`/api/feed/*`).

## 7. Tests

- `npm run types:check`, `npx eslint`, `npm run format:check`.
- `php artisan test` (non-régression complète — aucun changement backend attendu).
- Captures Playwright des 4 écrans (mobile 390×844), avant/après si pertinent.

## 8. Chantier suivant recommandé

Selon la décision du fondateur sur les points ouverts (§3.3, Carte Wasplex, Parrainage, KYC,
retrait/dépôt/transfert utilisateur) : le premier module backend à activer derrière ces
placeholders inertes.
