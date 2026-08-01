# WASPLEX — DOCUMENT MAÎTRE

**Version :** 1.0  
**Date :** 2026-08-01  
**Statut :** canonique après validation du fondateur  
**Dépôt :** `zumradeals/wasplex-core`

## 1. Rôle

Ce document est la porte d'entrée du projet. Il présente Wasplex, ses acteurs, ses flux, ses modules, ses dépendances et renvoie vers les spécifications détaillées.

Il ne remplace pas :

- une décision explicite du fondateur ;
- la note détaillée du module concerné ;
- l'état réel du code ;
- le chantier validé dans la roadmap.

Ordre de priorité : décision du fondateur → note du module → présent document → architecture → roadmap validée → code réel → ancienne documentation.

## 2. Résumé exécutif

Wasplex est un écosystème numérique où un compte universel peut accéder à plusieurs espaces : utilisateur, annonceur, professionnel, institutionnel et administration.

Son premier flux économique transforme une campagne financée en attention qualifiée et en valeur traçable :

```text
Annonceur
→ budget
→ campagne
→ audience protégée
→ revue administrative
→ Matching
→ Feed
→ attention qualifiée
→ Grand Livre
→ Wallet utilisateur
→ reporting et audit
```

`1 WP = 1 FCFA`. Le Grand Livre en double entrée est la source de vérité. Le Wallet est une projection ; aucun solde n'est modifié directement.

## 3. Acteurs et espaces

| Acteur | Espace | Finalité |
|---|---|---|
| Utilisateur | Application mobile-first | Feed, Wallet, Mon Espace, Fonds, Alertes, Santé, Carte, Live |
| Annonceur | Studio Annonceur | marques, médias, campagnes, budgets, reporting |
| Professionnel/partenaire | Portail professionnel | opérations, équipes, prestations, rapports |
| Institution | Espace institutionnel | Alertes, dossiers autorisés, coordination, audit |
| Acteur Santé | Espace Santé séparé | données et accès strictement contrôlés |
| Administration/fondateur | Console de pilotage | configuration, supervision, risques, audit, interventions contrôlées |

Un seul compte peut posséder plusieurs espaces. Chaque action est évaluée dans le contexte : compte + espace + organisation + capacité + périmètre + MFA lorsque requis.

## 4. Navigation utilisateur

La navigation principale doit rester mobile-first et cohérente : Feed, Mon Espace, Wallet, Carte et accès aux services autorisés. Santé et Alertes conservent des frontières de données distinctes. Le shell mobile est conservé sur desktop pour l'utilisateur.

Le Studio Annonceur et la console administrative disposent de véritables expériences desktop et mobile adaptées à leurs usages ; ils ne doivent pas devenir de simples formulaires génériques.

## 5. Économie publicitaire

- une campagne possède contenu, audience, période, budget et règles versionnées ;
- l'annonceur achète une capacité de ciblage, jamais l'identité des personnes ;
- le ciblage utilise seulement des données volontaires et des projections autorisées ;
- Santé, Alertes, Fonds et KYC sont interdits au ciblage commercial ;
- la livraison publicitaire consomme le quota selon la règle publiée ;
- le gain exige un événement qualifié distinct ;
- le gain exact est connu avant l'action ;
- la valeur est réservée avant validation ;
- le partage économique initial est 50 % utilisateur / 50 % Wasplex ;
- la part utilisateur est distribuée selon les classes et configurations publiées ;
- aucun revenu individuel n'est garanti.

## 6. Grand Livre et Wallet

Le Grand Livre doit être équilibré, append-only, idempotent, auditable, explicite sur la devise et corrigé par compensation.

```text
preuve validée
→ transaction Grand Livre
→ commit
→ projection Wallet
→ événement temps réel
→ interface
```

Le Wallet expose disponible, réservations, compartiments et historique. Dépôts, retraits, transferts, Fonds, Carte, Live, commissions et remboursements doivent tous produire des écritures traçables.

Voir [Wallet & Grand Livre](./06-wallet-et-grand-livre-wasplex.md).

## 7. Classes, abonnements et configurations

Les classes initiales sont Gratuit, Premium, Gold et Platine. Noms, poids, quotas, prix, frais, commissions, limites et seuils sont administrables, versionnés, simulables et publiés. Les règles ne doivent pas être dissimulées dans des constantes applicatives.

Voir [Abonnements et classes économiques](./03-abonnements-et-classes-economiques-wasplex.md).

## 8. Matching et protection des données

Le Matching évalue l'éligibilité à partir de projections minimales et de consentements actifs. L'annonceur reçoit estimations et rapports agrégés, jamais une liste nominative. L'utilisateur peut comprendre « Pourquoi cette publicité ? », corriger ses réponses et retirer son consentement.

Voir [Matching et distribution](./04-moteur-matching-et-distribution-publicitaire-wasplex.md) et [Données, permissions et consentements](./17-donnees-permissions-consentements-techniques-wasplex.md).

## 9. Feed et attention

Le Feed est l'expérience centrale utilisateur. Il combine contenu, publicité autorisée, insertions utiles et Alertes prioritaires. Une publicité peut être livrée sans produire de gain ; seul l'événement qualifié et prouvé capture la réservation.

Voir [Feed principal](./08-feed-principal-wasplex.md) et [Super moteur de valeur](./07-super-moteur-unifie-valeur-temps-reel-wasplex.md).

## 10. Administration et autorité du fondateur

Le fondateur dispose d'une console réelle pour configurations, finances, campagnes, risques, incidents, audit et supervision des modules. Cette autorité passe par des capacités explicites et, pour les actions critiques, MFA, justification, confirmation et audit.

Une intervention exceptionnelle ne peut jamais effacer une écriture, falsifier une preuve, modifier directement un solde ou rendre une action invisible.

Voir [Administration centrale](./12-administration-centrale-supervision-fondateur-wasplex.md).

## 11. Modules

| N° | Module | Spécification |
|---:|---|---|
| 00 | Identité visuelle et design system | [Note 00](./00-identite-visuelle-wasplex.md) |
| 01 | Fonds Wasplex | [Note 01](./01-module-fonds-wasplex.md) |
| 02 | Alertes et Santé | [Note 02](./02-module-alertes-sante-wasplex.md) |
| 03 | Abonnements et classes | [Note 03](./03-abonnements-et-classes-economiques-wasplex.md) |
| 04 | Matching publicitaire | [Note 04](./04-moteur-matching-et-distribution-publicitaire-wasplex.md) |
| 05 | Modèle économique publicitaire | [Note 05](./05-modele-economique-publicitaire-wasplex.md) |
| 06 | Wallet et Grand Livre | [Note 06](./06-wallet-et-grand-livre-wasplex.md) |
| 07 | Moteur de valeur | [Note 07](./07-super-moteur-unifie-valeur-temps-reel-wasplex.md) |
| 08 | Feed | [Note 08](./08-feed-principal-wasplex.md) |
| 09 | Compte universel et Mon Espace | [Note 09](./09-compte-universel-et-mon-espace-intelligent-wasplex.md) |
| 10 | Carte Wasplex | [Note 10](./10-carte-wasplex.md) |
| 11 | Live Wasplex | [Note 11](./11-live-wasplex.md) |
| 12 | Administration | [Note 12](./12-administration-centrale-supervision-fondateur-wasplex.md) |
| 13 | Studio Annonceur | [Note 13](./13-studio-annonceur-wasplex.md) |
| 14 | Espaces professionnels/institutionnels | [Note 14](./14-espaces-partenaires-professionnels-institutionnels-wasplex.md) |
| 15 | Notifications et messagerie | [Note 15](./15-notifications-messagerie-communication-wasplex.md) |
| 16 | Modération, sécurité et antifraude | [Note 16](./16-moderation-securite-antifraude-globale-wasplex.md) |
| 17 | Données, permissions et consentements | [Note 17](./17-donnees-permissions-consentements-techniques-wasplex.md) |
| 18 | Reporting, audit et observabilité | [Note 18](./18-reporting-statistiques-audit-observabilite-wasplex.md) |
| 19 | Intégrations externes | [Note 19](./19-integrations-externes-wasplex.md) |
| 20 | Architecture technique | [Note 20](./20-architecture-technique-generale-wasplex.md) |
| 21 | Protocole de roadmap | [Note 21](./21-feuille-de-route-implementation-depot-wasplex.md) |
| 22 | Source du document maître | [Note 22](./22-document-maitre-wasplex.md) |

## 12. Fonds

Fonds gère vœux, programmes, contribution personnelle, contribution collective, prestataires, frais et régularisation. Le compartiment Fonds est séparé ; aucun débit ne rend le Wallet disponible négatif.

## 13. Alertes

Alertes gère dossier source, projections publique/institutionnelle, priorités, routage, restitution et canaux temporaires. Une Alerte ne produit aucun WP, ne consomme aucun quota publicitaire et une priorité vitale n'est jamais vendue.

## 14. Santé

Santé partage certains parcours avec Alertes, mais conserve schémas, permissions, journaux et rétention séparés. Capsule d'urgence et break-glass sont minimaux, temporaires, justifiés, audités et notifiés. Aucune donnée médicale personnelle n'alimente la publicité.

## 15. Carte et partenaires

La Carte ne stocke pas la vérité financière ; elle autorise des opérations reliées au Wallet et au Grand Livre. Partenaires, commissions, avantages, cashback et paiements utilisent contrats, preuves, limites, rapprochement et audit.

## 16. Live

Un Live standard ne rémunère pas automatiquement. Un Live sponsorisé rémunéré exige budget payé, places limitées, gain annoncé, réservation progressive, blocs complets d'attention vérifiée et crédit après Grand Livre. Le cachet créateur reste séparé de l'enveloppe spectateurs.

## 17. Communication, sécurité et modération

Notifications financières seulement après commit. Messagerie et canaux temporaires respectent finalité, durée et accès. Modération, antifraude, sanctions ciblées, holds, réexamen et kill switches sont audités et ne contournent jamais le Grand Livre.

## 18. Reporting, audit et observabilité

Les événements métier sont sources de projections analytiques versionnées. Les annonceurs voient des données agrégées. Le fondateur supervise économie, modules, risques, files et incidents. Logs, métriques, traces, health checks et rapprochements doivent permettre d'expliquer et de reprendre les flux.

## 19. Architecture technique

```text
monorepo
→ apps/platform Laravel
→ monolithe modulaire
→ Inertia + Vue 3 + TypeScript
→ Tailwind + Vite
→ PostgreSQL
→ Redis
→ workers + scheduler
→ outbox/inbox
→ Reverb
→ S3 compatible
→ adaptateurs externes
```

Pas de microservices prématurés, pas de lecture libre entre tables de domaines, pas de logique métier dans les contrôleurs ou SDK fournisseurs.

Voir [Architecture technique générale](./20-architecture-technique-generale-wasplex.md).

## 20. Première roadmap

La construction commence par le noyau P000-P004, puis la verticale publicitaire P005-P013. Les extensions métier suivent seulement après preuve et stabilisation de cette verticale.

Voir [Feuille de route d'implémentation](./IMPLEMENTATION-ROADMAP-WASPLEX.md).

## 21. Glossaire minimal

| Terme | Définition |
|---|---|
| WP | unité Wasplex équivalente à 1 FCFA |
| Grand Livre | source de vérité financière en double entrée |
| Wallet | projection de valeur attachée à un compte/espace |
| Quote | calcul versionné et figé avant une opération |
| Reservation | valeur bloquée avant validation |
| Capture | confirmation définitive d'une réservation |
| Release | libération d'une réservation |
| Compensation | nouvelle transaction corrigeant une opération |
| Matching | décision d'éligibilité fondée sur projections autorisées |
| Attention qualifiée | attention prouvée selon une règle publiée |
| Outbox/Inbox | mécanismes de publication fiable et de déduplication |
| Capacité | permission explicite, limitée et auditée |

## 22. Méthode de travail

```text
document maître
→ note du module
→ dépendances
→ architecture
→ chantier roadmap
→ code réel
→ tests
→ rapport
```

Toute contradiction métier importante est présentée au fondateur avec options et impacts. Aucun texte ne doit devenir une constitution bloquant le développement.
