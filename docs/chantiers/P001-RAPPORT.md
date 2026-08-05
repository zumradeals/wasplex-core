# P001 — RAPPORT DE CHANTIER

**Chantier :** P001 — Compte universel, espaces, capacités et shells
**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `fb48a9421a4f33132523d70f1d6947e16d90328f` (P000 fusionné sur `main`)
**Statut :** `ready_for_review` — en attente d'autorisation du fondateur avant P002

## Contexte

Ce rapport remplace intégralement l'ancien rapport P001 (branche
`codex/p001-identity-spaces-capabilities`, code aujourd'hui supprimé lors de
la réinitialisation du dépôt décidée par le fondateur). Il documente la
reconstruction de P001 à partir de `docs/chantiers/P001-CHANTIER.md`, sur la
base du socle P000 déjà fusionné sur `main`.

## Objectif

Établir une identité Wasplex unique et des contextes utilisateur, annonceur
et administration séparés avant toute logique financière ou publicitaire.

## Inclus (réalisé)

- module `App\Modules\Identity` (Domain/Application/Infrastructure/Http/
  Database/Console), 12 migrations : comptes, identifiants normalisés
  (email/téléphone), appareils, sessions applicatives révocables, profil
  personnel minimal, organisations, memberships d'organisation, invitations
  d'organisation, espaces utilisateur (`user_spaces` : user/advertiser/admin),
  memberships d'espace, capacités (`capability_grants`, scope organisation ou
  espace, expirables/révocables), journal d'audit append-only
  (`account_audit_events`) ;
- `CapabilityChecker` : aucune capacité globale implicite, vérifie statut +
  fenêtre `starts_at`/`expires_at` + correspondance de périmètre exacte ;
- sessions applicatives (`AccountSessionManager`) distinctes de la session
  Laravel sous-jacente, révocables indépendamment
  (`EnsureSessionNotRevoked`, table `account_sessions` liée par
  `laravel_session_id`) ;
- MFA TOTP interne (`MfaService` + `pragmarx/google2fa`, aucun fournisseur
  externe, aucun code de récupération — conforme au périmètre exclu) : secret
  chiffré au repos (`Account::$casts`), jamais renvoyé après l'enrôlement,
  fenêtre de MFA récente de 15 minutes (`EnsureRecentMfa`) exigée pour toute
  action d'administration ;
- inscription (`AccountRegistrationService`), organisations
  (`OrganizationRegistrationService` : création → membership propriétaire →
  espace annonceur → capacité `organization.manage.self`), invitations
  d'organisation expirables (72h, jeton haché, acceptation liée strictement
  à l'identifiant invité — `OrganizationInvitationService`), changement
  d'espace (`SpaceService`), journal d'audit (`AuditLogger`) ;
- middlewares `EnsureSessionNotRevoked`, `EnsureCapability` (acteur + espace
  + organisation + capacité + périmètre, docs/17 §11), `EnsureRecentMfa`,
  `EnsureSpaceMembership` ;
- API `web` (session + CSRF, pas `api` stateless) : inscription, connexion,
  déconnexion, `/me` (lecture/mise à jour), espaces (liste/changement),
  sessions (liste/révocation), MFA (enrôlement/confirmation/vérification),
  organisations (liste/création/détail/membres/invitations), console
  d'administration (capacités : liste/octroi/révocation), chacune gardée par
  les capacités `admin.*` appropriées ;
- commande `identity:seed-founder` (bootstrap unique, idempotent, capacités
  auto-octroyées documentées comme exception de démarrage) ;
- shells Inertia/Vue : `Login`/`Register` (formulaires réels), `UserShell`
  (mobile-first, navigation Feed/Fonds/Wallet/Alertes/Mon Espace — onglets
  hors périmètre affichent « bientôt disponible », Mon Espace réel), `Studio
  Annonceur` (desktop + sélecteur mobile, onglet Équipe interroge réellement
  `/organizations/{id}/members`), `Administration` (desktop, onglet
  Capacités fonctionnel : liste/octroi/révocation réels via l'API admin),
  `AdminMfaChallenge` (enrôlement TOTP → confirmation → vérification →
  redirection) ; `HandleInertiaRequests` partage `auth` (compte, MFA,
  espaces accessibles, espace actif) à toutes les pages.

## Exclus (respecté)

Vérification d'adresse électronique et adaptateur d'envoi d'invitation,
SmartProfile complet/KYC/gestion documentaire, Grand Livre/Wallet/paiements/
réservations, marques/campagnes/Feed, fournisseur MFA externe et codes de
récupération.

## Migrations

12 migrations dans `app/Modules/Identity/Database/Migrations/` (préfixe
`2026_08_05_1800xx`) : `accounts`, `account_identifiers`, `account_devices`,
`account_sessions`, `personal_profiles`, `organizations`,
`organization_memberships`, `organization_invitations`, `user_spaces`,
`space_memberships`, `capability_grants`, `account_audit_events`. Aller/retour
vérifié (`migrate:fresh` puis `migrate:rollback --step=12` puis `migrate`) sur
PostgreSQL 16 local (cible documentée : PostgreSQL 17, cf. limite ci-dessous).

## API (23 routes)

`POST /api/register`, `POST /api/login`, `POST /api/logout`,
`GET|PATCH /api/me`, `GET /api/me/spaces`,
`POST /api/me/spaces/{userSpace}/switch`, `GET /api/me/sessions`,
`DELETE /api/me/sessions/{session}`, `POST|PUT /api/me/mfa`,
`POST /api/me/mfa/verify`, `GET|POST /api/organizations`,
`GET /api/organizations/{organization}`,
`GET|DELETE /api/organizations/{organization}/members[/{membership}]`,
`POST /api/organizations/{organization}/invitations`,
`POST /api/organizations/invitations/{invitation}/accept`,
`GET|POST /api/admin/capabilities`, `DELETE /api/admin/capabilities/{grant}`.
Web : `/login`, `/register`, `/app`, `/studio` (gardé par
`EnsureSpaceMembership:advertiser`), `/admin/mfa-challenge`, `/admin` (gardé
par `EnsureCapability` + `EnsureRecentMfa`).

## Événements / audit

Pas d'événements outbox à ce stade (hors périmètre financier de P001,
conforme §9). Audit append-only via `AccountAuditEvent`
(`account_audit_events`, pas de `updated_at`) : actions journalisées
`UserSpaceSwitched`, `CapabilityGranted`, `CapabilityRevoked`,
`DataAccessDenied` (accès refusé faute de capacité).

## Permissions

Capacités explicites, contextualisées, expirables et révocables — aucune
capacité globale implicite (docs/17 §11, §6). Les capacités `admin.*`
(`admin.dashboard.view`, `admin.capabilities.grant`,
`admin.capabilities.revoke`, `admin.audit.view`) sont les seules accordées
au fondateur au bootstrap, explicitement et individuellement, pas via un
rôle implicite.

## Invariants vérifiés

- une capacité expirée est refusée (test) ;
- une capacité révoquée est refusée même avant son expiration (test) ;
- une capacité scopée à une organisation ne s'applique pas à une autre
  (test — voir bug corrigé ci-dessous) ;
- une session applicative révoquée est refusée indépendamment de la session
  Laravel sous-jacente (test) ;
- une invitation ne peut être acceptée que par le compte possédant
  l'identifiant ciblé (test) ;
- la console d'administration exige une preuve MFA récente (test, fenêtre
  15 minutes) ;
- les secrets TOTP sont chiffrés au repos et jamais renvoyés après
  l'enrôlement (cast `encrypted`, `hidden` sur le modèle `Account`).

## Bug corrigé en cours de chantier : scope de capacité stringifié via un modèle lié

`EnsureCapability::resolveScope()` faisait `(string) $request->route($routeParam)`
sur un paramètre de route lié (route model binding). Pour un modèle Eloquent,
`(string)` invoque `__toString()`, qui renvoie la représentation JSON complète
de l'enregistrement — pas sa clé. Conséquence : lors de l'audit d'un refus de
capacité sur une route `{organization}`, la colonne
`account_audit_events.organization_id` (CHAR(26), clé étrangère réelle vers
`organizations`) recevait ce JSON et PostgreSQL renvoyait
`SQLSTATE[22001]: String data, right truncated ... value too long for type
character(26)`. Corrigé en détectant explicitement une instance de
`Illuminate\Database\Eloquent\Model` et en utilisant `->getKey()`.

## Bug corrigé en cours de chantier : `scope_id` à largeur fixe

`capability_grants.scope_id` référence de façon polymorphe soit une
organisation soit un espace (ce n'est pas une clé étrangère), et avait été
défini avec `$table->ulid('scope_id')->nullable()`, qui crée une colonne
PostgreSQL `CHAR(26)` à largeur fixe. Toute valeur plus courte qu'un ULID de
26 caractères est complétée par des espaces par PostgreSQL, ce qui casse la
comparaison stricte `===` dans `CapabilityGrant::matchesScope()` — confirmé
en inspectant directement la colonne (`\d capability_grants`) puis en
reproduisant l'échec du test `CapabilityTest`. Corrigé en remplaçant la
colonne par `$table->string('scope_id', 26)->nullable()`. En production, où
`scope_id` contient toujours un ULID réel de 26 caractères, ce bug était
invisible ; il n'a été révélé que par un test utilisant des identifiants
courts (`org-a`/`org-b`), ce qui reste la bonne pratique pour cette colonne
non-FK plutôt que d'exiger des ULID factices dans les tests.

## Difficulté résolue : continuité de session dans les tests Pest

La suite de tests d'authentification par session échouait systématiquement
avec `401 SESSION_REVOKED` juste après une inscription/connexion réussie.
Cinq causes cumulées, découvertes et corrigées successivement :

1. `SESSION_DRIVER=array` (défaut Laravel pour les tests) ne persiste jamais
   entre deux appels HTTP simulés séparés au sein d'un même test — confirmé
   en constatant qu'aucune session n'apparaissait jamais dans Redis.
   `phpunit.xml` fixe désormais `SESSION_DRIVER=redis` (avec
   `REDIS_HOST`/`REDIS_PORT`), conforme à la cible réelle et à CI.
2. `postJson()`/`getJson()` n'envoient **aucun cookie** par défaut (miroir du
   flag `withCredentials` d'XHR, `false` par défaut) — il faut appeler
   explicitement `withCredentials()`.
3. Rejouer le cookie de session via `withCookie()` le rechiffre une seconde
   fois puisque cette méthode attend une valeur en clair ; la valeur
   `Set-Cookie` déjà chiffrée doit être rejouée telle quelle via
   `withUnencryptedCookie()`.
4. Un bug de portée (`EnsureCapability`, voir ci-dessus) provoquait une
   erreur serveur masquant le vrai symptôme sur certains tests.
5. `test()` renvoie un `Pest\Support\HigherOrderTapProxy`, pas directement le
   `TestCase` — l'inspection réflexive directe de ses propriétés échoue ; il
   faut passer par les façades (`app('session')->getId()`).

Le helper `registerAndLogin()` dans `tests/Pest.php` encapsule la solution
complète pour tous les tests Identity.

## Tests exécutés

| Contrôle | Résultat |
|---|---|
| `composer test` (Pest 4) | Réussi — 31 tests, 153 assertions |
| `composer lint:check` (Pint) | Réussi |
| `composer types:check` (Larastan) | **Non exécuté** — paquet non installable dans ce bac à sable réseau restreint (cf. P000-RAPPORT.md, limite déjà documentée, inchangée) |
| `npm run format:check` (Prettier) | Réussi |
| `npm run lint:check` (ESLint) | Réussi |
| `npm run types:check` (vue-tsc) | Réussi |
| `npm run build` (Vite) | Réussi |
| `migrate:fresh` puis `migrate:rollback --step=12` puis `migrate` | Réussi, aller/retour propre |
| `identity:seed-founder` (exécution + ré-exécution) | Réussi — création puis rejet idempotent (« Un compte existe déjà ») |
| Parcours navigateur (Playwright/Chromium) : inscription → `/app`, création d'organisation → `/studio` (onglet Équipe réel), connexion fondateur → enrôlement MFA TOTP → vérification → `/admin` (onglet Capacités réel) | Réussi, captures ci-dessous |

Détail des 31 tests Pest (`tests/Feature/Identity/`) : inscription
(`RegistrationTest`), changement d'espace (`SpaceSwitchingTest`), capacités —
expiration/révocation/périmètre inter-organisation (`CapabilityTest`),
révocation de session (`SessionRevocationTest`), parcours MFA (`MfaTest`),
invitation et adhésion d'organisation (`OrganizationInvitationTest`).

## Captures

Six captures prises via Chromium/Playwright contre le serveur de
développement (`php artisan serve`), parcours réels de bout en bout :

1. `/login` (mobile, 390×844).
2. `/register` (mobile, 390×844).
3. `/app` — `UserShell`, compte fraîchement inscrit, MFA non activée,
   navigation Feed/Fonds/Wallet/Alertes/Mon Espace (mobile, 390×844).
4. `/studio` — `AdvertiserShell`, onglet Équipe listant réellement le
   propriétaire via `GET /organizations/{id}/members` après création d'une
   organisation par API (desktop, 1280×800).
5. `/admin/mfa-challenge` — enrôlement TOTP du fondateur, secret affiché,
   code à confirmer (desktop, 1280×800).
6. `/admin` — `AdminShell` après vérification MFA réussie, onglet Capacités
   (desktop, 1280×800).

## Fichiers modifiés/créés (résumé)

- `app/Modules/Identity/` : domaine complet (56 fichiers PHP — migrations,
  modèles, services applicatifs, exceptions, middlewares, contrôleurs API et
  web, routes, provider, commande console) ;
- `config/auth.php` : recréé (guard `web` session, provider Eloquent
  `Account`) ;
- `bootstrap/providers.php` : `IdentityServiceProvider` ;
- `bootstrap/app.php` : rendu JSON standard pour `AuthenticationException`
  (`UNAUTHENTICATED`, 401) ;
- `app/Http/Middleware/HandleInertiaRequests.php` : partage `auth` (compte,
  espaces, espace actif) ;
- `resources/js/lib/http.ts`, `resources/js/types/identity.ts`,
  `resources/js/Components/SpaceSwitcher.vue`,
  `resources/js/Pages/Identity/*.vue` (6 pages) ;
- `phpunit.xml` : `SESSION_DRIVER=redis` + hôte/port Redis (justifié en
  commentaire) ;
- `tests/Pest.php` : helper `registerAndLogin()` ;
- `tests/Feature/Identity/` : 6 fichiers de tests.

## Limites restantes

1. Larastan/PHPStan toujours non installable dans ce bac à sable réseau
   restreint (même blocage que P000, non résolu ici, à exécuter depuis un
   poste avec accès réseau complet).
2. PostgreSQL 16 utilisé en local pour les preuves (17 reste la cible
   documentée et utilisée en CI, cf. P000).
3. Aucun envoi réel d'invitation (email/SMS) — le jeton est généré et haché
   mais son canal de livraison est explicitement hors périmètre P001.
4. Aucune vérification d'adresse (email/téléphone) — hors périmètre P001.
5. Les shells n'exposent que la navigation réelle du périmètre P001 (compte,
   espaces, équipe, capacités) ; les autres onglets (Feed, Fonds, Wallet,
   Alertes, Marques, Campagnes, Tableau de bord, Audit) affichent un état
   « bientôt disponible » authentique — aucune donnée simulée n'est affichée
   pour ces domaines, conformément à l'absence de logique
   financière/publicitaire à ce stade.

## Risques

- Le bug de largeur fixe sur `scope_id` aurait pu rester invisible en
  production (ULID toujours 26 caractères) sans un test utilisant des
  identifiants courts ; vigilance à conserver pour toute future colonne
  polymorphe non-FK utilisant `ulid()` plutôt que `string(26)`.
- La configuration de test (`SESSION_DRIVER=redis`) rapproche l'environnement
  de test de la cible réelle, mais introduit une dépendance dure à un Redis
  disponible pour lancer la suite Pest (déjà le cas en CI).

## Décisions ouvertes pour le fondateur

- Confirmer le périmètre de scission Utilisateur/Annonceur/Administration
  tel qu'implémenté (un compte peut cumuler plusieurs espaces) avant P002.
- Valider que P002 (Grand Livre minimal) peut démarrer sur cette base
  d'identité, sans autre ajustement du modèle de capacités.

## Commit final proposé

```text
P001: identité, espaces, capacités et shells Inertia/Vue
```

## Chantier suivant recommandé

**P002 — Grand Livre minimal**, après autorisation explicite du fondateur.
