# P002 — GRAND LIVRE MINIMAL

**Branche proposée :** `codex/p002-ledger-core`
**Commit de base :** `21fdbd04f802067544554262c2a4388717c40a1f`
**Statut :** `deployed`

## Objectif

Établir la source de vérité financière append-only de Wasplex avant toute projection Wallet, réservation ou opération de paiement.

## Inclus

- types de comptes, comptes comptables et journaux ;
- transactions, écritures en double entrée et liens entre transactions ;
- montant entier, unité et devise explicites ;
- clé d’idempotence avec empreinte du contenu économique ;
- sérialisation concurrente de deux demandes identiques ;
- refus atomique des transactions déséquilibrées ;
- immutabilité applicative et protection PostgreSQL des écritures publiées ;
- correction uniquement par transaction compensatoire liée ;
- contrat interne de posting, consultation et compensation ;
- événements Ledger après commit ;
- consultation administrative en lecture seule, protégée par MFA et capacités ;
- commande idempotente d’initialisation du catalogue comptable minimal ;
- audit des consultations et journalisation des divergences.

## Exclus

- Wallet, solde projeté et historique utilisateur ;
- réservations, captures et libérations ;
- dépôts, retraits, transferts et prestataires externes ;
- budget publicitaire et gain utilisateur ;
- interface financière utilisateur ;
- outbox et notifications temps réel, livrées dans les chantiers dépendants.

## Invariants

- aucune transaction déséquilibrée n’est commise ;
- aucune écriture n’utilise de nombre flottant ;
- toutes les écritures d’une transaction partagent unité et devise ;
- un compte ne reçoit que son unité et sa devise déclarées ;
- le Grand Livre ne stocke aucun solde d’autorité ;
- un rejeu idempotent retourne la transaction initiale ;
- une clé réutilisée avec un contenu différent est refusée ;
- transactions, écritures et liens publiés ne sont ni modifiés ni supprimés ;
- une correction inverse les sens dans une nouvelle transaction ;
- les clients HTTP ne disposent d’aucune route de posting.

## Migrations

- `ledger_account_types` ;
- `ledger_journals` ;
- `ledger_accounts` ;
- `ledger_transactions` ;
- `ledger_entries` ;
- `ledger_transaction_links` ;
- `ledger_idempotency_keys`.

## Capacités

- `wallet.ledger.view` ;
- `wallet.correction.propose` ;
- `wallet.correction.approve` ;
- `wallet.audit.view`.

## Événements

- `LedgerTransactionPosted` ;
- `LedgerTransactionReversed` ;
- `LedgerImbalanceDetected`.

## Preuves attendues

- suite P001 toujours verte ;
- tests Ledger d’équilibre, devise, idempotence, rejeu, compensation et rollback ;
- test de deux appels identiques représentant deux workers ;
- test de consultation administrative et d’audit ;
- migration aller/retour PostgreSQL 17 ;
- Pint et Larastan niveau 8 ;
- build frontend inchangé et vert ;
- `git diff --check` sans erreur.

## Rollback VPS

Avant migration, sauvegarder PostgreSQL et noter le commit déployé. En cas d’échec avant toute écriture P002 réelle, revenir au commit P001 puis exécuter `php8.4 artisan migrate:rollback --step=1 --force`. Après une première écriture réelle, restaurer la sauvegarde ; ne jamais supprimer sélectivement des écritures Ledger.
