# P002 — RAPPORT DE CHANTIER

**Branche proposée :** `codex/p002-ledger-core`
**Commit de base :** `21fdbd04f802067544554262c2a4388717c40a1f`
**Statut :** implémentation locale réalisée — validation CI et PostgreSQL en attente de publication autorisée

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
| ESLint | Réussi |
| Prettier | Réussi |
| TypeScript/Vue | Réussi |
| Build Vite | Réussi — 565 modules transformés |
| Analyse YAML du workflow CI | Réussi |
| Recherche de secret dans le périmètre P002 | Aucun secret réel trouvé |

## Contrôles restant obligatoires

L’environnement local de cette session ne fournit ni binaire PHP, ni Composer, ni Docker. Les commandes suivantes doivent donc être exécutées par GitHub Actions après publication de la branche :

- Pint ;
- Larastan niveau 8 ;
- suite Pest complète sous SQLite ;
- suite Pest complète sous PostgreSQL 17 ;
- migrations aller/retour PostgreSQL ;
- triggers d’immutabilité PostgreSQL.

P002 ne peut pas être déclaré `ready_for_review` avant ces résultats.

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

- comportement des verrous concurrents à confirmer sur PostgreSQL 17 réel ;
- compatibilité Pint/Larastan à confirmer dans l’environnement officiel ;
- aucune première écriture financière réelle sans validation explicite du fondateur.

## Commit proposé

```text
feat(ledger): establish the minimal double-entry core
```

## Étape suivante

Publier la branche après autorisation explicite, laisser la CI SQLite/PostgreSQL terminer, corriger tout échec, puis présenter P002 au fondateur avant fusion et déploiement. P003 ne commence qu’après acceptation de P002.
