# WASPLEX — CARTE WASPLEX

**Fichier cible recommandé :** `docs/09-carte/00-carte-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module transversal, non présent comme onglet permanent dans la navigation principale  
**Dépendances :** Compte universel, Mon Espace, Wallet & Grand Livre, Super moteur de valeur, Abonnements, Alertes, Santé, Partenaires  
**Principe central :** la Carte Wasplex est une clé sécurisée d’accès à des services, une identité numérique contrôlée et un support d’opérations vérifiables ; elle ne constitue ni une action, ni un investissement, ni une cryptomonnaie, ni une promesse de rendement  
**Formats :** carte virtuelle obligatoire, support physique facultatif selon l’offre

---

# 1. OBJET DU DOCUMENT

Ce document définit la Carte Wasplex comme composant transversal de l’écosystème.

La Carte Wasplex doit pouvoir servir à :

- identifier un utilisateur dans un contexte autorisé ;
- présenter un QR Wasplex ;
- ouvrir un profil public minimal ;
- recevoir ou initier un paiement ;
- accéder au Wallet ;
- vérifier un abonnement ;
- confirmer une opération partenaire ;
- obtenir un avantage, une réduction ou un cashback ;
- participer à une restitution Alertes ;
- demander temporairement l’accès à une capsule médicale d’urgence ;
- ouvrir une expérience dans Mon Espace ;
- suspendre un support perdu ;
- fonctionner en version numérique et éventuellement physique ;
- accueillir plus tard des fonctions Live, événementielles ou institutionnelles.

La Carte ne doit pas dupliquer les données des autres modules.

Elle agit comme :

> **identifiant + clé de service + support de vérification + point d’entrée sécurisé**

---

# 2. VISION PRODUIT

L’expérience cible est :

```text
J’ouvre ma Carte Wasplex
→ je présente mon QR
→ l’autre partie scanne
→ Wasplex vérifie l’identité, le contexte et l’autorisation
→ l’opération adaptée s’ouvre
→ aucune donnée inutile n’est révélée
→ l’action est confirmée et auditée
```

Exemples :

```text
Paiement
→ montant
→ confirmation
→ Wallet
```

```text
Restitution Alertes
→ dossier
→ code ou QR
→ double confirmation
→ clôture
```

```text
Urgence Santé
→ professionnel vérifié
→ demande de capsule
→ accès temporaire
→ journal
```

---

# 3. POSITION DANS L’ÉCOSYSTÈME

La Carte Wasplex n’est pas un sixième onglet principal.

Elle est accessible depuis :

- Mon Espace ;
- Wallet ;
- Santé ;
- Alertes ;
- Partenaires ;
- écran rapide QR ;
- raccourci mobile ;
- support physique ;
- notification ou deep link.

La navigation principale reste :

```text
Feed — Fonds — Wallet — Alertes — Mon Espace
```

---

# 4. DISTINCTION ENTRE CARTE ET ABONNEMENT

La Carte n’est pas automatiquement un abonnement.

Trois modèles peuvent coexister :

## 4.1. Carte de base

- virtuelle ;
- liée au compte ;
- QR ;
- identification minimale ;
- accès aux fonctions essentielles.

## 4.2. Carte incluse dans un plan

Un abonnement peut inclure :

- carte virtuelle avancée ;
- support physique ;
- avantages ;
- partenaires ;
- plafonds ;
- personnalisation.

## 4.3. Offre Carte indépendante

Une offre spécifique peut être achetée ou renouvelée.

Elle doit définir :

- prix ;
- durée ;
- avantages ;
- services ;
- support ;
- conditions ;
- partenaires ;
- plafonds.

Aucun développeur ne doit imposer un seul modèle.

---

# 5. TYPES DE CARTES

L’administration peut créer plusieurs offres.

Exemples possibles :

```text
Wasplex Base
Wasplex Premium
Wasplex Gold
Wasplex Platine
Wasplex Partenaire
Wasplex Institution
```

Les noms sont configurables.

Chaque offre possède :

- code technique stable ;
- nom ;
- description ;
- prix ;
- devise ;
- durée ;
- classe d’abonnement associée ou non ;
- services inclus ;
- support virtuel ;
- support physique ;
- coût de fabrication ;
- coût d’expédition ;
- partenaires ;
- plafonds ;
- dates ;
- état ;
- visuel ;
- ordre d’affichage.

---

# 6. CARTE VIRTUELLE

La carte virtuelle est la représentation principale.

Elle affiche selon les permissions :

- nom d’affichage ;
- photo facultative ;
- identifiant public court ;
- QR dynamique ;
- statut ;
- niveau ou offre ;
- date d’expiration ;
- badge vérifié ;
- accès rapide aux actions ;
- bouton suspendre ;
- bouton afficher les avantages.

Elle ne doit jamais afficher automatiquement :

- téléphone ;
- email ;
- adresse ;
- solde ;
- KYC ;
- dossier Santé ;
- Alertes privées ;
- numéro de pièce ;
- données publicitaires.

---

# 7. SUPPORT PHYSIQUE

Le support physique est facultatif.

Il peut comprendre :

- impression ;
- QR ;
- identifiant court ;
- NFC futur ;
- visuel ;
- nom ;
- date d’expiration ;
- code de sécurité non sensible.

Règles :

- aucun secret permanent en clair ;
- aucune donnée médicale stockée directement ;
- aucun solde stocké localement ;
- suspension possible ;
- remplacement ;
- cycle de fabrication ;
- suivi d’expédition ;
- coût distinct ;
- activation après réception.

---

# 8. IDENTIFIANT PUBLIC

Chaque carte possède un identifiant public non sensible.

Exemple :

```text
WPLX-CI-8F42-K9
```

Il ne doit pas exposer :

- identifiant interne de base ;
- numéro de téléphone ;
- année de naissance ;
- territoire précis ;
- statut financier.

L’identifiant peut être régénéré selon politique.

---

# 9. QR WASPLEX

Le QR peut ouvrir :

- profil public minimal ;
- demande de paiement ;
- réception d’argent ;
- vérification d’une carte ;
- opération partenaire ;
- restitution Alertes ;
- accès Santé d’urgence ;
- invitation ;
- lien service.

Le QR doit être contextualisé.

Ne pas utiliser un QR statique unique donnant accès à toutes les fonctions.

---

# 10. TYPES DE QR

## 10.1. QR public d’identité

Affiche uniquement le profil minimal autorisé.

## 10.2. QR de paiement

Contient une demande signée :

- bénéficiaire ;
- montant facultatif ;
- devise ;
- expiration ;
- référence.

## 10.3. QR partenaire

Lie :

- partenaire ;
- offre ;
- point de vente ;
- opération ;
- carte ;
- expiration.

## 10.4. QR Alertes

Lie une restitution ou une action précise.

## 10.5. QR Santé

Ne contient pas le dossier.

Il déclenche une demande d’accès temporaire à la capsule.

## 10.6. QR à usage unique

Pour :

- opération sensible ;
- confirmation ;
- récupération ;
- restitution ;
- paiement élevé.

---

# 11. QR DYNAMIQUE

Le QR dynamique doit :

- expirer ;
- être signé ;
- être limité à une finalité ;
- posséder un nonce ;
- refuser le replay ;
- être invalidé après usage si nécessaire.

Champs conceptuels :

```text
token_id
card_id
purpose
subject_id
expires_at
nonce
signature
```

---

# 12. PROFIL PUBLIC MINIMAL

Le titulaire contrôle les champs visibles.

Exemples autorisables :

- nom d’affichage ;
- photo ;
- ville approximative ;
- activité ;
- badge vérifié ;
- liens publics ;
- disponibilité partenaire.

Par défaut :

```text
profil minimal
```

Aucune information commerciale ou sensible.

---

# 13. VÉRIFICATION DE LA CARTE

Le scan doit afficher :

- carte active ou non ;
- titulaire correspondant ;
- badge ;
- offre ;
- expiration ;
- contexte ;
- action autorisée.

Ne jamais afficher :

- raison détaillée d’une suspension ;
- KYC complet ;
- solde ;
- historique.

---

# 14. RELATION AVEC LE COMPTE UNIVERSEL

Une carte appartient à :

- un compte ;
- éventuellement un espace ;
- éventuellement une organisation ;
- éventuellement un bénéficiaire représenté.

Un compte peut posséder :

- une carte principale ;
- plusieurs supports ;
- une carte partenaire ;
- une carte institutionnelle ;
- une carte temporaire.

Les règles d’unicité sont configurables.

---

# 15. RELATION AVEC LE WALLET

La Carte peut :

- ouvrir le Wallet ;
- présenter un QR de réception ;
- initier un paiement ;
- confirmer un transfert ;
- afficher un résumé non sensible au titulaire ;
- sélectionner un moyen de paiement ;
- recevoir un cashback ;
- payer un abonnement ;
- payer une offre partenaire.

La Carte ne possède pas de solde indépendant par défaut.

Le Wallet reste source de vérité.

---

# 16. PAIEMENT PAR CARTE WASPLEX

Parcours :

```text
Commerçant ou utilisateur scanne
→ montant saisi ou reçu
→ titulaire vérifie
→ authentification adaptée
→ devis frais
→ confirmation
→ Grand Livre
→ notification
```

Règles :

- aucune opération au simple scan ;
- confirmation explicite ;
- MFA selon montant ;
- idempotence ;
- limite ;
- preuve ;
- reçu.

---

# 17. RÉCEPTION D’ARGENT

Le titulaire peut afficher :

```text
Recevoir
→ QR
→ montant optionnel
→ note
→ expiration
```

Le payeur voit :

- nom d’affichage ;
- identifiant ;
- montant ;
- devise ;
- référence.

---

# 18. PAIEMENT MARCHAND FUTUR

Un partenaire ou marchand peut :

- scanner la carte ;
- créer une demande ;
- appliquer une offre ;
- confirmer la prestation ;
- recevoir un paiement ;
- déclencher un avantage.

Le marchand n’accède pas au Wallet complet.

---

# 19. PARTENAIRES

Un partenaire doit être vérifié.

Dossier :

- identité légale ;
- représentant ;
- pays ;
- contrat ;
- offre ;
- commission ;
- points de vente ;
- règlement ;
- remboursement ;
- statut.

Le partenaire ne reçoit jamais un accès général au profil utilisateur.

---

# 20. OFFRES PARTENAIRES

Une offre peut proposer :

- réduction ;
- cashback ;
- avantage en nature ;
- tarif préférentiel ;
- accès prioritaire ;
- service inclus ;
- WP financés ;
- récompense contractuelle.

Elle affiche :

- partenaire ;
- avantage ;
- conditions ;
- lieux ;
- dates ;
- plafonds ;
- exclusions ;
- preuve ;
- délai de validation.

---

# 21. OPÉRATION PARTENAIRE

Parcours :

```text
Utilisateur présente la carte
→ partenaire enregistre
→ référence créée
→ preuve
→ confirmation
→ calcul
→ règlement
→ avantage ou cashback
→ Wallet
```

Aucun avantage définitif sur simple déclaration non vérifiée.

---

# 22. SOURCES DE VALEUR PARTENAIRE

Sources autorisées :

- commission sur vente réelle ;
- remise financée ;
- budget promotionnel ;
- frais de service ;
- autre revenu contractuel réel.

Interdit :

- rémunérer les anciens détenteurs avec l’achat des nouvelles cartes ;
- promettre un rendement garanti ;
- présenter la carte comme une action ;
- créer une chaîne de recrutement rémunérée.

---

# 23. PARTAGE D’UNE OPÉRATION PARTENAIRE

Une opération peut contenir :

```text
Montant brut
- taxes/frais externes
= montant net

Montant net
├── avantage utilisateur
├── part Wasplex
├── part partenaire
└── pool collectif éventuel
```

La règle est :

- versionnée ;
- annoncée ;
- comptabilisée ;
- auditable.

---

# 24. POOL COLLECTIF ÉVENTUEL

Un pool peut exister uniquement si :

- financé par un revenu externe réel ;
- règle approuvée ;
- cartes éligibles définies ;
- période définie ;
- formule définie ;
- distribution non garantie.

Une période peut produire zéro.

Ce mécanisme est facultatif et désactivé par défaut.

---

# 25. RELATION AVEC LES ABONNEMENTS

La Carte peut vérifier :

- plan actif ;
- classe économique ;
- date ;
- services ;
- accès Fonds ;
- avantages.

Un partenaire peut offrir un avantage selon le plan.

Il ne doit pas recevoir l’historique complet de l’abonnement.

Réponse minimale :

```text
eligible = true
offer_code = ...
expires_at = ...
```

---

# 26. RELATION AVEC LE FONDS

La Carte peut :

- ouvrir le Solde Fonds ;
- confirmer l’identité lors d’un rendez-vous ;
- présenter un QR de paiement prestataire ;
- vérifier une autorisation ;
- confirmer une remise.

Elle ne doit pas exposer :

- montant d’un vœu ;
- contributions des autres ;
- situation à régulariser ;
- dossier social complet.

---

# 27. RELATION AVEC ALERTES

La Carte peut servir à :

- prouver l’identité dans une restitution ;
- ouvrir un dossier autorisé ;
- présenter un code ;
- confirmer une remise ;
- identifier un témoin autorisé ;
- scanner un objet étiqueté futur.

---

# 28. RESTITUTION ALERTES PAR CARTE

Parcours :

```text
Correspondance validée
→ rendez-vous
→ QR de restitution
→ scan des parties
→ vérification
→ code à usage unique
→ double confirmation
→ récompense éventuelle
→ clôture
```

Aucune partie ne reçoit les coordonnées complètes de l’autre avant la politique prévue.

---

# 29. ÉTIQUETTE D’OBJET FUTURE

Une étiquette Wasplex peut être associée à un objet.

Le scan peut :

- signaler l’objet trouvé ;
- contacter le propriétaire via relais ;
- ouvrir une restitution ;
- masquer l’identité.

Cette fonctionnalité est future et séparée du support principal.

---

# 30. RELATION AVEC SANTÉ

La Carte peut servir de clé pour la capsule médicale d’urgence.

Elle ne stocke pas directement :

- groupe sanguin complet non vérifié ;
- allergies ;
- traitements ;
- dossier médical ;
- historique.

Elle contient seulement un identifiant ou jeton.

---

# 31. ACCÈS SANTÉ D’URGENCE

Parcours :

```text
Carte scannée
→ sujet identifié
→ professionnel ou institution vérifié
→ finalité urgence
→ justification
→ MFA récente
→ demande bris de glace
→ capsule minimale
→ expiration
→ audit
```

L’accès reste limité à la capsule autorisée.

---

# 32. CARTE ET PERSONNE INCONSCIENTE

Le support physique peut aider à retrouver :

- identité utile ;
- contact d’urgence ;
- capsule si autorisée.

Mais le système doit :

- journaliser ;
- limiter ;
- vérifier le demandeur ;
- éviter un accès public général.

---

# 33. RELATION AVEC MON ESPACE

Mon Espace permet :

- consulter la carte ;
- afficher le QR ;
- choisir la visibilité ;
- gérer les supports ;
- acheter ;
- renouveler ;
- suspendre ;
- voir les avantages ;
- voir les opérations ;
- voir les accès Santé ;
- voir les restitutions.

---

# 34. PERSONNALISATION VISUELLE

L’administration peut configurer :

- thème ;
- image ;
- logo ;
- nom ;
- couleur ;
- badge ;
- partenaires ;
- ordre.

La personnalisation ne modifie pas les capacités techniques.

---

# 35. CYCLE DE VIE D’UNE CARTE

États :

```text
draft
ordered
awaiting_payment
paid
issued
active
physical_preparation
shipped
delivered
suspended
lost
stolen
expired
renewed
replaced
closed
refunded
```

Une carte fermée conserve son historique.

---

# 36. ÉMISSION

Parcours :

```text
Éligibilité
→ offre
→ paiement éventuel
→ génération
→ activation virtuelle
→ support physique éventuel
```

La carte virtuelle peut être activée avant le support physique.

---

# 37. ACTIVATION DU SUPPORT PHYSIQUE

Après réception :

```text
code d’activation
→ authentification
→ lien avec carte
→ statut actif
```

Un support non activé ne doit pas donner accès aux opérations sensibles.

---

# 38. PERTE OU VOL

Actions :

- suspendre immédiatement ;
- révoquer les jetons ;
- fermer les QR actifs ;
- conserver la carte virtuelle ;
- demander remplacement ;
- signaler une opération.

Le support perdu ne doit pas permettre de retirer des fonds.

---

# 39. REMPLACEMENT

Parcours :

```text
suspension
→ demande
→ frais éventuels
→ fabrication
→ activation nouveau support
→ ancien support révoqué
```

---

# 40. EXPIRATION ET RENOUVELLEMENT

À expiration :

- support ne donne plus les avantages expirés ;
- Wallet reste accessible par le compte ;
- historique conservé ;
- gains acquis conservés ;
- renouvellement possible ;
- carte de base virtuelle peut rester selon politique.

---

# 41. TRANSFERT DE CARTE

Une carte personnelle n’est pas transférable.

Une carte organisationnelle peut être réattribuée uniquement par procédure :

- révocation ;
- fermeture du lien ;
- nouvelle émission ;
- audit.

---

# 42. PLAFONDS

Configurables :

- paiements ;
- réception ;
- opérations partenaires ;
- cashback ;
- QR ;
- fréquence ;
- pays ;
- offre ;
- KYC ;
- support ;
- jour/mois.

---

# 43. FRAIS

Frais possibles :

- émission ;
- renouvellement ;
- support physique ;
- remplacement ;
- expédition ;
- paiement ;
- partenaire ;
- conversion future.

Chaque frais est affiché avant confirmation.

---

# 44. SÉCURITÉ DU QR

Contrôles :

- signature ;
- expiration ;
- nonce ;
- finalité ;
- domaine ;
- anti-replay ;
- appareil ;
- risque ;
- idempotence.

Aucune information sensible en clair dans le QR.

---

# 45. NFC FUTUR

Le NFC peut servir à :

- identification ;
- paiement ;
- opération partenaire ;
- ouverture de service.

Il doit respecter les mêmes contrats que le QR.

Le NFC n’est pas requis pour la V1.

---

# 46. AUTHENTIFICATION DES OPÉRATIONS

Selon le risque :

- simple confirmation ;
- code PIN ;
- biométrie locale ;
- OTP ;
- MFA ;
- double confirmation ;
- validation partenaire.

Le scan seul ne suffit pas pour une opération financière.

---

# 47. CONFIDENTIALITÉ

Le titulaire contrôle :

- profil public ;
- photo ;
- nom ;
- ville ;
- liens ;
- disponibilité.

Les partenaires ne reçoivent que :

- identité minimale ;
- éligibilité ;
- référence ;
- résultat.

---

# 48. JOURNAL DES ACCÈS

Le titulaire peut voir :

- scans importants ;
- paiements ;
- partenaires ;
- accès Santé ;
- restitutions ;
- suspensions ;
- supports ;
- appareils.

Les scans publics simples peuvent être agrégés selon politique.

---

# 49. ADMINISTRATION CARTE

Dashboard :

- cartes actives ;
- offres ;
- supports ;
- commandes ;
- partenaires ;
- opérations ;
- cashback ;
- commissions ;
- suspensions ;
- fraudes ;
- accès Santé ;
- restitutions ;
- remboursements.

---

# 50. ADMINISTRATION DES OFFRES

Configurer :

- nom ;
- code ;
- prix ;
- durée ;
- support ;
- services ;
- avantages ;
- partenaires ;
- plafonds ;
- pays ;
- visuel ;
- dates ;
- état.

Versionnement obligatoire.

---

# 51. ADMINISTRATION DES SUPPORTS

Fonctions :

- fabrication ;
- lot ;
- stock ;
- personnalisation ;
- expédition ;
- activation ;
- remplacement ;
- destruction ;
- fournisseur.

Aucune donnée sensible ne doit être transmise au fabricant au-delà du nécessaire.

---

# 52. ADMINISTRATION DES PARTENAIRES

Fonctions :

- vérifier ;
- contrat ;
- points de vente ;
- offres ;
- commission ;
- règlement ;
- suspension ;
- équipe ;
- audit.

---

# 53. RÔLES ET CAPACITÉS

## Utilisateur

```text
card.view.self
card.qr.generate.self
card.order.self
card.renew.self
card.suspend.self
card.replace.self
card.operations.view.self
```

## Partenaire

```text
card.partner.verify
card.partner.operation.create
card.partner.operation.confirm
card.partner.refund
card.partner.offer.manage
```

## Santé

```text
card.health.emergency.request
card.health.emergency.read
```

## Alertes

```text
card.alert.restitution.verify
card.alert.restitution.confirm
```

## Administration

```text
card.offer.manage
card.issue.manage
card.physical_support.manage
card.partner.manage
card.audit.view
card.exception.manage
```

---

# 54. SÉPARATION DES ESPACES

Une carte partenaire ou institutionnelle est liée à un espace précis.

Lors du scan :

- contexte demandé ;
- espace vérifié ;
- capacité vérifiée ;
- aucune fuite entre espace utilisateur et organisation.

---

# 55. MODÈLE DE DONNÉES

Entités recommandées :

```text
card_offers
card_offer_versions
cards
card_supports
card_support_orders
card_support_shipments
card_public_identifiers
card_qr_tokens
card_qr_usages
card_access_policies
card_operations
card_operation_events
card_partner_links
card_partner_offers
card_partner_operations
card_partner_settlements
card_benefits
card_cashbacks
card_collective_pools
card_health_access_links
card_alert_restitution_links
card_suspensions
card_replacements
card_audit_events
```

---

# 56. CHAMPS — CARD

```text
id
account_id
space_id
offer_version_id
public_identifier
status
issued_at
activated_at
expires_at
suspended_at
closed_at
```

---

# 57. CHAMPS — CARD SUPPORT

```text
id
card_id
support_type
serial_number
status
provider_reference
ordered_at
shipped_at
delivered_at
activated_at
revoked_at
```

---

# 58. CHAMPS — QR TOKEN

```text
id
card_id
purpose
subject_type
subject_id
nonce_hash
status
expires_at
used_at
created_at
```

---

# 59. CHAMPS — PARTNER OPERATION

```text
id
partner_id
offer_id
card_id
external_reference
gross_amount
currency
status
proof_reference
settled_at
ledger_transaction_id
```

---

# 60. MACHINES D’ÉTATS — CARTE

```text
ordered
awaiting_payment
issued
active
suspended
lost
stolen
expired
replaced
closed
refunded
```

---

# 61. MACHINES D’ÉTATS — OPÉRATION PARTENAIRE

```text
created
pending_proof
submitted
confirmed
settlement_pending
settled
benefit_pending
benefit_credited
rejected
cancelled
refunded
disputed
```

---

# 62. API UTILISATEUR

```text
GET    /api/cards
GET    /api/cards/{id}
POST   /api/cards
POST   /api/cards/{id}/activate
POST   /api/cards/{id}/renew
POST   /api/cards/{id}/suspend
POST   /api/cards/{id}/replace

POST   /api/cards/{id}/qr
GET    /api/cards/{id}/operations
GET    /api/cards/{id}/benefits
GET    /api/cards/{id}/partners
```

---

# 63. API SCAN

```text
POST   /api/card-scan/resolve
POST   /api/card-scan/payment
POST   /api/card-scan/partner-operation
POST   /api/card-scan/alert-restitution
POST   /api/card-scan/health-emergency
```

Le serveur résout la finalité avant de retourner une projection.

---

# 64. API PARTENAIRE

```text
GET    /api/partner/cards/offers
POST   /api/partner/card-operations
GET    /api/partner/card-operations/{id}
POST   /api/partner/card-operations/{id}/confirm
POST   /api/partner/card-operations/{id}/cancel
POST   /api/partner/card-operations/{id}/refund
```

---

# 65. API ADMINISTRATION

```text
GET    /api/admin/cards/dashboard
GET    /api/admin/cards/offers
POST   /api/admin/cards/offers
PATCH  /api/admin/cards/offers/{id}
POST   /api/admin/cards/offers/{id}/publish

GET    /api/admin/cards
GET    /api/admin/cards/{id}
POST   /api/admin/cards/{id}/suspend
POST   /api/admin/cards/{id}/close

GET    /api/admin/card-supports
POST   /api/admin/card-supports/{id}/ship
POST   /api/admin/card-supports/{id}/revoke

GET    /api/admin/card-partners
POST   /api/admin/card-partners/{id}/verify
POST   /api/admin/card-partners/{id}/suspend
```

---

# 66. ÉVÉNEMENTS MÉTIER

```text
CardOrdered
CardPaymentReceived
CardIssued
CardActivated
CardSuspended
CardReportedLost
CardReportedStolen
CardExpired
CardRenewed
CardReplaced
CardClosed

CardQrGenerated
CardQrResolved
CardQrRejected

CardPartnerOperationCreated
CardPartnerOperationConfirmed
CardPartnerSettlementReceived
CardBenefitCalculated
CardBenefitCredited
CardPartnerOperationRefunded

CardHealthEmergencyRequested
CardAlertRestitutionConfirmed
```

---

# 67. INTÉGRATION AVEC LE SUPER MOTEUR

Événements valorisables :

```text
CARD_PURCHASE
CARD_RENEWAL
CARD_REPLACEMENT
PARTNER_OPERATION_SETTLED
PARTNER_CASHBACK
PARTNER_COLLECTIVE_POOL_DISTRIBUTION
CARD_PAYMENT
CARD_REFUND
```

Le Super moteur :

- calcule ;
- réserve ;
- comptabilise ;
- crédite ;
- compense ;
- audite.

---

# 68. INTÉGRATION AVEC LE GRAND LIVRE

Comptes réservés :

```text
card.subscription.revenue
card.physical_support.cost
partner.receivable
partner.settlement.pending
partner.user.benefit.pending
partner.user.benefit.available
partner.wasplex.commission
partner.collective.pool
```

Aucune valeur n’est créditée directement depuis l’API partenaire.

---

# 69. NOTIFICATIONS

- carte créée ;
- paiement reçu ;
- support expédié ;
- carte activée ;
- carte suspendue ;
- scan sensible ;
- paiement ;
- avantage ;
- cashback ;
- remboursement ;
- accès Santé ;
- restitution Alertes ;
- expiration ;
- renouvellement.

---

# 70. ANTIFRAUDE

Signaux :

- scans répétés ;
- QR rejoué ;
- plusieurs cartes ;
- support cloné ;
- partenaire anormal ;
- cashback excessif ;
- opération annulée après avantage ;
- géographie incohérente ;
- identifiant testé en masse ;
- accès Santé abusif.

Décisions :

```text
allow
monitor
hold
review
deny
```

---

# 71. SÉCURITÉ

- QR signé ;
- jeton court ;
- expiration ;
- anti-replay ;
- MFA ;
- idempotence ;
- rate limiting ;
- contrôle des partenaires ;
- audit ;
- chiffrement ;
- révocation ;
- aucune donnée médicale locale ;
- aucune clé financière persistante sur le support.

---

# 72. PERFORMANCE

- résolution QR rapide ;
- cache des offres publiques ;
- aucune lecture complète du profil ;
- projection minimale ;
- index ;
- expiration automatique ;
- support réseau faible ;
- QR fonctionnant avec une charge courte.

---

# 73. RÉSEAU FAIBLE

Prévoir :

- QR compact ;
- reprise ;
- écran de vérification léger ;
- délais ;
- mode partenaire PWA ;
- brouillon d’opération non financière ;
- synchronisation.

Une opération financière ne devient définitive qu’après confirmation serveur.

---

# 74. ACCESSIBILITÉ

- QR agrandi ;
- contraste ;
- texte lisible ;
- lecteur d’écran ;
- alternative au scan ;
- identifiant manuel ;
- vibration ;
- confirmation claire.

---

# 75. ÉCRANS UTILISATEUR

## 75.1. Ma Carte

- visuel ;
- QR ;
- état ;
- offre ;
- expiration ;
- actions.

## 75.2. Recevoir

- QR ;
- montant ;
- référence ;
- expiration.

## 75.3. Payer

- scan ;
- bénéficiaire ;
- montant ;
- frais ;
- confirmation.

## 75.4. Avantages

- partenaires ;
- offres ;
- conditions ;
- proximité.

## 75.5. Opérations

- paiements ;
- avantages ;
- cashback ;
- restitutions ;
- accès Santé.

## 75.6. Support physique

- commande ;
- suivi ;
- activation ;
- suspension ;
- remplacement.

---

# 76. ÉCRANS PARTENAIRE

- scanner ;
- créer opération ;
- appliquer offre ;
- confirmer ;
- preuve ;
- annuler ;
- rembourser ;
- historique ;
- règlement ;
- statistiques.

---

# 77. ÉCRANS ADMINISTRATION

- offres Carte ;
- cartes ;
- supports ;
- commandes ;
- expéditions ;
- partenaires ;
- offres partenaires ;
- opérations ;
- règlements ;
- cashback ;
- fraudes ;
- accès sensibles ;
- audit.

---

# 78. TESTS CARTE

- émission ;
- activation ;
- expiration ;
- renouvellement ;
- suspension ;
- perte ;
- remplacement ;
- support physique ;
- identifiant public ;
- profil minimal.

---

# 79. TESTS QR

- QR valide ;
- expiré ;
- rejoué ;
- finalité incorrecte ;
- signature ;
- montant ;
- autre carte ;
- identifiant manuel ;
- réseau faible.

---

# 80. TESTS WALLET

- réception ;
- paiement ;
- frais ;
- solde insuffisant ;
- double paiement ;
- annulation ;
- remboursement ;
- grand livre ;
- notification.

---

# 81. TESTS PARTENAIRES

- partenaire vérifié ;
- partenaire suspendu ;
- offre active ;
- preuve ;
- règlement ;
- cashback ;
- annulation ;
- remboursement ;
- double avantage impossible ;
- pool désactivé.

---

# 82. TESTS ALERTES

- restitution ;
- code unique ;
- double confirmation ;
- identité minimale ;
- récompense ;
- litige ;
- clôture.

---

# 83. TESTS SANTÉ

- professionnel vérifié ;
- urgence ;
- justification ;
- capsule seulement ;
- expiration ;
- audit ;
- carte perdue ;
- aucun dossier stocké sur carte.

---

# 84. TESTS DE CONFIDENTIALITÉ

- scan public minimal ;
- partenaire sans profil complet ;
- solde masqué ;
- KYC masqué ;
- Santé masquée ;
- Alertes sensibles masquées ;
- abonnement renvoyé comme éligibilité minimale.

---

# 85. TESTS VISUELS

Captures minimales :

1. carte virtuelle ;
2. QR ;
3. profil public minimal ;
4. recevoir ;
5. payer ;
6. confirmation ;
7. avantages ;
8. partenaires ;
9. historique ;
10. support physique ;
11. perte/suspension ;
12. restitution Alertes ;
13. accès Santé ;
14. partenaire scanner ;
15. administration ;
16. mobile 320/360/390.

---

# 86. CRITÈRES D’ACCEPTATION

Le module est accepté lorsque :

1. la carte virtuelle existe ;
2. le support physique est facultatif ;
3. le QR est contextualisé ;
4. les jetons expirent ;
5. le replay est bloqué ;
6. la Carte est liée au compte ;
7. le Wallet reste source de vérité ;
8. un paiement exige confirmation ;
9. les partenaires sont vérifiés ;
10. les avantages proviennent d’opérations réelles ;
11. aucun rendement n’est garanti ;
12. aucune redistribution pyramidale ;
13. Alertes peut utiliser la Carte ;
14. Santé peut utiliser la Carte ;
15. aucune donnée médicale n’est stockée sur le support ;
16. perte et remplacement fonctionnent ;
17. administration complète ;
18. grand livre intégré ;
19. super moteur intégré ;
20. tests critiques verts.

---

# 87. ORDRE D’IMPLÉMENTATION

## Phase 1 — Carte virtuelle

- modèle ;
- émission ;
- affichage ;
- QR public ;
- suspension.

## Phase 2 — QR sécurisé

- jetons ;
- finalités ;
- résolution ;
- anti-replay ;
- audit.

## Phase 3 — Wallet

- recevoir ;
- payer ;
- frais ;
- reçus ;
- notifications.

## Phase 4 — Partenaires

- vérification ;
- offres ;
- opérations ;
- règlement ;
- avantage.

## Phase 5 — Support physique

- commande ;
- fabrication ;
- expédition ;
- activation ;
- remplacement.

## Phase 6 — Alertes

- restitution ;
- identité ;
- codes ;
- récompense.

## Phase 7 — Santé

- bris de glace ;
- capsule ;
- audit.

## Phase 8 — Administration

- offres ;
- supports ;
- partenaires ;
- opérations ;
- fraude.

## Phase 9 — Stabilisation

- réseau faible ;
- sécurité ;
- performance ;
- accessibilité ;
- captures.

---

# 88. PREMIÈRE VERTICALE À LIVRER

```text
Utilisateur ouvre sa Carte virtuelle
→ affiche un QR de réception
→ autre utilisateur scanne
→ bénéficiaire vérifié
→ montant saisi
→ confirmation
→ transaction Grand Livre
→ Wallet débité/crédité
→ reçu
→ historique
```

Deuxième démonstration :

```text
Partenaire vérifié
→ scanne la Carte
→ opération réelle
→ preuve
→ règlement
→ cashback
→ Wallet crédité
```

---

# 89. DIRECTIVE POUR CLAUDE CODE

1. lire Compte universel, Wallet, Super moteur, Alertes, Santé et Cartes partenaires ;
2. auditer le nouveau dépôt ;
3. créer la carte virtuelle avant le support physique ;
4. créer des QR par finalité ;
5. ne jamais stocker le dossier Santé sur la carte ;
6. ne jamais créer un solde indépendant sans décision ;
7. passer toutes les valeurs par le Grand Livre ;
8. vérifier les partenaires ;
9. coder suspension et anti-replay ;
10. fournir migrations, tests et captures ;
11. ne pas reprendre le modèle historique de micro-actionnariat ;
12. ne pas promettre de rendement.

---

# 90. DÉCISION FINALE

La Carte Wasplex est une clé transversale.

Elle suit ce principe :

```text
Carte
→ identité minimale
→ finalité explicite
→ autorisation
→ service
→ preuve
→ opération
→ audit
```

Elle ne doit jamais devenir un duplicata du compte, du Wallet ou du dossier Santé.

> **La Carte Wasplex permet d’ouvrir le bon service, au bon moment, avec le minimum de données nécessaires et une preuve traçable, tout en restant une carte de services et de partenaires — jamais un titre financier ou une promesse de rendement.**
