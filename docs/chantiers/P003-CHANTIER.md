# P003 — WALLET ANNONCEUR

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `800f78133b5924bdb17cf174d11b5c2e5e09187b` (P002 fusionné sur `main`)
**Statut :** `in_progress`

## Contexte

Aucun `P003-CHANTIER.md` n'existait dans le corpus reconstruit (la numérotation
saute de P002 à P004). Ce chantier est donc dérivé de :

- `docs/06-wallet-et-grand-livre-wasplex.md` §5-9 (structure des comptes),
  §20 (dépôt), §37 (capacités) ;
- `docs/13-studio-annonceur-wasplex.md` §24-30 (Wallet annonceur, comptes,
  rechargement, dépôt supervisé) et §89 (API) ;
- `docs/19-integrations-externes-wasplex.md` (contrats internes, adaptateur,
  webhooks, statuts normalisés) ;
- `docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md`, qui documente le
  **contrat réel** de l'API Marchand GeniusPay (base API, domaines de
  checkout, en-têtes, schéma de signature, forme des réponses/webhooks) —
  utilisé ici comme référence pour implémenter l'intégration correctement
  dès la première version plutôt que de reproduire les erreurs qu'il corrige.

Précision du fondateur (2026-08-05) : l'annonceur recharge effectivement son
Wallet depuis GeniusPay pour financer ses campagnes — l'intégration
GeniusPay fait donc partie du périmètre de ce chantier, pas d'un chantier
ultérieur.

## Objectif

Donner à chaque organisation-annonceur un Wallet distinct du Wallet
personnel, alimenté par dépôt GeniusPay (sandbox), avec le Grand Livre (P002)
comme unique source de vérité.

## Inclus

- module `AdvertiserWallet` : comptes projetés (disponible, réservé,
  consommé, remboursable) calculés depuis le Grand Livre, jamais stockés
  comme colonne de solde ;
- intégration GeniusPay en sandbox exclusivement, derrière un contrat
  interne (`PaymentProviderContract`) — aucun module métier n'appelle le
  SDK/l'API GeniusPay directement ;
- cycle de vie du dépôt (docs/06 §20.2) : `created` → `awaiting_payment` →
  `payment_detected` → `confirmed` → `credited`, avec issues `rejected`/
  `expired`/`reversed` ;
- webhook GeniusPay : vérification de signature HMAC-SHA256 sur
  `timestamp.payload`, re-vérification serveur du statut avant tout crédit,
  idempotence stricte (un webhook rejoué ou un statut déjà traité ne crédite
  jamais deux fois) ;
- crédit effectif exclusivement via `LedgerPostingContract` (P002) — aucune
  colonne de solde modifiée directement ;
- API annonceur : consultation du Wallet, historique, création de dépôt,
  consultation d'un dépôt ;
- interface Studio Annonceur : onglet Wallet réel (remplace le placeholder
  P001) avec recharge, historique, statut de dépôt en cours.

## Exclus

- Wallet personnel (utilisateur) — hors périmètre, module distinct futur ;
- transfert Wallet personnel → annonceur (dépend du Wallet personnel) ;
- retrait annonceur (l'annonceur dépense son budget en campagnes, il ne
  retire pas — pas de payout dans ce chantier) ;
- réservation/consommation de budget de campagne (P006, campagnes
  inexistantes) ;
- tout prestataire de paiement autre que GeniusPay (Mobile Money, virement,
  carte) — architecture prête à en accueillir d'autres via le même contrat,
  mais un seul adaptateur implémenté ici ;
- facturation/factures PDF, dépôt supervisé administratif manuel ;
- Studio Annonceur complet (marques, médiathèque — P005).

## Invariants

- le Wallet annonceur reste une projection du Grand Livre, jamais une
  vérité indépendante ;
- aucun crédit n'est possible depuis la simple redirection navigateur —
  seul un webhook signé et revérifié auprès de GeniusPay peut déclencher un
  crédit ;
- le mode sandbox est obligatoire ; une clé contenant `live` est refusée au
  démarrage ;
- HTTPS et allowlist explicite des domaines de checkout GeniusPay ;
- toute commande externe (création de dépôt, traitement webhook) possède
  une clé d'idempotence ;
- aucune donnée client GeniusPay (nom, téléphone, moyen de paiement) n'est
  conservée dans les journaux ou événements — seules les références
  techniques (reference, statut, montant, devise) le sont.

## Capacités

- `advertiser.wallet.view` (consulter le Wallet et son historique) ;
- `advertiser.wallet.deposit.create` (initier un rechargement).

Réutilise `organization.manage.self` (P001) comme périmètre d'accès à
l'espace annonceur — pas de nouvelle capacité de gestion d'équipe ici.

## Preuves attendues

- migrations aller/retour PostgreSQL ;
- Pint, suite Pest (le paquet Larastan reste non installable dans ce bac à
  sable réseau restreint, limite documentée depuis P000) ;
- tests : dépôt créé → checkout renvoyé, webhook signé valide crédite une
  seule fois, webhook rejoué ne crédite pas deux fois, signature invalide
  rejetée, statut fournisseur inconnu ne crédite jamais, isolation
  inter-organisation (le Wallet d'une organisation n'est jamais visible par
  une autre) ;
- captures de l'onglet Wallet annonceur (liste vide, recharge, historique).
