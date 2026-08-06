# P011-B — Rapprochement Grand Livre / prestataire GeniusPay

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `73b41e8` (P011 fusionné)
**Dépendances :** P002 (Grand Livre), P003 (dépôts annonceur GeniusPay), P004 (paiements
d'abonnement GeniusPay)
**Spécifications :** `docs/06-wallet-et-grand-livre-wasplex.md` §34, §38, §56, §57.14

`docs/chantiers/P011-CHANTIER.md` avait différé le rapprochement (`docs/06` §34) à un chantier
explicite. C'est ce chantier.

## 1. État réel constaté avant ce chantier

Deux flux GeniusPay indépendants existent, chacun avec sa propre table de paiement et son propre
journal webhook append-only :

- `advertiser_wallet_deposits` + `advertiser_wallet_deposit_events` (P003) ;
- `subscription_payments` (P004, pas de journal webhook événementiel séparé — statut mis à jour
  directement).

`PaymentProviderContract` (`app/Shared/Payments`) n'expose qu'un **lookup par référence**
(`fetchPaymentStatus`) — GeniusPay (sandbox) ne fournit aucune API ni fichier de relevé
(« statement ») listant l'ensemble des transactions d'une période. Le rapprochement de ce
chantier est donc bâti sur la **revérification serveur par référence** (déjà le principe retenu
pour la confirmation initiale, `docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md` §4) et le
**journal webhook déjà stocké**, pas sur un import de relevé prestataire.

## 2. Objectif

```text
paiement GeniusPay initié (dépôt annonceur ou paiement d'abonnement)
→ revérification serveur (fetchPaymentStatus)
→ comparaison avec l'état interne + le journal webhook
→ classification (matched / partially_matched / unmatched / duplicate /
  amount_mismatch / status_mismatch / manual_review)
→ file de rapprochement, revue administrative des cas ambigus
→ export
```

## 3. Réduction de périmètre explicite

1. **Pas d'import de relevé prestataire.** GeniusPay (sandbox) n'expose aucune API de statement —
   seul `fetchPaymentStatus(reference)` existe. Rapprochement basé sur revérification serveur +
   journal webhook interne. Documenté comme limite d'intégration, pas une fonctionnalité omise.
2. **Deux modules sources, une seule capacité de rapprochement.** Plutôt que dupliquer la logique
   de classification dans AdvertiserWallet et Subscriptions, un nouveau module `Reconciliation`
   consomme un contrat léger (`ReconcilablePaymentDirectoryContract`) que chacun des deux modules
   implémente — aucune nouvelle clé étrangère cross-module, projection en valeur uniquement
   (`CLAUDE.md` §6).
3. **Aucune correction automatique du Grand Livre.** Un résultat de rapprochement n'écrit jamais
   d'écriture compensatoire lui-même — il alimente une file de revue ; toute correction reste un
   acte administratif explicite et distinct (`CLAUDE.md` §7/§24).
4. **Séparation des tâches (`docs/06` §38 : « celui qui soumet un retrait ne confirme pas le
   rapprochement ») non applicable ici** — aucun retrait n'existe encore (P011-C). Documentée comme
   règle à appliquer dès que les retraits existeront, pas ignorée.
5. **Déclenchement manuel/planifiable via commande artisan**, pas de scheduler temps réel
   automatique branché à cette étape — cohérent avec `feed:release-expired-deliveries` (P009/P010),
   déjà une commande invoquée explicitement plutôt qu'un worker continu.

## 4. Contrats internes

- `App\Modules\AdvertiserWallet\Application\Contracts\ReconcilablePaymentDirectoryContract`
  (nouveau, un par module source) : `pending(): array<ReconcilablePaymentRecord>` — projection en
  valeur (`sourceModule`, `sourceRecordId`, `providerReference`, `amountMinor`, `currency`,
  `internalStatus`, `ledgerTransactionId`, `webhookEventCount`, `createdAt`) pour les paiements non
  terminaux ou récents (fenêtre configurable).
- `App\Modules\Subscriptions\Application\Contracts\ReconcilablePaymentDirectoryContract` (même
  forme, implémentation propre au module).
- `PaymentProviderContract::fetchPaymentStatus()` (existant, P003/P004) : réutilisé tel quel.

## 5. Migrations (module `Reconciliation`, nouveau)

- `reconciliation_runs` : `id`, `triggered_by` (nullable, référence par valeur à Identity Account),
  `started_at`, `completed_at`, `total_checked`, `summary` (jsonb : compte par résultat).
- `reconciliation_entries` : `id`, `run_id` (FK cascade), `source_module`, `source_record_id`
  (référence par valeur), `provider_reference`, `amount_minor`, `currency`, `internal_status`,
  `provider_status_raw`, `result_code` (CHECK sur les 7 valeurs de `docs/06` §34), `notes`,
  `created_at`.

## 6. API / UI

- `POST /api/admin/reconciliation/runs` — déclenche un rapprochement (capacité
  `admin.reconciliation.review`).
- `GET /api/admin/reconciliation/runs` — historique des exécutions.
- `GET /api/admin/reconciliation/entries?result=...` — file filtrable par résultat.
- `GET /api/admin/reconciliation/entries/export` — export CSV (`docs/06` §56 « export »).
- Onglet Rapprochement dans `AdminShell.vue`.

## 7. Permissions

Nouvelle capacité `admin.reconciliation.review`, ajoutée au fondateur (`SeedFounderCommand`).

## 8. Tests (`docs/06` §56)

- référence exacte → `matched` ;
- montant différent → `amount_mismatch` ;
- doublon (plusieurs événements webhook traités pour une même référence) → `duplicate` ;
- statut incompatible (interne `credited`/`activated` mais prestataire `failed`/`refunded`, ou
  l'inverse) → `status_mismatch` ;
- opération absente côté prestataire (référence introuvable) → `unmatched` ;
- rapprochement manuel (cas non catégorisable automatiquement) → `manual_review` ;
- inversion prestataire (statut redevenu `refunded`/`reversed` après un crédit déjà confirmé) →
  `manual_review` avec note explicite ;
- export CSV.

## 9. Critères de fin

Code conforme, tests verts, `pint --test` vert, `npm run format/lint/types:check/build` verts,
rapport de chantier, mise à jour de `docs/ROADMAP-INDEX.md`, limites explicites (pas d'import de
relevé, pas de correction automatique).
