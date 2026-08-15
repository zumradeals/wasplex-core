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
- si le navigateur ne prend pas correctement en charge le scanner, le code `WPLX:RECEIVE:…` peut être copié/collé comme fallback officiel ;
- un identifiant public `WPLX-CI-…` est explicitement refusé comme code de paiement ;
- le bénéficiaire, le montant et le solde disponible sont montrés avant confirmation ;
- le reçu et les dernières opérations Carte sont visibles après comptabilisation.

## P017-B.1 — finition de clôture

L'affichage membre utilise désormais la priorité suivante :

```text
display_name
→ prénom + nom
→ Membre Wasplex
```

Cela évite l'affichage générique lorsque le profil possède déjà un prénom et un nom mais pas de `display_name` explicite.

## Validation production du 2026-08-15

Parcours réel validé avec deux comptes :

```text
Bénéficiaire génère 500 WP
→ payeur vérifie le bénéficiaire
→ solde payeur 3 950 WP
→ confirmation
→ paiement confirmé 500 WP
→ solde payeur 3 450 WP
→ bénéficiaire +500 WP
→ reçu WPC-…
→ Historique Carte envoyé -500 WP
→ Historique Carte reçu +500 WP
```

Le même QR `WPLX:RECEIVE:…` a ensuite été rejoué volontairement et correctement refusé avec le statut « déjà utilisé ou révoqué », sans second débit.

P017-B est donc validé fonctionnellement en production. P017-B.1 clôt les finitions immédiates ; la suite de la Carte est documentée dans [`P017-ROADMAP.md`](./P017-ROADMAP.md).

## Hors périmètre immédiat

- réseau marchand / partenaires ;
- réductions partenaires et cashback ;
- remboursement Carte ;
- carte physique ;
- NFC ;
- step-up MFA utilisateur avancé ;
- paiements hors Wallet WP.

Ces éléments ne bloquent pas les prochains modules cœur de Wasplex.
