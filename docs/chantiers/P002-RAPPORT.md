# P002 — RAPPORT DE CHANTIER

**Branche proposée :** `codex/p002-ledger-core`
**Commit de base :** `21fdbd04f802067544554262c2a4388717c40a1f`
**Statut :** validé par la CI GitHub — fusion autorisée — déploiement VPS en attente

## Objectif

Créer la source de vérité financière minimale de Wasplex, en double entrée, avant toute projection Wallet ou opération financière réelle.

## Réalisé

- module Laravel `Ledger` structuré en domaine, application, infrastructure et HTTP ;
- types de comptes, journaux et comptes sans colonne de solde ;
- transactions, écritures, liens et clés d’idempotence en ULID ;
- montants strictement entiers, unité et devise explicites ;
- service interne de posting avec verrou transactionnel et empreinte canonique ;
- refus des écritures non positives, comptes absents/inactifs, devises incohérentes et déséquilibres ;
- transaction brouillon invisible, finalisée seulement après validation des écritures ;
- protections applicatives et triggers PostgreSQL contre modification, suppression ou écriture tardive ;
- compensation complète dans une nouvelle transaction avec inversion débit/crédit et lien `reversal` ;
- rejeu idempotent sans duplication et conflit explicite si le contenu économique change ;
- événements `LedgerTransactionPosted` et `LedgerTransactionReversed` après commit ;
- événement et log critique `LedgerImbalanceDetected` avant rejet ;
- contrat interne de posting/compensation et contrat de consultation ;
- consultation administrative JSON strictement en lecture seule ;
- MFA et capacités explicites pour la consultation et l’audit ;
- audit des consultations de comptes et transactions ;
- commande idempotente `ledger:bootstrap-core` créant uniquement le catalogue minimal ;
- double passage CI prévu : SQLite puis PostgreSQL 17 ;
- test PostgreSQL des triggers d’immutabilité ;
- fiche chantier et guide de déploiement VPS.

## Migrations

Une migration réversible crée :

- `ledger_account_types` ;
- `ledger_journals` ;
- `ledger_accounts` ;
- `ledger_transactions` ;
- `ledger_entries` ;
- `ledger_transaction_links` ;
- `ledger_idempotency_keys`.

La migration PostgreSQL ajoute les contraintes de statut, direction, montant positif, lien distinct, équilibre à la finalisation et immutabilité des enregistrements publiés.

## Contrats internes

| Contrat | Responsabilité |
|---|---|
| `LedgerContract` | poster une transaction et créer une compensation |
| `LedgerQueryContract` | consulter comptes et transactions sans mutation |
| `LedgerCatalog` | initialiser types, journaux et comptes de manière idempotente |

## API administrative

| Méthode | Route | Capacité |
|---|---|---|
| `GET` | `/api/admin/ledger/transactions` | `wallet.ledger.view` |
| `GET` | `/api/admin/ledger/transactions/{id}` | `wallet.audit.view` |
| `GET` | `/api/admin/ledger/accounts` | `wallet.ledger.view` |

Aucune route HTTP ne permet de poster, modifier, supprimer ou compenser une transaction.

## Capacités

- `wallet.ledger.view` ;
- `wallet.correction.propose` ;
- `wallet.correction.approve` ;
- `wallet.audit.view`.

La commande fondatrice existante est idempotente et accorde désormais ces capacités dans l’espace d’administration nominatif.

## Tests ajoutés

- transaction équilibrée et double entrée ;
- montant total entier et devise explicite ;
- rejeu par deux instances de worker ;
- conflit de clé d’idempotence ;
- rollback atomique après compte introuvable ;
- déséquilibre refusé ;
- incohérence de devise refusée ;
- immutabilité applicative ;
- compensation et inversion des sens ;
- seconde compensation refusée ;
- consultation administrative, MFA, capacités et audit ;
- triggers PostgreSQL contre mutation directe et écriture tardive.

## Contrôles exécutés localement

| Contrôle | Résultat |
|---|---|
| Analyse syntaxique PHP 8.4 WebAssembly | Réussi — 117 fichiers parsés |
| Test autonome des DTO et empreintes Ledger | Réussi |
| Pint | Réussi — 116 fichiers conformes |
| Larastan niveau 8 | Réussi — aucune erreur |
| Pest SQLite | Réussi — 24 tests, 165 assertions, 2 tests PostgreSQL ignorés |
| Pest PostgreSQL 17 | Réussi — 26 tests, 172 assertions |
| ESLint | Réussi |
| Prettier | Réussi |
| TypeScript/Vue | Réussi |
| Build Vite | Réussi — 565 modules transformés |
| Analyse YAML du workflow CI | Réussi |
| Recherche de secret dans le périmètre P002 | Aucun secret réel trouvé |

La CI prépare explicitement l’environnement de test et traite désormais tout avertissement PHPUnit comme un échec. Aucun test fonctionnel ne peut donc produire un faux résultat vert faute de fichier `.env`.

## Validation officielle

Le workflow GitHub Actions `ci` no 8, exécuté sur le commit `a019c06`, est entièrement vert. Il valide notamment la migration aller/retour et les triggers PostgreSQL 17 au travers de la suite Pest officielle.

## Interface et captures

Aucune interface utilisateur n’est incluse dans P002. Le design actuel de Wasplex n’a pas été modifié. Les routes administratives livrent uniquement des données de lecture pour une future interface de supervision.

## Rollback

Le plan détaillé se trouve dans `docs/chantiers/P002-DEPLOIEMENT-VPS.md`. Avant toute écriture Ledger réelle, la migration peut être annulée après vérification que `ledger_transactions` est vide. Après une écriture réelle, seule une restauration contrôlée de sauvegarde est acceptable.

## Limites

- aucun Wallet ni solde projeté ;
- aucune réservation ;
- aucun dépôt, retrait ou transfert ;
- aucune opération publicitaire ;
- aucune outbox P002 : la diffusion fiable aux projections et notifications appartient aux chantiers dépendants ;
- aucun compte économique concret créé par la commande de bootstrap, seulement types et journaux.

## Risques surveillés

- charge concurrente à surveiller lors des futurs flux Wallet P003 ;
- aucune première écriture financière réelle sans validation explicite du fondateur.

## Commits de chantier

```text
feat(ledger): establish the minimal double-entry core
style(ledger): align PHP formatting
fix(ledger): satisfy static analysis contracts
ci: make feature test warnings blocking
test(ledger): verify PostgreSQL migration rollback
```

## Étape suivante

Fusionner la PR autorisée sur `main`, puis exécuter avec le fondateur le guide `P002-DEPLOIEMENT-VPS.md`. P003 ne commence qu’après validation du déploiement P002.
