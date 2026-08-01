# Wasplex Core

Wasplex Core is a modular Laravel monolith. The repository keeps the product documentation, the main platform application, and local infrastructure in one place.

## Repository layout

```text
apps/platform/  Laravel 13 + Inertia 3 + Vue 3 + TypeScript
docs/           Canonical product and implementation documents
infra/          Local PostgreSQL and Redis services
```

## Baseline

- PHP 8.4
- Laravel 13
- PostgreSQL 17
- Redis 7.4
- Inertia 3
- Vue 3
- TypeScript
- Tailwind CSS 4
- Vite 8
- Pest 4

## Local setup

```bash
docker compose -f infra/compose.yaml up -d
cd apps/platform
cp .env.example .env
composer install
php artisan key:generate
npm ci
php artisan migrate
composer test
npm run build
```

No real secret belongs in Git. Copy `.env.example` and provide local values through `.env`.

Product and delivery references:

- [`docs/MASTER-WASPLEX.md`](docs/MASTER-WASPLEX.md)
- [`docs/IMPLEMENTATION-ROADMAP-WASPLEX.md`](docs/IMPLEMENTATION-ROADMAP-WASPLEX.md)
- [`docs/ROADMAP-INDEX.md`](docs/ROADMAP-INDEX.md)
- [`docs/audit/`](docs/audit/)

## Quality checks

Run from `apps/platform`:

```bash
composer lint:check
composer types:check
composer test
npm run lint:check
npm run format:check
npm run types:check
npm run build
```

## Architecture boundary

P000 only installs the technical foundation. Business rules, authentication, accounts, Ledger, Wallet, campaigns and permissions begin in later validated work packages.
