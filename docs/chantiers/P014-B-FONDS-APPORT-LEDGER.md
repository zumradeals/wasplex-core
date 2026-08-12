# P014-B — Fonds — Apport personnel & Ledger

## Statut

Chantier d’implémentation du deuxième lot Fonds, construit au-dessus de P014-A et du Grand Livre Wasplex.

## Objectif

Permettre à un membre Fonds de préparer son **Solde Fonds**, puis de constituer progressivement l’**apport personnel** d’un vœu, sans créer une comptabilité parallèle et sans rendre le Wallet négatif.

## Doctrine comptable

- Le Grand Livre reste l’unique source de vérité financière.
- Le Wallet utilisateur reste une projection du compte `user.available.wp`.
- Le Solde Fonds est une projection Ledger dédiée : `fund.balance.wp`.
- Chaque vœu possède un compartiment Ledger nominatif et isolé : `fund.wish.personal.{wish_id}.wp`.
- Un transfert Wallet → Solde Fonds débite le Wallet disponible et crédite le Solde Fonds.
- Un apport personnel débite soit le Wallet disponible, soit le Solde Fonds, puis crédite le compartiment du vœu.
- Aucun solde source ne peut devenir négatif.
- Toutes les écritures sont équilibrées et idempotentes.
- La table `fund_personal_contributions` est une projection métier/audit ; elle ne remplace jamais le Ledger.

## Unité économique

P014-B conserve la doctrine Fonds actuelle : **1 WP = 1 FCFA**. Les écritures internes utilisent l’unité Ledger `WP`; l’interface présente explicitement l’équivalence en FCFA.

## Parcours grand public

### Solde Fonds

L’utilisateur voit simultanément :

- son Wallet disponible ;
- son Solde Fonds ;
- l’équivalence WP / FCFA ;
- un bouton simple « Alimenter mon Solde Fonds ».

Le transfert est volontaire, immédiatement traçable et ne doit jamais donner l’impression d’un prélèvement caché.

### Apport d’un vœu

Pour chaque vœu engagé, l’utilisateur voit :

- apport requis ;
- apport déjà constitué ;
- reste à constituer ;
- pourcentage de progression ;
- historique récent ;
- choix de la source : Solde Fonds ou Wallet disponible.

L’utilisateur peut contribuer progressivement. Un versement dépassant le reste à constituer est refusé : aucune sur-collecte silencieuse.

## Règles d’éligibilité

- Un brouillon ne peut pas recevoir d’apport personnel.
- Un vœu soumis, en demande d’information ou approuvé peut recevoir un apport.
- Pendant une adhésion active, ces apports sont autorisés normalement.
- Pendant le délai de grâce ou après suspension pour expiration d’abonnement, seul un engagement déjà constitué avant le début de la grâce peut continuer à être régularisé.
- Une suspension pour un autre motif ne donne pas automatiquement accès aux opérations Fonds.
- Le mandat Fonds doit rester actif pour les opérations de ce lot.

Cette règle applique la décision fondateur : l’expiration de l’abonnement bloque les **nouveaux engagements**, mais ne détruit pas les engagements déjà constitués.

## Sécurité et cohérence

- verrou transactionnel PostgreSQL par compte pendant les mouvements Fonds ;
- contrôle du solde avant écriture ;
- écriture Ledger débit/crédit équilibrée ;
- clé d’idempotence par opération ;
- référence métier du vœu dans la transaction Ledger ;
- audit Fonds sur alimentation du Solde Fonds et apport au vœu ;
- historique du vœu lié à l’identifiant de transaction Ledger.

## API utilisateur

- `GET /api/funds` : ajoute les soldes Wallet/Fonds et la progression d’apport par vœu.
- `POST /api/funds/balance/fund` : Wallet → Solde Fonds.
- `POST /api/funds/wishes/{wish}/contributions` : source autorisée → compartiment personnel du vœu.

## Non inclus dans P014-B

- collecte automatique de solidarité entre membres ;
- frais Wasplex par collecte ;
- arriérés de solidarité ;
- devis et sélection de prestataire P019 ;
- paiement au partenaire ;
- réserve collective ;
- seconde vague de collecte.

Ces éléments restent dans P014-C/P014-D/P014-E.

## Critères d’acceptation

1. Le Solde Fonds est séparé du Wallet disponible et repose sur le Ledger.
2. Un transfert vers Fonds ne peut pas rendre le Wallet négatif.
3. Un vœu possède un compartiment d’apport personnel distinct.
4. La progression est calculée depuis le Ledger, pas depuis un solde dupliqué.
5. Un même appel idempotent ne crée pas deux contributions.
6. Une tentative de sur-financement est refusée.
7. L’apport complet est marqué et visible.
8. Les engagements antérieurs restent régularisables pendant la grâce conformément à la doctrine validée.
9. Un brouillon ne peut pas devenir un nouvel engagement pendant la grâce.
10. L’UX reste mobile-first, lisible, rassurante et cohérente avec la famille visuelle Wasplex.
