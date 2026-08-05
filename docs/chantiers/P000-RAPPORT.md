# P000 — RAPPORT DE CHANTIER

**Chantier :** P000 — Socle du dépôt et stack
**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base (avant réinitialisation) :** `aa6cb1a60c2e43308066fb267cad76b38d689e1c`
**Statut :** `ready_for_review` — en attente d'autorisation du fondateur avant P001

## Contexte : réinitialisation du dépôt

Mission explicite du fondateur : archiver/supprimer le code applicatif existant
(chantiers P000-P009 précédemment "Déployé"/"En cours") et reconstruire
`apps/platform` en repartant strictement du corpus `docs/`, traité comme
si rien n'avait jamais été codé.

- L'état complet précédent reste intégralement consultable dans l'historique
  Git de cette branche, au commit `aa6cb1a60c2e43308066fb267cad76b38d689e1c`
  (poussé tel quel avant toute suppression, donc non perdu).
- `apps/platform`, `infra/compose.yaml` et `.github/workflows/ci.yml` ont été
  retirés du working tree dans le commit suivant, puis reconstruits de zéro.
- `docs/ROADMAP-INDEX.md` a été mis à jour pour ne plus afficher les anciens
  statuts "Déployé" désormais faux ; les rapports historiques dans
  `docs/chantiers/` restent conservés comme trace d'audit uniquement.
- Conformément à `docs/IMPLEMENTATION-ROADMAP-WASPLEX.md` §13, **seul P000
  est exécuté dans ce chantier** ; P001 attend une autorisation explicite.

## Objectif

Rendre le monorepo reproductible, exécutable et testable sans aucune règle
métier, conformément à `docs/chantiers/P000-CHANTIER.md`.

## Inclus (réalisé)

- `apps/platform` : Laravel 13.24, PHP 8.4.19 ;
- Inertia 3 (serveur `inertiajs/inertia-laravel` ^3.0, client `@inertiajs/vue3`
  ^3.6), Vue 3, TypeScript 5.9, Tailwind CSS 4, Vite 8 ;
- PostgreSQL 17 (image Docker) / PostgreSQL 16 vérifié en local dans ce
  bac à sable ; Redis 7.4 (`infra/compose.yaml`, service MinIO ajouté pour le
  stockage S3 compatible) ;
- structure modulaire explicite déclarée (`app/Modules/README.md` : couches
  Domain/Application/Infrastructure/Http/Database/Events/Jobs/Policies/Tests,
  liste des 26 domaines métier de la roadmap) — aucun module n'est encore
  implémenté, comme prescrit ;
- noyau partagé minimal : `App\Shared\Money\Money` (entier + devise, refuse
  les `float`), `App\Shared\Http\AppException` / `ApiErrorResponse` (forme
  d'erreur JSON standard `{code, message, details, trace_id}`) ;
- `App\Http\Middleware\AssignTraceId` : `trace_id` généré ou repris de
  `X-Trace-Id`, propagé dans les logs (`Log::withContext`) et la réponse ;
- `App\Http\Middleware\SecurityHeaders` : `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` ;
- `config/cors.php` : origines pilotées par `CORS_ALLOWED_ORIGINS`, pas de
  `*` par défaut ;
- logs structurés JSON (`config/logging.php`, canal `structured`,
  `storage/logs/wasplex.json.log`) ;
- health checks : `GET /up` (Laravel natif) et `GET /api/health` (JSON avec
  vérification réelle PostgreSQL + Redis, `trace_id`, `timestamp`) ;
- page technique de vérification Inertia/Vue (`resources/js/Pages/Technical.vue`)
  affichant environnement, versions PHP/Laravel, `trace_id`, connectivité ;
- tokens visuels Wasplex de base dans `resources/css/app.css` (or, noir,
  surfaces, succès/avertissement/danger/info, rayons, ombre de carte) ;
- Pint (formatage), Pest 4 + plugin arch (tests), ESLint 9 + Prettier +
  `prettier-plugin-tailwindcss` (frontend) ;
- CI GitHub Actions (`.github/workflows/ci.yml`) : services PostgreSQL 17 +
  Redis 7.4, `composer install`, `composer lint:check`, `composer test`,
  `npm ci`, `npm run format:check`, `npm run lint:check`,
  `npm run types:check`, `npm run build` ;
- `.env.example` sans secret, conventions UUID/ULID/UTC déjà en place via
  Laravel, `README.md` racine mis à jour.

## Exclus (respecté)

Authentification, comptes/espaces, permissions métier, migrations métier,
Ledger, Wallet, campagnes, dashboards métier, fournisseur externe réel.
Le modèle `User` et les tables `users`/`password_reset_tokens`/`sessions` du
squelette Laravel par défaut ont été **retirés** (avec `config/auth.php`)
car ils anticipaient le domaine "comptes" réservé à P001.

## Migrations / API / événements

- **Migrations :** uniquement les migrations techniques du framework
  (`cache`, `jobs`) — aucune migration métier.
- **API :** `GET /up`, `GET /api/health`. Aucune route métier.
- **Événements :** aucun événement métier (hors périmètre P000).

## Limite de réseau rencontrée (bac à sable) et impact

Ce chantier a été exécuté dans un environnement dont la politique réseau
restreint les téléchargements GitHub aux dépôts explicitement autorisés pour
la session (`zumradeals/wasplex-core`). Deux paquets **dist-only** (sans
export Git, donc insensibles à `--prefer-source`) ont été bloqués (403) :

- **`larastan/larastan` → `phpstan/phpstan` ≥ 2.1.32.** Toutes les versions
  compatibles avec Laravel 13 exigent une version de `phpstan/phpstan`
  distribuée uniquement en zip via `api.github.com` (bloqué). **Non installé.**
  `phpstan.neon` est fourni, `composer types:check` est déclaré, mais cette
  preuve **n'a pas pu être exécutée ici**. À ajouter depuis un poste ou une CI
  disposant d'un accès réseau complet à `github.com`.
- **`league/flysystem-aws-s3-v3` → `aws/aws-sdk-php`.** Dépôt Git trop
  volumineux (clone écourté après 300 s), et le repli dist est également
  bloqué. **Non installé.** Le disque `s3` reste configuré dans
  `config/filesystems.php` avec les variables MinIO (`AWS_ENDPOINT`,
  `AWS_USE_PATH_STYLE_ENDPOINT`) ; `FILESYSTEM_DISK=local` par défaut.

Un troisième blocage (police distante `fonts.bunny.net` via
`laravel-vite-plugin`) a été contourné proprement en supprimant la
dépendance de police distante au build (pile de polices système), un choix
plus robuste pour un socle indépendant du réseau, pas un contournement de
politique.

Ces trois éléments sont documentés dans `README.md` (section "Quality
checks") et ne bloquent aucune preuve obligatoire de P000 (Composer/Pest).

## Bug tiers rencontré et contourné

`pestphp/pest-plugin-arch` (v4.0.2) plante (`ObjectDescriptionBase::$path`
non initialisé) dès qu'une expectation `expect('App')` doit signaler une
violation touchant une classe vendor. Or `vendor/laravel/pint` utilise en
interne l'espace de noms `App\*` pour son propre code (Kernel, Providers,
Fixers…), ce qui collisionne avec le nôtre. `tests/Unit/ArchitectureTest.php`
cible donc explicitement `App\Http` et `App\Shared` plutôt que `App` en
entier — ces deux sous-espaces ne collisionnent pas avec Pint.

## Tests exécutés

| Contrôle | Résultat |
|---|---|
| `composer install` | Réussi (Pest 4, sans Larastan — voir limite réseau) |
| `composer lint:check` (Pint) | Réussi |
| `composer test` (Pest 4) | Réussi — 14 tests, 44 assertions |
| `composer types:check` (Larastan) | **Non exécuté** — paquet non installable ici |
| `npm ci` / `npm install` | Réussi |
| `npm run format:check` (Prettier) | Réussi |
| `npm run lint:check` (ESLint) | Réussi |
| `npm run types:check` (vue-tsc) | Réussi |
| `npm run build` (Vite) | Réussi |
| `GET /up` | 200, en-têtes de sécurité + `X-Trace-Id` présents |
| `GET /api/health` | 200, `{"status":"ok","checks":{"database":{"ok":true},"redis":{"ok":true}},...}` |
| `GET /` (page technique Inertia) | 200, rendu Vue vérifié par capture d'écran |
| PostgreSQL runtime | PostgreSQL 16 (local, sandbox) — migrations et `select 1` OK |
| Redis runtime | Redis 7.4 (paquet Debian) — `PING` OK |

Détail des 14 tests Pest : isolation Feed/API health (5), forme d'erreur API
standard (1), objets valeur `Money` — arithmétique, devises, refus de
`float`, montant négatif (5), architecture — strict types / pas de `dd()` /
pas de dépendance à `Tests` sur `App\Http` et `App\Shared` (3).

## Bug de version détecté et corrigé en cours de chantier

`inertiajs/inertia-laravel` s'est initialement résolu en v2.0.25 (dernière
version majeure disponible sur Packagist : v3.3.1), incompatible avec le
client `@inertiajs/vue3` ^3.6 installé côté npm (convention d'hydratation
initiale différente : `<div data-page>` vs `<script type="application/json"
data-page="app">`). Remonté à `^3.0` (résolu en v3.3.1) et le cache de vues
Blade compilé a été vidé (`php artisan view:clear`) — un `composer install`
frais sur un nouveau clone ne rencontre pas ce problème.

## Captures

Capture de la page technique (`/`) jointe à ce chantier : environnement
`local`, PHP 8.4.19, Laravel 13.24.0, `trace_id` affiché, PostgreSQL OK,
Redis OK.

## Fichiers modifiés/créés (résumé)

- Racine : `README.md`, `.github/workflows/ci.yml`, `infra/compose.yaml`,
  `docs/ROADMAP-INDEX.md`.
- `apps/platform/` : squelette Laravel complet (voir `git diff --stat` du
  commit de ce chantier) — configuration, `app/Http`, `app/Shared`,
  `app/Modules/README.md`, `resources/`, `routes/`, `tests/`, outillage
  qualité (Pint, ESLint, Prettier, Pest, phpstan.neon).

## Limites restantes

1. Larastan/PHPStan non installé ni exécuté dans cet environnement (blocage
   réseau documenté ci-dessus) — à faire depuis un poste avec accès complet.
2. Pilote de stockage S3 (`league/flysystem-aws-s3-v3`) non installé pour la
   même raison — disque `s3` configuré mais inutilisé (`FILESYSTEM_DISK=local`).
3. MinIO ajouté à `infra/compose.yaml` mais non démarré/vérifié dans ce
   bac à sable (Docker non exercé ici ; PostgreSQL/Redis natifs utilisés à la
   place pour les preuves).
4. PostgreSQL 17 non disponible nativement dans ce bac à sable (16 utilisé
   pour vérifier les migrations/tests) ; l'image Docker `postgres:17-alpine`
   reste la cible documentée et utilisée en CI.
5. Aucune règle métier, authentification ni migration de domaine — conforme
   au périmètre exclu de P000.

## Risques

- Le blocage réseau sur les paquets dist-only (PHPStan récent, AWS SDK) est
  spécifique à cet environnement d'exécution restreint ; il ne doit pas être
  interprété comme une contre-indication à ces paquets en production.
- Le bug `pestphp/pest-plugin-arch` + collision `laravel/pint` pourrait
  ressurgir si un futur chantier tente `expect('App')` sans restriction de
  sous-espace — le contourner de la même façon (`App\Http`, `App\Shared`,
  ou tout futur `App\Modules\<Domaine>`).

## Décisions ouvertes pour le fondateur

- Confirmer PostgreSQL 17 / Redis 7.4 comme cibles définitives (versions déjà
  verrouillées dans `README.md` et `infra/compose.yaml`, non modifiées ici).
- Valider que le périmètre de reconstruction reste bien limité à P000 pour
  cette itération, avant d'autoriser P001 (Compte universel, espaces,
  capacités).

## Commit final proposé

Un commit unique regroupant le socle P000 complet, en plus du commit de
réinitialisation déjà réalisé séparément. Message proposé :

```text
P000: reconstruit le socle Laravel/Inertia/Vue/Tailwind depuis docs/
```

## Chantier suivant recommandé

**P001 — Compte universel, espaces, capacités et shells**, strictement après
autorisation explicite du fondateur, conformément à
`docs/IMPLEMENTATION-ROADMAP-WASPLEX.md` §13 ("attendre l'autorisation de
fusion et de P001").
