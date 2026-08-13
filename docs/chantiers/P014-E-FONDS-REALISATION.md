# P014-E — Fonds · Réalisation du vœu

## Statut

Implémentation du parcours post-financement du module Fonds : commande au prestataire sélectionné, jalons, preuves, allocation Ledger, règlement externe confirmé, livraison, litiges, garantie, réserve et clôture.

## Doctrine

P014-E ne transforme jamais une intention ou une écriture interne en preuve fictive de réalisation.

- une commande n'est créée qu'après sélection d'un devis et financement complet du snapshot P014-D ;
- l'apport personnel, la collecte solidaire et la réserve restent des compartiments Ledger distincts ;
- l'allocation Ledger vers le compte fournisseur est distincte du règlement externe réel ;
- un règlement externe exige une référence réelle ;
- les paiements importants sont découpables en jalons ;
- un jalon marqué comme nécessitant une preuve ne peut pas être validé sans preuve ;
- la livraison est déclarée par le prestataire puis confirmée par le bénéficiaire ;
- un litige ouvert bloque la clôture ;
- la garantie est rattachée à la commande et au prestataire vérifié, avec dates et réclamations auditables ;
- la réserve n'est utilisable que si un solde Ledger réel existe et après autorisation explicite.

## Objets métier

### Commande Fonds

`fund_orders` relie un vœu à son devis sélectionné et à l'organisation P019 prestataire. Une seule commande existe par vœu.

États principaux :

`issued → in_progress → delivered → completed`

États de contrôle : `disputed`, `cancelled`.

### Jalons

`fund_order_milestones` porte le montant, l'ordre, l'exigence de preuve, l'allocation Ledger et le règlement externe.

Le total des jalons ne peut pas dépasser le coût validé augmenté d'une éventuelle réserve explicitement autorisée.

### Preuves

`fund_order_proofs` conserve les références et descriptions de preuve : facture, bon de livraison, preuve de jalon ou autre justificatif utile. Les données sont rattachées à la commande et, lorsqu'il y en a un, au jalon concerné.

### Paiement

Le Ledger crée une dette interne envers le prestataire via un compte `fund.provider.payable.{organizationId}.wp`. Cette allocation ne vaut pas confirmation de paiement externe.

Le règlement externe possède son propre statut, sa référence et sa date. Le produit n'affiche donc pas « payé » tant que la preuve de règlement externe n'existe pas.

### Litiges

Le bénéficiaire peut ouvrir un litige après livraison. Tant qu'un litige reste ouvert, la commande ne peut pas être clôturée. L'administration peut reprendre la réalisation, accepter la livraison ou annuler le dossier avec une note de résolution.

### Garantie

`fund_order_warranties` contient : prestataire, référence, conditions, début et fin de garantie.

`fund_warranty_claims` contient les réclamations du bénéficiaire, la réponse du prestataire et la résolution finale auditée.

Une garantie ne peut être créée qu'après livraison. Une réclamation n'est recevable que pendant la période active, et une seule réclamation active est admise à la fois.

### Réserve

La réserve utilise le compte Ledger `fund.reserve.wp`. P014-E n'invente aucun crédit de réserve. L'administration ne peut autoriser sur une commande qu'un montant effectivement disponible dans le Ledger ; l'utilisation réelle crée une écriture `fund_reserve_entries` liée à la transaction Ledger.

## Sécurité et P019

Les prestataires ne disposent pas d'un second système de comptes Fonds. Ils utilisent leurs espaces professionnels P019 vérifiés et des capacités organisation-scopées :

- `professional.funds.order.view`
- `professional.funds.milestone.submit`
- `professional.funds.delivery.confirm`
- `professional.funds.warranty.manage`

L'organisation ne reçoit que les informations nécessaires à la réalisation. Le Wallet personnel, les documents privés non nécessaires et les données Santé ne sont pas exposés au prestataire.

## Surfaces

### Membre

- suivi de la commande et des jalons ;
- montant effectivement réglé ;
- confirmation de livraison ;
- ouverture d'un litige ;
- visibilité de la garantie ;
- ouverture d'une réclamation de garantie.

### Wasplex Pro

- commandes adressées à l'organisation ;
- dépôt des preuves de jalons ;
- déclaration de livraison ;
- enregistrement de la garantie ;
- réponse aux réclamations de garantie.

### Administration

- création de la commande uniquement après financement complet ;
- découpage en jalons ;
- validation des preuves ;
- allocation Ledger ;
- confirmation du règlement externe ;
- autorisation de réserve ;
- traitement des litiges ;
- résolution des réclamations de garantie.

## Critères d'acceptation

P014-E est considéré terminé lorsque :

1. aucune commande n'est possible avant financement complet ;
2. aucune preuve obligatoire ne peut être contournée ;
3. les allocations Ledger sont idempotentes ;
4. le règlement externe reste séparé et exige une référence ;
5. la livraison doit être confirmée par le bénéficiaire ;
6. un litige ouvert bloque la clôture ;
7. la réserve n'est jamais créée artificiellement ;
8. la garantie possède des dates réelles et un cycle de réclamation ;
9. le prestataire reste strictement scopé à son organisation P019 ;
10. formatage, TypeScript, build et suite Laravel complète sont verts.
