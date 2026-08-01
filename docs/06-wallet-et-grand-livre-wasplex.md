# WASPLEX — WALLET & GRAND LIVRE

**Fichier cible recommandé :** `docs/05-wallet/00-wallet-et-grand-livre-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique prête au codage  
**Position dans la navigation :** troisième destination principale, centrale et dominante  
**Navigation officielle :** Feed — Fonds — Wallet — Alertes — Mon Espace  
**Référence de valeur :** 1 WP = 1 FCFA  
**Principe directeur :** aucune valeur ne peut apparaître dans l’interface sans une écriture financière traçable, et aucune écriture ne peut être modifiée silencieusement.

---

# 1. OBJET DU DOCUMENT

Ce document définit le nouveau Wallet Wasplex et son grand livre financier.

Le Wallet doit permettre de recevoir, conserver, réserver, utiliser, transférer et retirer différentes formes de valeur liées à :

- la rémunération publicitaire ;
- les abonnements ;
- les dépôts ;
- les retraits ;
- les transferts ;
- le Fonds ;
- les alertes payantes autorisées ;
- les récompenses de restitution ;
- les partenaires ;
- la Carte Wasplex ;
- les remboursements ;
- les corrections ;
- les futures opérations Live et Santé.

Le Wallet ne doit pas être une simple colonne `balance` modifiée directement.

Il repose sur :

> **comptes financiers distincts + écritures équilibrées + réservations + états d’opération + rapprochement + audit**

---

# 2. VISION PRODUIT

Le Wallet est le centre visible de la valeur dans Wasplex.

Il doit répondre instantanément à cinq questions :

1. Combien ai-je réellement ?
2. Quelle somme puis-je utiliser maintenant ?
3. Quelle somme est réservée ou en attente ?
4. D’où vient chaque valeur ?
5. Que s’est-il passé en cas d’échec, de remboursement ou de correction ?

L’expérience cible est :

```text
Événement validé
→ écriture financière
→ solde mis à jour
→ Wallet animé
→ origine visible
→ historique consultable
```

---

# 3. RÔLE DU GRAND LIVRE

Le grand livre est la source de vérité financière.

Le Wallet est une projection utilisateur calculée à partir du grand livre.

```text
Grand livre
→ vérité financière immuable

Wallet
→ présentation des soldes et opérations

Moteur de valeur
→ décide et orchestre les écritures

Modules métier
→ demandent une opération, mais ne modifient jamais un solde directement
```

Feed, Fonds, Alertes, Abonnements, Carte et Partenaires ne doivent jamais écrire directement dans un champ de solde.

---

# 4. PRINCIPES INVIOLABLES

1. Aucun solde modifié directement.
2. Toute opération possède un identifiant unique.
3. Toute écriture appartient à une transaction équilibrée.
4. Une opération validée ne peut pas être supprimée.
5. Une erreur est corrigée par une écriture inverse ou compensatoire.
6. Une même preuve ne peut pas produire deux crédits.
7. Une même demande ne peut pas produire deux débits.
8. Une réservation ne constitue pas encore une consommation définitive.
9. Le disponible ne devient jamais négatif.
10. Les montants sont stockés en unité entière minimale.
11. Les devises et unités ne sont jamais mélangées silencieusement.
12. Chaque solde visible doit pouvoir être expliqué.
13. Les opérations sensibles exigent les capacités et niveaux d’authentification appropriés.
14. Les écritures restent séparées des données publicitaires, médicales et d’alertes.
15. L’administration ne peut pas “éditer le solde” sans opération de correction traçable.

---

# 5. UNITÉS ET DEVISES

## 5.1. WasPoint

Décision fondatrice :

```text
1 WP = 1 FCFA
```

Le WP est l’unité de présentation interne des gains Wasplex.

Le système doit néanmoins distinguer :

- unité comptable ;
- devise de règlement ;
- disponibilité ;
- convertibilité ;
- pays ;
- origine.

## 5.2. Stockage

Montants stockés en entier :

```text
amount_minor = 175
unit = WP
```

ou :

```text
amount_minor = 100000
currency = XOF
```

Pas de nombre flottant.

## 5.3. Multi-devise

Architecture compatible avec plusieurs devises :

- XOF ;
- autres devises futures ;
- taux de conversion versionné ;
- aucune conversion implicite ;
- aucun mélange de soldes.

La première configuration peut se concentrer sur XOF/WP.

---

# 6. SOLDES PRÉSENTÉS À L’UTILISATEUR

Le Wallet doit afficher plusieurs compartiments sans donner l’impression qu’ils sont tous immédiatement retirable.

## 6.1. Solde disponible

Valeur utilisable immédiatement pour les opérations autorisées :

- paiement ;
- transfert ;
- abonnement ;
- visibilité renforcée ;
- contribution Fonds ;
- retrait si éligible.

## 6.2. Solde en attente

Valeur créée mais pas encore définitivement disponible :

- paiement externe en rapprochement ;
- gain soumis à contrôle ;
- remboursement en traitement ;
- opération partenaire non réglée ;
- retrait annulable pendant une courte fenêtre.

## 6.3. Solde réservé

Valeur temporairement bloquée pour :

- publicité commencée ;
- retrait demandé ;
- abonnement en paiement ;
- récompense Alertes ;
- paiement partenaire ;
- contribution Fonds ;
- litige ;
- opération Carte.

## 6.4. Solde Fonds

Compartiment distinct réservé au programme Fonds.

Il peut être alimenté par :

- transfert depuis le solde disponible ;
- dépôt direct ;
- autre moyen autorisé.

Il ne doit pas être confondu avec le solde publicitaire ordinaire.

## 6.5. Contribution à régulariser

Ce n’est pas un solde négatif.

C’est une obligation métier distincte :

```text
Montant dû au Fonds
Statut : à régulariser
```

Elle peut limiter les avantages Fonds, mais ne rend jamais le Wallet général négatif.

## 6.6. Solde retirable

Projection calculée selon :

- disponibilité ;
- KYC ;
- montant minimum ;
- réserves ;
- litiges ;
- origine ;
- règles du pays ;
- plafond ;
- frais.

## 6.7. Solde total informatif

Peut être affiché, à condition de détailler :

```text
Disponible
+ En attente
+ Réservé
+ Fonds
```

Ne jamais afficher le total comme entièrement retirable.

---

# 7. STRUCTURE DES COMPTES DU GRAND LIVRE

Le grand livre utilise une comptabilité à double entrée.

Familles de comptes :

```text
ACTIFS
PASSIFS UTILISATEURS
REVENUS WASPLEX
CHARGES ET DISTRIBUTIONS
COMPTES DE RÉSERVATION
COMPTES DE COMPENSATION
COMPTES DE PARTENAIRES
COMPTES DE FONDS
COMPTES DE REMBOURSEMENT
```

---

# 8. COMPTES UTILISATEURS

Chaque utilisateur peut posséder plusieurs comptes comptables :

```text
user.available.wp
user.pending.wp
user.reserved.wp
user.fonds.wp
user.withdrawal_reserved.wp
user.alert_reward_reserved.wp
user.partner_pending.wp
```

Les codes techniques sont stables.

L’interface peut les regrouper visuellement.

---

# 9. COMPTES WASPLEX

Exemples :

```text
wasplex.cash.clearing
wasplex.advertising.revenue
wasplex.subscription.revenue
wasplex.alert_visibility.revenue
wasplex.partner.revenue
wasplex.withdrawal.fees
wasplex.deposit.fees
wasplex.refund.liability
wasplex.suspense
wasplex.rounding
```

L’usage de chaque compte doit être documenté.

Le compte de suspense doit être surveillé et rapproché, jamais utilisé comme poubelle permanente.

---

# 10. COMPTES PUBLICITAIRES

Exemples :

```text
advertiser.cash.received
advertiser.budget.available
advertiser.budget.reserved
advertiser.budget.consumed
advertiser.refundable
advertising.user_envelope.free
advertising.user_envelope.premium
advertising.user_envelope.gold
advertising.user_envelope.platinum
```

Le grand livre doit expliquer :

- budget reçu ;
- part Wasplex ;
- enveloppe utilisateurs ;
- réservation ;
- consommation ;
- reliquat ;
- remboursement.

---

# 11. COMPTES FONDS

Exemples :

```text
fonds.user.personal_contribution
fonds.user.available
fonds.user.reserved
fonds.collective.collection
fonds.wasplex.fixed_fee
fonds.provider.payable
fonds.reserve
fonds.regularization.receivable
```

Le Fonds reste un sous-domaine comptable séparé.

---

# 12. COMPTES PARTENAIRES ET CARTE

Réserver les familles suivantes :

```text
partner.receivable
partner.settlement.pending
partner.user.benefit.pending
partner.user.benefit.available
partner.wasplex.commission
partner.collective.pool
card.subscription.revenue
card.physical_support.cost
```

La note Carte existante apporte des éléments utiles : carte virtuelle ou physique, opérations vérifiées, commissions externes, avantages et éventuel pool collectif financé par des revenus partenaires réels. L’ancien mécanisme de « micro-actionnaire » ou de redistribution alimentée par la vente de nouvelles cartes ne doit pas être repris automatiquement.

---

# 13. TYPES D’OPÉRATIONS

Catalogue initial :

```text
AD_REWARD
AD_BUDGET_FUNDING
AD_VALUE_RESERVATION
AD_VALUE_RELEASE
SUBSCRIPTION_PAYMENT
SUBSCRIPTION_REFUND
DEPOSIT
DEPOSIT_REVERSAL
WITHDRAWAL
WITHDRAWAL_REVERSAL
USER_TRANSFER
FONDS_FUNDING
FONDS_CONTRIBUTION
FONDS_FIXED_FEE
FONDS_PROVIDER_PAYMENT
FONDS_REGULARIZATION
ALERT_VISIBILITY_PAYMENT
ALERT_REWARD_RESERVATION
ALERT_REWARD_RELEASE
PARTNER_BENEFIT
PARTNER_COMMISSION
CARD_PURCHASE
CARD_RENEWAL
REFUND
ADMIN_CORRECTION
DISPUTE_HOLD
DISPUTE_RELEASE
```

Les codes restent stables même si les libellés changent.

---

# 14. TRANSACTION COMPTABLE

Une transaction comporte :

- identifiant UUID ;
- type ;
- statut ;
- référence métier ;
- module source ;
- idempotency key ;
- date ;
- devise/unité ;
- total ;
- initiateur ;
- bénéficiaire ;
- justification ;
- version de règle ;
- écritures ;
- métadonnées contrôlées ;
- traces techniques.

Une transaction n’est publiée que si elle est équilibrée.

---

# 15. ÉCRITURE COMPTABLE

Chaque écriture comporte :

- transaction ;
- compte ;
- sens débit/crédit ;
- montant entier ;
- unité ;
- propriétaire ;
- date comptable ;
- date de création ;
- description ;
- référence externe éventuelle.

Invariant :

```text
Somme des débits = somme des crédits
```

pour une même unité et une même transaction.

---

# 16. ÉTATS DES OPÉRATIONS

États génériques :

```text
draft
pending
authorized
reserved
processing
posted
failed
cancelled
reversed
refunded
disputed
expired
```

Règles :

- `posted` signifie comptabilisé ;
- `reversed` nécessite une transaction inverse ;
- `failed` ne modifie pas le disponible final ;
- `reserved` réduit le disponible mais ne constitue pas un débit définitif ;
- une transition doit être autorisée par machine d’état.

---

# 17. SUPER MOTEUR PUBLICITAIRE ET WALLET

Lorsqu’une publicité est terminée :

```text
QualifiedAttentionValidated
→ vérification idempotence
→ consommation de la réservation publicitaire
→ écriture part Wasplex
→ écriture part utilisateur
→ crédit user.available.wp
→ commit
→ notification temps réel
→ animation Wallet
```

Aucune animation avant confirmation du commit financier.

---

# 18. EXEMPLE DE CRÉDIT PUBLICITAIRE

Gain promis :

```text
175 WP
```

Écriture conceptuelle :

```text
Débit  advertising.user_envelope.gold     175
Crédit user.available.wp                   175
```

La reconnaissance de la part Wasplex appartient à la transaction économique globale de la campagne.

La même `qualified_event_id` ne peut être utilisée qu’une fois.

---

# 19. RÉSERVATION PUBLICITAIRE

Avant lecture :

```text
Débit  advertising.user_envelope.gold.available
Crédit advertising.user_envelope.gold.reserved
```

Abandon :

```text
Débit  advertising.user_envelope.gold.reserved
Crédit advertising.user_envelope.gold.available
```

Validation :

```text
Débit  advertising.user_envelope.gold.reserved
Crédit user.available.wp
```

La modélisation exacte peut varier, mais le résultat doit rester équilibré, traçable et idempotent.

---

# 20. DÉPÔT

## 20.1. Parcours

```text
Choisir moyen
→ saisir montant
→ créer intention
→ recevoir référence
→ paiement externe
→ webhook ou preuve
→ rapprochement
→ crédit
```

## 20.2. États

```text
created
awaiting_payment
payment_detected
under_review
confirmed
credited
rejected
expired
reversed
```

## 20.3. Règles

- aucun crédit sur simple capture d’écran ;
- référence unique ;
- rapprochement automatique ou manuel ;
- preuve conservée ;
- validation admin encadrée ;
- double crédit impossible ;
- origine visible dans l’historique.

---

# 21. DÉPÔT SOUS SUPERVISION ADMINISTRATIVE

Pour les moyens non automatisés :

- utilisateur soumet la référence ;
- système détecte les doublons ;
- agent habilité examine ;
- séparation entre revue et validation possible ;
- décision justifiée ;
- audit ;
- fondateur administrateur possède les capacités de configuration et de supervision prévues, sans suppression des traces.

Une validation administrative produit une vraie transaction comptable.

---

# 22. RETRAIT

## 22.1. Parcours

```text
Montant
→ moyen de retrait
→ contrôle d’éligibilité
→ authentification renforcée
→ réservation
→ soumission prestataire
→ confirmation
→ comptabilisation
```

## 22.2. États

```text
requested
under_review
reserved
submitted
provider_processing
paid
failed
cancelled
reversed
disputed
```

## 22.3. Règles

- solde suffisant ;
- montant minimum ;
- plafond ;
- frais affichés avant validation ;
- KYC selon le niveau requis ;
- destination vérifiée ;
- réservation immédiate ;
- aucun double paiement ;
- échec prestataire libère ou corrige la réservation ;
- preuve de paiement conservée.

---

# 23. TRANSFERT ENTRE UTILISATEURS

Parcours :

```text
Choisir destinataire
→ vérifier identité publique minimale
→ montant
→ frais éventuels
→ confirmation
→ débit/crédit atomique
```

Règles :

- aucun transfert vers soi-même ;
- idempotency key ;
- limites ;
- contrôles antifraude ;
- destinataire confirmé ;
- notification ;
- libellé ;
- annulation seulement avant comptabilisation ;
- correction par opération inverse après comptabilisation.

---

# 24. PAIEMENT D’ABONNEMENT

Sources possibles :

- solde Wallet ;
- moyen externe ;
- combinaison si autorisée.

Parcours :

```text
Plan
→ devis
→ réservation
→ paiement
→ activation abonnement
→ consommation
```

Si l’activation échoue après paiement :

- opération remboursable ;
- compensation ;
- aucune perte silencieuse.

---

# 25. FONDS

## 25.1. Alimentation

L’utilisateur peut alimenter le solde Fonds depuis :

- son solde disponible ;
- un dépôt direct ;
- un moyen externe autorisé.

## 25.2. Contribution personnelle

```text
user.available.wp
→ fonds.user.personal_contribution
```

## 25.3. Collecte collective

Le moteur débite uniquement :

- participants éligibles ;
- même programme ;
- même pays/devise ;
- mandat actif ;
- plafonds respectés ;
- bénéficiaire exclu de son propre débit collectif.

## 25.4. Frais Wasplex

Frais fixe configurable par compte effectivement débité.

Ce frais peut être supérieur à la petite part de solidarité individuelle.

Il doit être comptabilisé séparément.

## 25.5. Régularisation

Aucun Wallet monétaire négatif.

Créer :

```text
Contribution à régulariser
```

avec conséquences Fonds configurables.

---

# 26. ALERTES

Le Wallet finance uniquement les services autorisés :

- visibilité renforcée ;
- récompense volontaire ;
- remboursement ;
- prolongation.

Il ne finance jamais :

- priorité vitale ;
- statut officiel ;
- traitement policier ;
- prise en charge d’un SOS.

---

# 27. RÉCOMPENSE ALERTES

Parcours :

```text
Auteur dépose la récompense
→ montant réservé
→ restitution vérifiée
→ confirmation
→ libération au bénéficiaire
```

En cas de litige :

- maintien en réserve ;
- revue ;
- résolution ;
- libération ou retour.

---

# 28. PARTENAIRES ET CARTE

Le Wallet doit pouvoir recevoir :

- cashback ;
- remise financée ;
- commission reversée ;
- bénéfice partenaire ;
- distribution collective autorisée ;
- remboursement d’achat ;
- correction d’une opération annulée.

Toute valeur partenaire exige :

- opération réelle ;
- référence ;
- confirmation ;
- règlement ou garantie contractuelle ;
- règle versionnée.

---

# 29. PAIEMENTS SANTÉ FUTURS

Prévoir un contrat futur pour :

- paiement d’une consultation ;
- laboratoire ;
- pharmacie ;
- assurance ;
- prestataire.

Le Wallet ne reçoit aucune donnée médicale détaillée.

La référence financière ne contient que le minimum :

```text
service_reference
provider_reference
amount
status
```

---

# 30. LIVE FUTUR

Réserver les types :

- cadeau Live ;
- pourboire ;
- achat d’accès ;
- remboursement ;
- partage créateur/Wasplex.

Aucune règle économique Live n’est fixée dans cette note.

---

# 31. FRAIS

Chaque frais possède :

- code ;
- libellé ;
- base ;
- montant fixe ou pourcentage ;
- minimum ;
- maximum ;
- pays ;
- devise ;
- période ;
- version ;
- bénéficiaire ;
- taxes externes éventuelles.

Aucun frais codé en dur dans l’interface.

---

# 32. PLAFONDS ET LIMITES

Configurables par :

- opération ;
- jour ;
- semaine ;
- mois ;
- pays ;
- classe d’abonnement ;
- niveau KYC ;
- moyen de paiement ;
- risque ;
- utilisateur ;
- institution.

Le refus doit indiquer une raison compréhensible sans révéler les règles antifraude sensibles.

---

# 33. ARRONDIS

Règles :

- montants entiers ;
- pas de flottants ;
- arrondi versionné ;
- compte de reliquat identifiable ;
- aucune perte silencieuse ;
- aucun gain créé par arrondi.

---

# 34. RAPPROCHEMENT

Le rapprochement compare :

- transactions Wasplex ;
- relevés prestataires ;
- webhooks ;
- références ;
- montants ;
- dates ;
- statuts ;
- frais.

Résultats :

```text
matched
partially_matched
unmatched
duplicate
amount_mismatch
status_mismatch
manual_review
```

---

# 35. LITIGES

Un litige peut concerner :

- dépôt ;
- retrait ;
- transfert ;
- publicité ;
- partenaire ;
- abonnement ;
- Fonds ;
- récompense.

États :

```text
opened
under_review
evidence_requested
resolved_for_user
resolved_against_user
partially_resolved
closed
```

Le litige ne supprime aucune écriture.

Il peut créer :

- réserve ;
- compensation ;
- remboursement ;
- correction ;
- notification.

---

# 36. CORRECTIONS ADMINISTRATIVES

Une correction exige :

- capacité dédiée ;
- motif ;
- référence ;
- montant ;
- compte source ;
- compte destination ;
- approbation renforcée au-dessus d’un seuil ;
- écriture ;
- audit ;
- notification selon le cas.

Interdit :

```text
UPDATE wallets SET balance = ...
```

---

# 37. RÔLES ET CAPACITÉS

## Utilisateur

```text
wallet.view.self
wallet.deposit.create.self
wallet.withdraw.create.self
wallet.transfer.create.self
wallet.history.view.self
wallet.statement.export.self
```

## Opérations financières

```text
wallet.deposit.review
wallet.deposit.approve
wallet.withdraw.review
wallet.withdraw.submit
wallet.reconciliation.manage
wallet.dispute.review
```

## Administration

```text
wallet.configuration.manage
wallet.fees.manage
wallet.limits.manage
wallet.ledger.view
wallet.correction.propose
wallet.correction.approve
wallet.audit.view
```

Le fondateur administrateur doit avoir la main sur les configurations, politiques, frais, limites et supervisions selon les capacités déclarées.

---

# 38. SÉPARATION DES TÂCHES

Selon les seuils configurés :

- celui qui crée une correction ne l’approuve pas ;
- celui qui soumet un retrait ne confirme pas le rapprochement ;
- opérations critiques avec double validation ;
- fondateur peut disposer d’un droit exceptionnel explicite, journalisé et non silencieux.

---

# 39. ÉCRANS WALLET

## 39.1. Accueil

- solde disponible ;
- gain WP ;
- réservé ;
- en attente ;
- Fonds ;
- bouton dépôt ;
- bouton retrait ;
- bouton transfert ;
- historique récent.

## 39.2. Détail des soldes

Chaque compartiment expliqué.

## 39.3. Historique

Filtres :

- gains ;
- dépôts ;
- retraits ;
- transferts ;
- Fonds ;
- abonnements ;
- Alertes ;
- partenaires ;
- remboursements.

## 39.4. Détail d’opération

- montant ;
- origine ;
- statut ;
- date ;
- référence ;
- frais ;
- chronologie ;
- justificatif ;
- assistance.

## 39.5. Dépôt

- moyens ;
- instruction ;
- référence ;
- preuve ;
- statut.

## 39.6. Retrait

- moyen ;
- destination ;
- montant ;
- frais ;
- montant net ;
- confirmation ;
- suivi.

## 39.7. Transfert

- destinataire ;
- montant ;
- note ;
- récapitulatif ;
- confirmation.

---

# 40. EXPÉRIENCE TEMPS RÉEL

Après un gain publicitaire confirmé :

- incrément visuel ;
- icône Wallet ;
- animation brève ;
- solde mis à jour ;
- nouvelle ligne dans l’historique ;
- message : `+175 WP — publicité terminée`.

En cas de latence :

```text
Validation en cours
```

Ne jamais afficher un gain définitif avant confirmation serveur.

---

# 41. RELEVÉS

L’utilisateur peut obtenir :

- relevé mensuel ;
- période personnalisée ;
- export CSV/PDF futur ;
- solde initial ;
- entrées ;
- sorties ;
- frais ;
- solde final.

Les relevés sont produits à partir du grand livre.

---

# 42. ADMINISTRATION WALLET

Dashboard :

- volumes ;
- dépôts ;
- retraits ;
- réservations ;
- soldes utilisateurs ;
- Fonds ;
- campagnes ;
- erreurs ;
- rapprochements ;
- litiges ;
- corrections ;
- suspense ;
- anomalies.

Actions :

- configurer ;
- revoir ;
- approuver ;
- suspendre ;
- rapprocher ;
- rembourser ;
- corriger ;
- exporter ;
- auditer.

---

# 43. MODÈLE DE DONNÉES

Entités recommandées :

```text
ledger_accounts
ledger_account_types
ledger_transactions
ledger_entries
ledger_transaction_links
ledger_idempotency_keys
wallets
wallet_balance_projections
wallet_balance_snapshots
wallet_operations
wallet_reservations
wallet_deposits
wallet_withdrawals
wallet_transfers
wallet_payment_methods
wallet_fees
wallet_limits
wallet_reconciliations
wallet_reconciliation_items
wallet_disputes
wallet_corrections
wallet_statements
wallet_audit_events
```

---

# 44. CHAMPS ESSENTIELS

## ledger_accounts

```text
id
code
owner_type
owner_id
unit
currency
account_type
status
country_code
created_at
```

## ledger_transactions

```text
id
type
status
source_module
business_reference
idempotency_key
rule_version
currency
posted_at
created_by
metadata
```

## ledger_entries

```text
id
transaction_id
account_id
direction
amount_minor
currency
description
posted_at
```

## wallet_reservations

```text
id
wallet_id
purpose
amount_minor
currency
status
expires_at
business_reference
```

---

# 45. API UTILISATEUR

```text
GET    /api/wallet
GET    /api/wallet/balances
GET    /api/wallet/operations
GET    /api/wallet/operations/{id}

POST   /api/wallet/deposits
GET    /api/wallet/deposits/{id}

POST   /api/wallet/withdrawals
GET    /api/wallet/withdrawals/{id}
POST   /api/wallet/withdrawals/{id}/cancel

POST   /api/wallet/transfers
GET    /api/wallet/transfers/{id}

GET    /api/wallet/statements
POST   /api/wallet/statements/export
```

---

# 46. API INTERNE DU GRAND LIVRE

```text
POST   /internal/ledger/transactions
POST   /internal/ledger/reservations
POST   /internal/ledger/reservations/{id}/capture
POST   /internal/ledger/reservations/{id}/release
POST   /internal/ledger/transactions/{id}/reverse
GET    /internal/ledger/accounts/{id}/balance
GET    /internal/ledger/transactions/{id}
```

Ces routes ne sont pas exposées directement aux clients mobiles.

---

# 47. API ADMINISTRATION

```text
GET    /api/admin/wallet/dashboard
GET    /api/admin/wallet/deposits
POST   /api/admin/wallet/deposits/{id}/review
POST   /api/admin/wallet/deposits/{id}/approve
POST   /api/admin/wallet/deposits/{id}/reject

GET    /api/admin/wallet/withdrawals
POST   /api/admin/wallet/withdrawals/{id}/review
POST   /api/admin/wallet/withdrawals/{id}/submit
POST   /api/admin/wallet/withdrawals/{id}/resolve

GET    /api/admin/wallet/reconciliations
POST   /api/admin/wallet/reconciliations/run

GET    /api/admin/wallet/corrections
POST   /api/admin/wallet/corrections
POST   /api/admin/wallet/corrections/{id}/approve

GET    /api/admin/ledger/transactions
GET    /api/admin/ledger/accounts
GET    /api/admin/wallet/configuration
PATCH  /api/admin/wallet/configuration
```

---

# 48. ÉVÉNEMENTS MÉTIER

```text
WalletCreated
WalletCredited
WalletDebited
WalletBalanceChanged
ValueReserved
ValueReservationCaptured
ValueReservationReleased

DepositCreated
DepositDetected
DepositApproved
DepositCredited
DepositRejected
DepositReversed

WithdrawalRequested
WithdrawalReserved
WithdrawalSubmitted
WithdrawalPaid
WithdrawalFailed
WithdrawalReversed

TransferCreated
TransferCompleted
TransferFailed

LedgerTransactionPosted
LedgerTransactionReversed
LedgerImbalanceDetected

ReconciliationMatched
ReconciliationMismatchDetected
DisputeOpened
DisputeResolved
WalletCorrectionPosted
```

---

# 49. OUTBOX ET TEMPS RÉEL

Toute transaction publiée écrit dans la même transaction de base :

- écritures ;
- projection ;
- événement outbox.

Le worker diffuse ensuite :

- WebSocket ou push ;
- notification ;
- analytics autorisées ;
- mise à jour interface.

Si la notification échoue, l’écriture financière reste vraie et sera rediffusée.

---

# 50. ANTIFRAUDE

Signaux possibles :

- comptes multiples ;
- appareils partagés anormalement ;
- vitesse impossible ;
- retraits rapides ;
- références dupliquées ;
- chaîne de transferts ;
- incohérence géographique ;
- abus de remboursement ;
- événements publicitaires répétés ;
- bénéficiaires suspects.

Le score antifraude :

- n’édite pas directement un solde ;
- peut placer une réserve ;
- doit être explicable aux opérateurs autorisés ;
- doit être contestable lorsqu’il produit un effet important.

---

# 51. SÉCURITÉ

- authentification renforcée ;
- MFA pour opérations sensibles ;
- signature des webhooks ;
- chiffrement ;
- secrets séparés ;
- idempotence ;
- rate limiting ;
- journal append-only ;
- appareils ;
- sessions ;
- suspension ;
- limites ;
- contrôle des pièces ;
- surveillance du compte de suspense.

---

# 52. PERFORMANCE

- projection de solde ;
- snapshots périodiques ;
- recalcul possible depuis le grand livre ;
- verrouillage ou contrôle de concurrence ;
- transactions courtes ;
- index ;
- pagination ;
- partitionnement futur ;
- aucune agrégation complète à chaque affichage.

Le solde projeté doit pouvoir être vérifié contre le grand livre.

---

# 53. TESTS DU GRAND LIVRE

- transaction équilibrée ;
- transaction déséquilibrée refusée ;
- double entrée ;
- idempotence ;
- concurrence ;
- inversion ;
- compensation ;
- multi-unité refusée dans une même paire invalide ;
- recalcul de solde ;
- snapshot ;
- append-only ;
- correction sans modification historique.

---

# 54. TESTS WALLET

- création ;
- gain publicitaire ;
- animation après confirmation ;
- dépôt ;
- double dépôt ;
- retrait ;
- retrait échoué ;
- transfert ;
- solde insuffisant ;
- réservation ;
- libération ;
- abonnement ;
- Fonds ;
- visibilité Alertes ;
- récompense ;
- partenaire ;
- frais ;
- limites ;
- expiration d’abonnement sans perte des WP.

---

# 55. TESTS DE SÉCURITÉ

- accès à un autre Wallet refusé ;
- correction sans capacité refusée ;
- retrait sans MFA refusé ;
- webhook falsifié refusé ;
- idempotency key rejouée ;
- montant négatif refusé ;
- dépassement de plafond ;
- compte suspendu ;
- recherche administrative auditée ;
- aucune donnée Santé dans le grand livre.

---

# 56. TESTS DE RAPPROCHEMENT

- référence exacte ;
- montant différent ;
- doublon ;
- statut incompatible ;
- opération absente ;
- rapprochement manuel ;
- inversion prestataire ;
- compte de suspense ;
- export.

---

# 57. CRITÈRES D’ACCEPTATION

Le module est accepté lorsque :

1. Wallet est la destination centrale ;
2. 1 WP = 1 FCFA ;
3. plusieurs compartiments sont visibles ;
4. le disponible n’est jamais négatif ;
5. le Fonds reste séparé ;
6. toute valeur vient du grand livre ;
7. les écritures sont équilibrées ;
8. aucune suppression silencieuse ;
9. les réservations fonctionnent ;
10. publicité et Wallet sont atomiques ;
11. dépôt et retrait sont traçables ;
12. transfert atomique ;
13. corrections par écritures ;
14. rapprochement disponible ;
15. frais versionnés ;
16. limites configurables ;
17. administration supervisable ;
18. temps réel après commit ;
19. Carte et partenaires disposent de comptes réservés ;
20. les tests critiques passent.

---

# 58. ORDRE D’IMPLÉMENTATION

## Phase 1 — Grand livre

- comptes ;
- transactions ;
- écritures ;
- équilibre ;
- idempotence ;
- projections.

## Phase 2 — Wallet utilisateur

- soldes ;
- historique ;
- détail ;
- temps réel.

## Phase 3 — Réservations

- création ;
- capture ;
- libération ;
- expiration.

## Phase 4 — Publicité

- enveloppes ;
- gains ;
- Wallet ;
- animation.

## Phase 5 — Dépôts et retraits

- intentions ;
- supervision ;
- prestataires ;
- rapprochement.

## Phase 6 — Transferts et abonnements

- transferts ;
- paiement de plans ;
- remboursements.

## Phase 7 — Fonds

- compartiment ;
- contributions ;
- frais ;
- régularisation.

## Phase 8 — Alertes et partenaires

- visibilité ;
- récompenses ;
- Carte ;
- cashback ;
- commissions.

## Phase 9 — Administration et audit

- dashboards ;
- corrections ;
- rapprochement ;
- litiges ;
- rapports.

---

# 59. DIRECTIVE DE CODAGE

1. auditer le nouveau dépôt ;
2. ne pas réutiliser aveuglément l’ancien Wallet ;
3. créer le grand livre avant les soldes ;
4. interdire toute modification directe ;
5. brancher les modules via contrats ;
6. conserver les références métier ;
7. versionner les règles ;
8. écrire les tests d’invariants en premier ;
9. produire une démonstration du crédit Feed → Wallet ;
10. produire une démonstration dépôt → validation → crédit ;
11. produire une démonstration retrait → réserve → paiement ;
12. fournir les captures utilisateur et administration.

---

# 60. DÉCISION FINALE

Le Wallet Wasplex ne doit jamais être une valeur arbitraire affichée dans une carte.

Il doit être la projection fiable d’un grand livre capable d’expliquer chaque mouvement :

```text
origine
→ autorisation
→ réservation éventuelle
→ preuve
→ transaction équilibrée
→ nouveau solde
→ affichage temps réel
```

Le principe final est :

> **Pas d’écriture, pas de valeur. Pas de preuve, pas de crédit. Pas de suppression, seulement une correction traçable.**
