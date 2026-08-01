# WASPLEX — CARTE INITIALE DES MODULES

**État global :** spécifiés, non implémentés.

| Domaine | Source principale | Dépendances structurantes | Code |
|---|---|---|---|
| Identity / Accounts | Note 09 | permissions, organisations, espaces | Absent |
| Organizations | Notes 09 et 14 | Identity, capacités | Absent |
| Subscriptions | Note 03 | Identity, configuration économique | Absent |
| SmartProfile | Notes 04, 09 et 17 | consentements, projections | Absent |
| Advertising | Notes 05 et 13 | Wallet annonceur, Ledger, Matching | Absent |
| Matching | Note 04 | SmartProfile, consentements, campagnes | Absent |
| Feed | Note 08 | Matching, Advertising, ValueEngine | Absent |
| Ledger | Note 06 | Identity, audit, idempotence | Absent |
| Wallet | Note 06 | Ledger, projections | Absent |
| ValueEngine | Note 07 | Ledger, Wallet, preuves, réservations | Absent |
| Funds | Note 01 | Wallet, Ledger, partenaires | Absent |
| Alerts | Note 02 | Identity, institutions, notifications | Absent |
| Health | Note 02 | Identity, consentements, audit | Absent |
| Card | Note 10 | Wallet, Ledger, partenaires | Absent |
| Partners | Notes 10 et 14 | Organizations, Wallet | Absent |
| Live | Note 11 | Feed, Wallet, Ledger, ValueEngine | Absent |
| Notifications | Note 15 | outbox, temps réel | Absent |
| Messaging | Note 15 | Identity, modération, rétention | Absent |
| Moderation | Note 16 | Identity, Feed, Live, audit | Absent |
| Risk | Note 16 | événements, Ledger, antifraude | Absent |
| DataAccess | Note 17 | tous les domaines sensibles | Absent |
| Reporting | Note 18 | événements et projections | Absent |
| Audit | Note 18 | tous les domaines sensibles | Absent |
| Integrations | Note 19 | contrats internes, outbox | Absent |
| Administration | Note 12 | capacités, audit, tous domaines | Absent |

## Noyau technique minimal recommandé

Le « noyau » initial n'est pas l'ensemble des modules. Il est la capacité partagée strictement nécessaire pour construire les verticales :

- identifiants et temps ;
- contexte de compte, espace et organisation ;
- capacités et politiques ;
- idempotence ;
- événements de domaine ;
- outbox/inbox ;
- audit ;
- conventions d'erreurs ;
- types monétaires sans solde mutable ;
- configuration versionnée ;
- observabilité minimale.

Le Ledger et le Wallet restent des domaines métier séparés, même s'ils arrivent très tôt dans le chemin critique.
