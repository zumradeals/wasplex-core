# Modules Wasplex

Convention pour chaque domaine métier (docs/20-architecture-technique-generale-wasplex.md #29-#37) :

```text
app/Modules/<Domaine>/
├── Domain/          entités, objets valeur, règles, événements métier
├── Application/     commandes, queries, handlers, cas d'usage
├── Infrastructure/  implémentations PostgreSQL, Redis, adaptateurs externes
├── Http/            contrôleurs, ressources JSON
├── Database/        migrations propres au module
├── Events/          événements versionnés
├── Jobs/            jobs de file idempotents
├── Policies/         autorisations
└── Tests/           tests du module
```

Domaines prévus (ordre d'introduction fixé par `docs/IMPLEMENTATION-ROADMAP-WASPLEX.md`) :

Identity, Accounts, Organizations, Subscriptions, SmartProfile, Advertising,
Matching, Feed, Wallet, Ledger, ValueEngine, Funds, Alerts, Health, Card,
Partners, Live, Notifications, Messaging, Moderation, Risk, DataAccess,
Reporting, Audit, Integrations, Administration.

Règles :

- un module ne lit jamais directement les tables d'un autre module ;
- les échanges passent par des contrats internes, événements ou projections ;
- aucune dépendance circulaire entre modules.

Aucun module n'est encore implémenté : P000 ne crée que le socle technique.
Le premier module (Identity/Accounts) arrive avec P001.
