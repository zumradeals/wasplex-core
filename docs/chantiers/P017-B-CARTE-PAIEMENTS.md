# P017-B — Paiement Carte Wasplex par QR

## Objet

P017-B livre la première verticale transactionnelle de la Carte Wasplex :

```text
Carte active
→ QR de réception temporaire
→ scan par un autre membre
→ résolution du bénéficiaire minimal
→ montant
→ confirmation explicite
→ Wallet
→ Grand Livre
→ reçu
→ Historique Carte Wasplex
```

## Invariants

- la Carte ne possède aucun solde indépendant ;
- le Wallet `user.available.wp` reste la source du disponible ;
- le Grand Livre reste la vérité financière ;
- le scan seul ne déclenche aucun débit ;
- le QR de réception est consommé uniquement après un paiement Ledger réussi ;
- un QR expiré, révoqué ou déjà utilisé ne peut plus payer ;
- un utilisateur ne peut pas payer sa propre Carte ;
- le bénéficiaire exposé au scanner reste une projection minimale ;
- `CARD_PAYMENT` utilise `source_module = card`, donc les mouvements apparaissent dans `Historique Carte Wasplex` ;
- l'idempotence protège les doubles clics et les reprises réseau.

## API

```text
POST /api/cards/{card}/receive-qr
POST /api/card-scan/resolve
POST /api/card-scan/payment
GET  /api/cards/operations
```

Le QR de réception utilise la forme opaque :

```text
WPLX:RECEIVE:<secret-temporaire>
```

Seul le hash du secret est persisté.

## Modèle

`card_operations` conserve : payeur, bénéficiaire, carte bénéficiaire, QR, montant, devise, note, statut, clé d'idempotence, transaction Ledger, référence de reçu et date de comptabilisation.

Le paiement comptabilisé produit exactement deux écritures :

```text
débit  user.available.wp du payeur
crédit user.available.wp du bénéficiaire
```

Le paiement pilote est plafonné à 1 000 000 WP par opération. Les politiques avancées de plafonds et de step-up MFA restent configurables dans les lots de durcissement suivants.

## Interface

Depuis `Ma Carte` :

- `Recevoir / Payer` ouvre le panneau transversal ;
- `Recevoir des WP` permet un montant optionnel et une note ;
- `Scanner pour payer` tente la caméra native lorsque `BarcodeDetector` est disponible ;
- un fallback permet de coller le contenu du QR ;
- le bénéficiaire, le montant et le solde disponible sont montrés avant confirmation ;
- le reçu et les dernières opérations Carte sont visibles après comptabilisation.

## Hors périmètre

- réseau marchand / partenaires ;
- cashback ;
- remboursement Carte ;
- carte physique ;
- NFC ;
- step-up MFA utilisateur avancé ;
- paiements hors Wallet WP.
