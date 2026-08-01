# P000 — SOCLE DU DÉPÔT ET STACK

**Branche :** `codex/p000-platform-foundation`  
**Commit de base :** `d899d61f232342eb859bd6599006354aeec85564`  
**Statut :** en cours  

## Objectif

Installer un monorepo Laravel reproductible, testable et observable sans introduire de règle métier.

## Inclus

- `apps/platform` avec Laravel 13 ;
- Inertia 3, Vue 3, TypeScript, Tailwind 4 et Vite 8 ;
- baseline PHP 8.4, Node 24, PostgreSQL 17 et Redis 7.4 ;
- structure modulaire explicite ;
- traces de requête et logs JSON ;
- health checks ;
- en-têtes de sécurité fondamentaux et CORS limité par configuration ;
- configuration PostgreSQL, Redis, S3 compatible et queues après commit ;
- Pest 4, Larastan et Pint déclarés ;
- CI frontend/backend ;
- documentation canonique, audit et roadmap.

## Exclus

- authentification ;
- comptes et espaces ;
- permissions métier ;
- migrations métier ;
- Ledger et Wallet ;
- campagnes, Feed et dashboards métier ;
- fournisseur externe réel.

## Preuves attendues

- `npm ci` ;
- `npm run format:check` ;
- `npm run lint:check` ;
- `npm run types:check` ;
- `npm run build` ;
- `composer install` et `composer.lock` ;
- `composer lint:check` ;
- `composer types:check` ;
- `composer test` ;
- réponse `/up` ;
- réponse structurée `/api/health` avec `X-Trace-Id` ;
- capture de la page technique.

P000 ne peut passer à `ready_for_review` tant que les validations Composer/Pest n'ont pas été réellement exécutées.
