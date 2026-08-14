# P017-A — Carte Wasplex : fondation virtuelle

## Statut

Socle fonctionnel P017-A de la Carte Wasplex.

## Positionnement

La Carte Wasplex est un module transversal de l’écosystème. Elle ne devient pas un sixième onglet principal : son point d’entrée utilisateur reste la carte déjà présente dans **Mon Espace**, et d’autres modules pourront plus tard ouvrir le même service.

La Carte Wasplex n’est pas un Wallet indépendant et ne porte aucun solde propre. Le Wallet et le Grand Livre restent les sources financières de vérité.

## Périmètre livré

- offre/version `WASPLEX_BASE` publiée par défaut ;
- Carte virtuelle gratuite ;
- émission idempotente d’une seule carte personnelle active/non close par compte ;
- identifiant public Wasplex de type `WPLX-{PAYS}-{REFERENCE}` ;
- état actif/suspendu/clos ;
- génération d’un QR d’identité à durée courte ;
- un seul QR d’identité actif à la fois ;
- secret QR stocké uniquement sous forme de hash SHA-256 ;
- expiration après 2 minutes ;
- consommation unique et protection anti-replay ;
- suspension de la carte avec révocation immédiate des QR actifs ;
- projection d’identité minimale lors de la résolution ;
- audit des émissions, QR générés/résolus et suspensions ;
- écran transversal `Ma Carte` accessible depuis l’entrée Carte Wasplex de Mon Espace.

## Confidentialité

La résolution QR n’expose pas :

- téléphone ;
- e-mail ;
- solde Wallet ;
- informations KYC ;
- données Santé ;
- données Fonds.

La projection P017-A se limite à l’identifiant public, au nom d’affichage, au statut, à l’offre et à un indicateur de vérification du compte.

## QR P017-A

Dans cette première verticale, la vérification du QR est **Wasplex-to-Wasplex** : le membre qui résout le QR doit être connecté à Wasplex. L’ouverture anonyme/publique n’est pas activée dans ce lot.

Le QR transporte une URL Wasplex contenant un secret opaque à forte entropie. Le serveur ne conserve que son hash, applique une expiration de 2 minutes, marque le jeton comme utilisé lors de la première résolution et rejette tout replay.

## Données

Tables introduites :

- `card_offers` ;
- `card_offer_versions` ;
- `cards` ;
- `card_qr_tokens` ;
- `card_audit_events`.

## API / navigation

- `GET /services/wasplex` — page transversale Carte ;
- `GET /api/cards` — vue d’ensemble Carte ;
- `POST /api/cards` — émission idempotente de la carte de base ;
- `POST /api/cards/{card}/qr` — génération d’un QR d’identité ;
- `GET /api/cards/qr/check?token=...` — résolution authentifiée du QR ;
- `POST /api/cards/{card}/suspend` — suspension par le titulaire.

## Offre de lancement

`Wasplex Base` :

- gratuite ;
- virtuelle ;
- identité et QR ;
- aucun support physique dans P017-A ;
- aucun solde propre ;
- aucun paiement Carte dans ce lot.

## Audit

Événements métier :

- `CardIssued` ;
- `CardQrGenerated` ;
- `CardQrResolved` ;
- `CardSuspended`.

## Hors périmètre P017-A

- paiement par Carte ;
- réception de fonds via scan ;
- partenaires et avantages ;
- cashback ;
- carte physique ;
- remplacement/perte/vol complet ;
- administration avancée des offres ;
- intégration Alertes/Santé ;
- scan anonyme public.

## Suite logique P017-B

La prochaine verticale doit relier le QR à un vrai parcours Wallet/Ledger :

`scanner → bénéficiaire vérifié → montant → confirmation explicite → transaction Grand Livre → Wallet débité/crédité → reçu → Historique Carte Wasplex`.

Elle doit également fournir l’expérience scanner/caméra et les garde-fous de paiement, sans jamais créer un solde Carte parallèle.
