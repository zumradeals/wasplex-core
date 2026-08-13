# Wallet V2 + Hotfix abonnement GeniusPay

## Objet

Ce chantier précède Carte Wasplex et Live. Il corrige le retour GeniusPay des abonnements et transforme le Wallet utilisateur d'une projection essentiellement passive en un vrai point d'entrée self-service pour les dépôts et transferts.

## Bugs corrigés

- un paiement d'abonnement héritait du `success_url` du Studio annonceur ;
- le webhook GeniusPay historiquement configuré ne distribuait pas les événements vers les abonnements ;
- l'écran abonnement ouvrait le checkout dans un nouvel onglet puis rechargeait trop tôt ;
- plusieurs boutons Wallet étaient volontairement branchés sur `comingSoon` ;
- les statistiques « aujourd'hui / ce mois » dépendaient seulement de la page d'historique chargée côté mobile.

## Invariants

- le retour navigateur ne confirme jamais un paiement ;
- abonnement et dépôt sont revérifiés directement auprès de GeniusPay ;
- un dépôt confirmé produit une transaction Grand Livre avant d'apparaître au solde ;
- un transfert est un débit/crédit atomique et idempotent ;
- aucun transfert ne peut rendre le Wallet négatif ;
- aucun transfert vers soi-même ;
- le webhook `/api/webhooks/geniuspay` route l'événement vers le module qui possède réellement la référence ;
- le retrait reste désactivé tant qu'aucun endpoint de payout réel et vérifiable n'est documenté.

## UX

Le Wallet présente maintenant :

- solde disponible ;
- équivalent FCFA ;
- dépôt GeniusPay ;
- transfert entre membres ;
- statut clair du retrait ;
- statistiques calculées côté serveur ;
- historique Wallet unifié ;
- accès direct à l'abonnement.
