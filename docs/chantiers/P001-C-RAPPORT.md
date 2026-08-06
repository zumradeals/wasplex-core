# RAPPORT — P001-C : Accueil public, connexion rapide par téléphone et Feed en première destination

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `5d681d1` (retrigger CI de P003, PR #23 encore ouverte à ce stade)
**Chantier :** `docs/chantiers/P001-C-CHANTIER.md`
**Statut :** ready_for_review

---

## 1. Objectif

Construire la coquille visuelle publique et post-connexion de Wasplex, sur instruction directe
du fondateur, hors séquence de roadmap : une page d'accueil publique verticale mobile-first
avec connexion rapide par téléphone, un geste décoratif hérité de l'ancien design, le Feed
comme première destination après connexion, et l'intégration du premier asset de marque réel
fourni par le fondateur (logo martin-pêcheur + logotype).

## 2. Réalisé

### 2.1. Logo officiel

- `apps/platform/public/brand/source/wasplex-logo-official-source.jpg` — copie exacte du fichier
  fourni par le fondateur, conservée comme source de vérité.
- `apps/platform/public/brand/wasplex-logo-full.png` — conversion sans perte (Pillow, aucune
  retouche) du JPG en PNG.
- `apps/platform/public/brand/wasplex-app-icon-512.png` / `wasplex-app-icon-192.png` —
  redimensionnements du même asset (pas de nouveau dessin).
- `apps/platform/public/brand/README.md` — état exact des variantes attendues par `docs/00` §4.3
  (présentes vs manquantes) et pourquoi les manquantes ne sont pas fabriquées automatiquement
  (détourage/vectorisation = travail graphique réel, interdit à improviser par §4.7).
- Intégré dans Login, Register, Landing, UserShell, AdvertiserShell, AdminShell — toujours
  affiché sur une plaque blanche arrondie assumée (le PNG a un fond blanc intrinsèque ; aucun
  détourage approximatif n'a été tenté).

### 2.2. Page d'accueil publique (`/`)

- `GuestPagesController::landing()` : rend `Identity/Landing` pour un invité ; redirige un compte
  déjà authentifié vers son espace actif (`/app`, `/studio` ou `/admin` selon le type d'espace),
  au lieu de revoir la vitrine.
- `resources/js/Pages/Identity/Landing.vue` : verticale, mobile-first (max-w-md même sur
  desktop, conformément à la doctrine responsive utilisateur de `docs/00` §14/CLAUDE.md §14),
  logo, accroche, trois cartes de valeur (Gratuit / 1 WP = 1 FCFA / Mobile Money), liste de
  fonctionnalités sourcées de `docs/06`, `docs/19` §32 (Mobile Money) — aucun chiffre de gain
  inventé (l'ancien "+30 WP/pub" n'a pas été repris : non confirmé par `docs/`, et en tension
  avec les quotas de classes à venir en P004).
- Connexion rapide intégrée (`#connexion-rapide`), ancre pour le bouton "Commencer maintenant".

### 2.3. Connexion rapide par téléphone

- `resources/js/Components/PhoneQuickConnect.vue` : tabs Connexion/Inscription, sélecteur
  d'indicatif (6 pays d'Afrique de l'Ouest où GeniusPay/Mobile Money s'appliquent), champ
  numéro, mot de passe, geste décoratif, bouton désactivé tant que le geste n'est pas résolu et
  que le formulaire n'est pas valide. Utilise **exactement** `POST /api/register` et
  `POST /api/login` existants (`identifier_type: phone`) — **aucune modification backend**.
- Un lien "Options avancées (email, autre méthode)" renvoie vers `/login` complet (inchangé,
  toujours utilisable par email — nécessaire pour le compte fondateur/admin).

### 2.4. Geste décoratif "appuie sur la bonne image"

- `resources/js/Components/TapMatchGate.vue` — grille de 6 émojis-fruits, cible affichée en
  texte, tape correcte → validé, tape incorrecte → secousse puis nouvelle grille. **Documenté en
  tête de fichier comme non sécuritaire** (voir §5.1 du chantier) : aucun document `docs/` ne
  spécifie de CAPTCHA, ce composant est une signature visuelle et un frein doux, pas un contrôle
  antifraude. La sécurité réelle (rate limiting, sessions, MFA, audit) reste entièrement côté
  serveur, inchangée depuis P001.

### 2.5. Feed en première destination

- `UserShell.vue` : `activeTab` par défaut passe de `espace` à `feed`.
- Le contenu du Feed n'existe pas encore (P008/P009) : au lieu du placeholder générique partagé
  par les autres onglets non construits, le Feed reçoit un habillage immersif dédié (dégradé
  navy, logo, message d'attente explicite mentionnant P008-P009) pour ne pas donner
  l'impression d'un onglet cassé au tout premier écran vu par l'utilisateur.

### 2.6. Page technique déplacée

- `apps/platform/routes/web.php` : `/` ne pointe plus vers `TechnicalPageController` ; la page
  vit maintenant sur `/status`, comportement inchangé.

## 3. Bug corrigé en cours de chantier (P000, latent, sans rapport avec P001-C)

`config/inertia.php` n'existait pas, donc le chemin par défaut du paquet
(`resource_path('js/pages')`, **minuscule**) faisait autorité. Sur un système de fichiers
sensible à la casse, ce chemin ne correspond pas à la convention réelle du dépôt
(`resources/js/Pages`, PascalCase — toutes les pages Inertia du projet vivent là). Le bug était
invisible jusqu'ici car **aucun test existant n'utilisait `assertInertia()->component()`** ; le
premier test de ce chantier (`PublicPagesTest`) l'a immédiatement révélé
(`Inertia page component file [Identity/Landing] does not exist.`). Corrigé en publiant
`config/inertia.php` avec le chemin correct (`resource_path('js/Pages')`). Portée générale,
bénéficie à tout le dépôt, pas seulement à ce chantier.

## 4. CI — incident PR #23 (P003) découvert pendant ce chantier

En reprenant le suivi de la PR #23 (P003), la conversion draft → prêt pour revue n'a déclenché
aucune exécution CI (`ready_for_review` n'est pas dans l'ensemble par défaut des événements
`pull_request` de GitHub Actions — seulement `opened`, `synchronize`, `reopened`). Plus
inattendu : `opened` lui-même n'avait produit **aucune** exécution de workflow pour le commit
`362a09b` (vérifié via `list_workflow_runs` sur la branche — zéro run après 22:15Z, contrairement
aux PR précédentes). Un commit vide (`chore(ci): retrigger CI for P003 PR`, `5d681d1`) a été
poussé pour forcer un événement `synchronize` et relancer la CI, sans changement de code. Ce
commit a été isolé de ce chantier (travail de P001-C mis de côté avec `git stash` pendant
l'opération) pour ne pas mélanger les scopes.

## 5. Tests

- `tests/Feature/Identity/PublicPagesTest.php` — 4 tests : landing rendue pour un invité,
  redirection d'un compte authentifié, `/status` toujours fonctionnelle, `/` ne sert plus la
  page technique.
- `composer test` : **69 tests, 379 assertions, aucune régression**.
- `composer lint:check` (Pint) : passé.
- `npm run format:check` / `lint:check` / `types:check` / `build` : passés (formatage
  auto-corrigé sur les 3 nouveaux fichiers Vue).
- Parcours navigateur (Playwright/Chromium) :
  - Landing mobile (390px) et desktop (1440px) — captures conformes à `docs/00`.
  - `/status` toujours accessible et fonctionnelle après déplacement.
  - Parcours complet : inscription par téléphone → résolution du geste → compte créé → atterrit
    sur `/app` avec l'onglet Feed actif et son habillage immersif.
  - `/login` : nouveau badge logo (plaque blanche arrondie) rendu correctement.

## 6. Limites restantes

- **Pas d'OTP SMS** : la connexion par téléphone utilise le même couple identifiant + mot de
  passe que l'email. Aucun contrat de fournisseur SMS n'existe dans `docs/` ; en ajouter un
  serait une décision produit hors périmètre de ce chantier.
- **Déclinaisons de logo manquantes** : SVG vectoriel, mascotte isolée (fond transparent),
  monochrome, wordmark seul, favicon dédié — nécessitent un vrai travail graphique (détourage,
  vectorisation), documenté et en attente dans `public/brand/README.md`. Le logo est utilisé tel
  quel (PNG, plaque blanche) partout où une déclinaison compacte serait normalement attendue.
- **Le Feed reste un placeholder** : seul l'habillage change, aucun contenu réel (P008/P009).
- **PR #23 (P003)** : CI relancée par ce chantier via un commit vide ; son état de fusion reste
  à vérifier séparément (hors périmètre P001-C).

## 7. Fichiers modifiés/ajoutés

```text
apps/platform/config/inertia.php                                          (nouveau)
apps/platform/public/brand/README.md                                      (nouveau)
apps/platform/public/brand/source/wasplex-logo-official-source.jpg        (nouveau)
apps/platform/public/brand/wasplex-logo-full.png                          (nouveau)
apps/platform/public/brand/wasplex-app-icon-512.png                       (nouveau)
apps/platform/public/brand/wasplex-app-icon-192.png                       (nouveau)
apps/platform/app/Modules/Identity/Http/Controllers/GuestPagesController.php (modifié)
apps/platform/app/Modules/Identity/Http/routes/web.php                    (modifié)
apps/platform/routes/web.php                                              (modifié)
apps/platform/resources/js/Pages/Identity/Landing.vue                     (nouveau)
apps/platform/resources/js/Components/PhoneQuickConnect.vue               (nouveau)
apps/platform/resources/js/Components/TapMatchGate.vue                    (nouveau)
apps/platform/resources/js/Pages/Identity/Login.vue                       (modifié — logo)
apps/platform/resources/js/Pages/Identity/Register.vue                    (modifié — logo)
apps/platform/resources/js/Pages/Identity/UserShell.vue                   (modifié — Feed-first, logo)
apps/platform/resources/js/Pages/Identity/AdvertiserShell.vue             (modifié — logo)
apps/platform/resources/js/Pages/Identity/AdminShell.vue                  (modifié — logo)
apps/platform/tests/Feature/Identity/PublicPagesTest.php                  (nouveau)
docs/chantiers/P001-C-CHANTIER.md                                         (nouveau)
docs/chantiers/P001-C-RAPPORT.md                                          (nouveau, ce fichier)
```

Aucune migration, aucune capacité, aucun événement métier nouveau.

## 8. État Git

Travail réalisé sur `claude/wasplex-reconstruction-7ujym7`, au-dessus du commit `5d681d1`
(retrigger CI, isolé de P003). Prêt pour commit, push et ouverture de PR.

## 9. Chantier suivant recommandé

P004 — Configurations, plans et classes.
