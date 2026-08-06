# P001-C — Accueil public, connexion rapide par téléphone et Feed en première destination

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `800f781` (P002 fusionné) + `362a09b` (P003, en revue)
**Dépendances :** P001 (comptes/espaces), P001-B (identité visuelle officielle)
**Statut :** proposed

---

## 1. Origine et décision du fondateur

Aucun `P001-C-CHANTIER.md` n'existait. Ce chantier est né d'une instruction directe du
fondateur, hors séquence de la roadmap initiale, qui a explicitement demandé de le traiter
avant P004 :

> « je vise la perfection visuel. que wasplex soit Beau, jolie et agréable a regarder.
> Wasplex est responsive mobile avant tout même sur desktop pour les utilisateurs. [...]
> la page d'accueil doit rédigé vers une page verticale mobile où la connexion se fait
> avec numéro uniquement. et après connexion la première atterrissage est le Feed.
> j'ai encore en mémoire l'ancien design de wasplex [...] je veux que tu en fasse un
> amélioré davantage. »

Le fondateur a ensuite fourni le premier asset de marque réel (logo officiel, martin-pêcheur
+ logotype) — voir `apps/platform/public/brand/README.md` pour l'état exact des déclinaisons
disponibles et manquantes.

## 2. Objectif

Construire la coquille visuelle publique et post-connexion de Wasplex :

1. une page d'accueil publique (`/`), verticale, mobile-first, qui remplace l'actuelle page
   technique de vérification du socle ;
2. une connexion rapide intégrée à cette page, **par numéro de téléphone uniquement** ;
3. un geste de friction décoratif inspiré de l'ancien design (« appuie sur la bonne image »),
   documenté explicitement comme **non sécuritaire** ;
4. le Feed comme première destination après connexion, à la place de « Mon Espace ».

Ce chantier ne construit pas le Feed réel (Matching, contenu publicitaire, attention) : cela
reste le périmètre de P008/P009. Il installe uniquement la place et l'habillage du Feed comme
priorité de navigation, avec un espace d'attente soigné.

## 3. Périmètre inclus

- Nouveau contrôleur/page `Landing` sur `/`.
- Déplacement de la page technique (`docs/00` n'en parle pas — c'est un outil de vérification
  P000, pas une page de marque) de `/` vers `/status`.
- Composant `PhoneQuickConnect` : connexion et inscription par téléphone uniquement, réutilisant
  les endpoints API existants (`POST /api/register`, `POST /api/login`) qui acceptent déjà
  `identifier_type: phone` sans aucune modification backend.
- Composant décoratif `TapMatchGate` (grille d'émojis, appuyer sur celui qui correspond à la
  cible affichée) — encapsulé, réutilisable, clairement commenté comme frein UX et non comme
  mesure antifraude.
- `UserShell.vue` : onglet actif par défaut `feed` au lieu de `espace`, avec un habillage
  immersif du placeholder (mascotte, message d'attente) au lieu du bloc générique partagé par
  les autres onglets non construits.
- Intégration du logo officiel (`public/brand/wasplex-logo-full.png`) dans les en-têtes/pages
  où un simple badge texte tenait lieu de logo (Landing, Login, Register, UserShell,
  AdvertiserShell, AdminShell).
- Redirection : un compte déjà authentifié qui visite `/` est renvoyé vers son espace actif au
  lieu de revoir la page publique.

## 4. Périmètre exclu

- Le contenu réel du Feed (vidéos, matching, attention, crédit automatique) — P008/P009.
- Les déclinaisons graphiques manquantes du logo (vectorisation, mascotte isolée, monochrome,
  wordmark seul, favicon dédié) — nécessitent un travail graphique réel, pas une conversion de
  fichier ; voir `public/brand/README.md`.
- L'OTP par SMS (le hotfix/contrat d'un fournisseur SMS n'existe pas dans `docs/`) — la
  connexion par téléphone utilise le même couple identifiant + mot de passe que l'email
  aujourd'hui, pas un code à usage unique. Documenté comme limite.
- Toute modification du contrat `AuthController`/`AccountRegistrationService` — le backend
  accepte déjà `phone`, aucun changement n'est nécessaire ni souhaité ici.
- Le multi-langue de l'indicatif pays (sélecteur de drapeau complet) — un champ pays ISO2 texte
  suffit à ce stade, comme sur les écrans existants.

## 5. Décisions explicites

### 5.1. Le geste « appuie sur la bonne image » n'est pas une mesure de sécurité

L'ancien écran de connexion Wasplex affichait « Sécurité · Appuie sur la même image » avec une
grille de fruits. Aucun document de `docs/` ne spécifie de CAPTCHA ni de mécanisme antibot par
reconnaissance d'image. Ce geste est donc repris **uniquement comme signature visuelle et
frein ergonomique doux** (cohérence avec l'ancien produit que le fondateur ne veut pas regretter),
et non comme contrôle de sécurité. La sécurité réelle de l'authentification reste : rate
limiting, sessions révocables, MFA, audit — déjà en place depuis P001. Le composant
`TapMatchGate` est documenté en tête de fichier avec cette clarification pour qu'aucun
développeur futur ne le confonde avec une protection réelle.

### 5.2. La page technique déménage, elle n'est pas supprimée

`TechnicalPageController` reste utile (vérification socle/santé) mais n'a pas vocation à
occuper la route racine `/` d'un produit grand public. Elle est déplacée vers `/status`, sans
changement de comportement.

### 5.3. Logo : utilisation du seul asset disponible, sans fabrication de variantes

Le PNG officiel a un fond blanc intrinsèque à l'illustration fournie. Plutôt que de tenter un
détourage automatique approximatif (interdit par `docs/00` §4.7 : « découper l'oiseau »), le
logo est présenté sur les fonds sombres dans une plaque blanche arrondie explicite (choix de
composition assumé et visible, pas un fond ajouté par erreur). Cette limite est documentée et
sera levée dès que des déclinaisons vectorielles/isolées seront fournies.

## 6. Pages et routes concernées

```text
GET  /            → Landing (public, connexion rapide intégrée)     [nouveau]
GET  /status       → TechnicalPageController (santé/debug)          [déplacé depuis /]
GET  /login        → Login (formulaire complet, email ou téléphone) [inchangé]
GET  /register     → Register (formulaire complet)                  [inchangé]
GET  /app          → UserShell, onglet par défaut = feed            [modifié]
```

Aucune migration, aucun événement, aucune capacité nouvelle : ce chantier est strictement
frontend + un contrôleur de page + une redirection.

## 7. Tests

- Pest : route `/` répond 200 pour un invité et affiche le composant `Landing` ; redirige vers
  l'espace actif pour un compte authentifié ; `/status` répond 200 avec les mêmes données que
  l'ancienne route racine.
- Playwright : parcours visuel mobile (390px) et desktop (1440px) de la Landing, capture de
  l'état du gate décoratif résolu/non résolu, capture de `UserShell` atterrissant sur Feed.

## 8. Chantier suivant recommandé

P004 — Configurations, plans et classes.
