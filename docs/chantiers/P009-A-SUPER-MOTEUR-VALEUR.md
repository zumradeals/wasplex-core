# P009-A — SUPER MOTEUR PUBLICITAIRE DE VALEUR

**Statut :** en cours  
**Branche :** `agent/p009-value-engine-core`  
**Base :** `main@033dc4bfe0d91da3c68bfa281d4379e954e61d5f`  
**Décision fondatrice :** le Feed Wasplex crédite automatiquement le Wallet après validation serveur d’une preuve d’attention qualifiée. Le navigateur ne crée jamais de WP.

## 1. Objet

Ce sous-chantier construit le noyau économique auquel le futur Feed d’accueil se raccordera. Il ne livre pas encore l’interface finale du Feed. Il garantit d’abord que toute publicité rémunérée suit une chaîne sûre, explicable et rejouable :

```text
campagne approuvée et financée
→ Matching P008 éligible
→ règle de valeur active
→ devis immuable
→ sous-réservation logique du budget
→ session d’attention signée
→ preuve serveur
→ décision
→ transaction Grand Livre
→ projection Wallet
→ événement outbox
```

## 2. Invariants

1. Une campagne non approuvée, non financée ou non éligible ne crée aucune tentative rémunérée.
2. Le gain annoncé est calculé avant la lecture et ne change plus pendant la tentative.
3. Le budget complet de campagne reste réservé dans le Wallet annonceur par P006 ; P009 maintient un compteur de sous-réservations logiques pour empêcher le surengagement.
4. Un heartbeat client n’est jamais une écriture financière.
5. Une complétion client seule ne crédite jamais le Wallet.
6. Le règlement final débite le compartiment réservé de l’annonceur et crédite, dans une même transaction équilibrée, le Wallet utilisateur et le compte de produit publicitaire Wasplex.
7. Une clé d’idempotence ne peut produire qu’une tentative et qu’une transaction.
8. L’abandon, l’expiration et le rejet libèrent la sous-réservation sans créer de valeur.
9. Aucun identifiant utilisateur n’est transmis à l’annonceur.
10. Les montants, partages, durées et délais proviennent de règles versionnées ou de configurations publiées.

## 3. Données livrées

- `value_event_definitions` : registre des événements valorisables ;
- `value_rule_versions` : règles économiques versionnées ;
- `value_campaign_budget_counters` : réservation, règlement et libération par financement ;
- `value_attempts` : tentative unique liée au Matching, à la campagne et aux Wallets ;
- `value_attention_sessions` : session d’attention signée ;
- `value_attention_heartbeats` : preuves techniques ordonnées et idempotentes ;
- `value_outbox_events` : résultats à publier de manière fiable après commit.

## 4. Événement initial

Le registre initial contient uniquement :

```text
AD_QUALIFIED_ATTENTION
```

Le Super Moteur est toutefois conçu comme un module transversal. Les futurs événements Fonds, Alertes, partenaires, Carte ou Live devront être ajoutés par des règles explicites ; un événement inconnu ne produit aucune valeur.

## 5. Calcul publicitaire initial

La règle initiale reprend le devis financé de la campagne :

```text
gain brut par événement = budget brut / impressions estimées
part utilisateur = gain brut × taux utilisateur
part Wasplex = reliquat exact
```

Le partage initial est configurable et doit totaliser 10 000 points de base. Aucun montant de secours silencieux n’est autorisé.

## 6. Réservation avant lecture

Lors du démarrage :

- le Matching doit être `eligible` et appartenir au compte ;
- la campagne doit être `approved` ;
- sa version active doit correspondre à celle du Matching ;
- le financement et sa réservation Wallet doivent être actifs ;
- la règle doit correspondre au marché, à la devise et à la classe économique ;
- le compteur de budget doit encore financer le gain annoncé.

Le moteur crée ensuite une tentative, une session et un jeton d’attention. Seule l’empreinte du jeton est conservée.

## 7. Heartbeats et preuve

Chaque heartbeat comporte au minimum :

- une clé d’idempotence ;
- une séquence strictement croissante ;
- un temps client contrôlé ;
- une durée active ;
- l’état visible, premier plan et lecture ;
- un indicateur de fin du média.

Le serveur refuse notamment les séquences rejouées avec un autre contenu, les durées impossibles, les horodatages hors fenêtre, les sessions d’un autre compte et les jetons invalides.

Seule la durée visible, au premier plan et réellement en lecture est agrégée. La tentative passe à `proof_pending` lorsque le seuil configuré est atteint.

## 8. Règlement automatique

Après preuve valide, le règlement :

1. verrouille la tentative, la session, le financement, le compteur et les projections ;
2. vérifie que la réservation de campagne demeure active ;
3. poste une transaction `AD_QUALIFIED_ATTENTION_SETTLEMENT` ;
4. débite le compartiment réservé du Wallet annonceur ;
5. crédite le disponible du Wallet utilisateur ;
6. crédite le compte `wasplex.revenue.advertising.wp` ;
7. reconstruit les deux projections Wallet ;
8. inscrit les opérations Wallet ;
9. marque la tentative `completed` ;
10. produit les événements outbox destinés au temps réel et au reporting.

Un rejeu retourne la même tentative terminée et ne reposte aucune transaction.

## 9. Frontières de P009-A

Inclus :

- registre et règle initiale ;
- tentative et idempotence ;
- sous-réservation logique ;
- session d’attention et heartbeats ;
- validation minimale de preuve ;
- règlement Ledger et crédit Wallet ;
- outbox transactionnelle ;
- expiration et libération ;
- tests SQLite/PostgreSQL et rollback.

Non inclus dans ce premier sous-chantier :

- page d’accueil Feed et design final ;
- lecteur vidéo frontend ;
- WebSocket/Reverb et animation Wallet ;
- antifraude avancée multi-appareils ;
- consommation des quotas P004 ;
- reporting complet annonceur/fondateur ;
- retraits utilisateur.

Ces éléments se brancheront sur le noyau sans déplacer la source de vérité financière.

## 10. Sous-phases suivantes

- **P009-B — Livraison et quotas :** session Feed, sélection, `AdDelivered`, fréquence, fatigue et quota.
- **P009-C — Feed d’accueil :** shell desktop trois colonnes, mobile immersif, vidéo/image, CTA, explication et réseau faible.
- **P009-D — Attention renforcée :** règles par format, antifraude, holds et revue.
- **P009-E — Temps réel :** publication outbox, notification, animation Wallet et reprise.
- **P009-F — Supervision :** santé du moteur, incidents et rapprochement.

## 11. Critères d’acceptation du noyau

- deux démarrages avec la même clé retournent la même tentative ;
- le démarrage ne crée aucune transaction financière ;
- une preuve insuffisante ne crédite rien ;
- une preuve valide puis un règlement crédite exactement une fois ;
- le débit annonceur égale la somme utilisateur + Wasplex ;
- l’abandon libère le budget logique et crédite zéro ;
- un rejeu après règlement ne change aucun solde ;
- les migrations se retournent proprement sur PostgreSQL 17 ;
- Pint, Larastan, Pest SQLite/PostgreSQL, Prettier, ESLint, TypeScript et Vite restent verts.
