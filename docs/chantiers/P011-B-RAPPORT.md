# RAPPORT — P011-B : Rapprochement Grand Livre / prestataire GeniusPay

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `73b41e8` (P011 fusionné)
**Chantier :** `docs/chantiers/P011-B-RAPPROCHEMENT.md`
**Spécifications :** `docs/06-wallet-et-grand-livre-wasplex.md` §34, §38, §56, §57.14
**Statut :** ready_for_review

`docs/ROADMAP-INDEX.md` annonçait P011 comme couvrant temps réel, rapprochement et retraits. P011
n'a livré que le temps réel ; ce chantier livre le rapprochement. Les retraits restent différés à
P011-C, qui nécessite au préalable un chantier KYC minimal (absent du dépôt) et une capacité de
paiement sortant chez GeniusPay (le contrat actuel ne fait que du dépôt).

## 1. Réalisé

### 1.1. Nouveau module `Reconciliation`

- Migrations : `reconciliation_runs` (une exécution), `reconciliation_entries` (une ligne par
  paiement vérifié, `result_code` contraint aux 7 valeurs de `docs/06` §34).
- `ReconciliationService::run()` : parcourt tous les paiements « pending » (non terminaux, ou
  terminaux dans une fenêtre de rattrapage configurable — `config('reconciliation.lookback_days')`,
  7 jours par défaut, pour détecter une inversion tardive), revérifie chaque référence auprès du
  prestataire (`PaymentProviderContract::fetchPaymentStatus`), classe le résultat, persiste une
  entrée. Ne modifie jamais un solde ni une écriture — alimente uniquement la file de revue.

### 1.2. Deux modules sources, un seul service de rapprochement

`AdvertiserWallet` et `Subscriptions` gèrent chacun leur propre intégration GeniusPay
(`advertiser_wallet_deposits`, `subscription_payments`) — aucune table commune, aucune nouvelle
clé étrangère cross-module. Chacun implémente désormais
`App\Shared\Reconciliation\ReconcilablePaymentDirectoryContract` (`pending(): array` — projection
en valeur uniquement) et s'auto-enregistre via `$this->app->tag([...], 'reconciliation.directories')`
dans son propre `ServiceProvider`. Le module `Reconciliation` résout la liste via une liaison
contextuelle (`$app->tagged('reconciliation.directories')`) — il ne référence jamais la classe
concrète d'un module source, seulement le contrat partagé (`CLAUDE.md` §6).

### 1.3. Classification (`docs/06` §34)

- **matched** : statut et montant identiques des deux côtés.
- **partially_matched** : les deux côtés sont encore en attente (paiement non résolu).
- **unmatched** : la référence est introuvable chez le prestataire (HTTP 404 à la revérification).
- **duplicate** : plusieurs webhooks signés valides reçus pour la même référence (compteur déjà
  stocké par `advertiser_wallet_deposit_events`, P003).
- **amount_mismatch** : montant ou devise différents entre l'interne et le prestataire.
- **status_mismatch** : statuts incompatibles sans qu'un crédit ait déjà eu lieu.
- **manual_review** : aucune référence prestataire enregistrée ; revérification indisponible
  (erreur réseau/HTTP autre que 404) ; ou **inversion prestataire** — un paiement déjà crédité en
  interne mais que le prestataire indique désormais `rejected`/`expired`.

### 1.4. Extension minimale de `ProviderPaymentResult`

Le rapprochement d'un montant exige de connaître le montant que le prestataire a réellement
enregistré — `fetchPaymentStatus()` ne le parsait pas auparavant. `ProviderPaymentResult` et
`GeniusPayAdapter::normalizePaymentResponse()` ont été étendus de deux champs optionnels
(`amountMinor`, `currency`), lus depuis les mêmes noms de champs déjà utilisés par les webhooks
(`data.amount`/`data.currency`). **Hypothèse non revérifiée sur du trafic GeniusPay réel** (hors
sandbox) — documentée dans le code et ci-dessous comme limite.

### 1.5. API et UI admin

- `POST /api/admin/reconciliation/runs` (déclenche), `GET .../runs` (historique), `GET
  .../entries?result=...` (file filtrable), `GET .../entries/export` (CSV).
- Nouvel onglet « Rapprochement » dans `AdminShell.vue` (`AdminReconciliationPanel.vue`) : lance un
  rapprochement, liste les exécutions récentes avec résumé par résultat, filtre et exporte la file.

## 2. Décisions explicites (voir `docs/chantiers/P011-B-RAPPROCHEMENT.md` §3)

1. Pas d'import de relevé prestataire — GeniusPay (sandbox) n'expose qu'un lookup par référence.
2. Deux modules sources, un seul service de rapprochement via un contrat partagé taggé.
3. Aucune correction automatique du Grand Livre — détection et file de revue uniquement.
4. Séparation des tâches (`docs/06` §38) non applicable tant qu'aucun retrait n'existe — à
   appliquer dès P011-C.
5. Déclenchement manuel/planifiable (`reconciliation:run`), pas de worker temps réel dédié.

## 3. Contrats internes

- `App\Shared\Reconciliation\ReconcilablePaymentDirectoryContract` (nouveau, partagé) — implémenté
  par `AdvertiserWalletReconcilablePaymentDirectory` et `SubscriptionsReconcilablePaymentDirectory`.
- `PaymentProviderContract::fetchPaymentStatus()` (existant) — réutilisé ; `ProviderPaymentResult`
  étendu de `amountMinor`/`currency`.

## 4. Migrations

`reconciliation_runs`, `reconciliation_entries` (voir §5 du chantier pour le détail des colonnes).

## 5. API / événements / permissions

API détaillée en §1.5. Aucun nouvel événement. Nouvelle capacité `admin.reconciliation.review`
(ajoutée au fondateur).

## 6. Tests exécutés

- `php artisan test` (Pest 4) — **200 tests, 2324 assertions, aucune régression** (190 avant ce
  chantier + 10 nouveaux dans `tests/Feature/Reconciliation/ReconciliationTest.php`, couvrant les 8
  scénarios de `docs/06` §56 : référence exacte → matched ; montant différent → amount_mismatch ;
  doublon webhook → duplicate ; statut incompatible → status_mismatch ; référence introuvable →
  unmatched ; aucune référence → manual_review ; inversion prestataire après crédit →
  manual_review ; export CSV — plus la gestion de capacité (403 sans `admin.reconciliation.review`)
  et le déclenchement via l'API.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- Preuve réelle (Playwright/Chromium contre serveur Laravel + Vite locaux) : trois dépôts créés
  directement en base (statuts variés), un premier rapprochement lancé en tinker avec les réponses
  GeniusPay simulées (`Http::fake` — ce sandbox n'a pas d'accès réseau sortant vers `geniuspay.ci`,
  contrainte d'environnement documentée ci-dessous) produit `matched`/`amount_mismatch`/`unmatched`
  visibles dans le vrai panneau admin ; un second rapprochement déclenché **en direct depuis le
  navigateur** (bouton « Lancer un rapprochement », sans aucun mock cette fois) a réellement tenté
  d'atteindre `geniuspay.ci`, reçu un blocage réseau réel (proxy sortant de ce sandbox → HTTP 403),
  et a été classé automatiquement en `manual_review` avec la note « Revérification prestataire
  indisponible (HTTP 403) » — démonstration non scénarisée du chemin de repli de
  `ReconciliationService::classify()`.

## 7. Fichiers modifiés/ajoutés

```text
apps/platform/app/Shared/Reconciliation/ReconcilablePaymentDirectoryContract.php   (nouveau)
apps/platform/app/Shared/Reconciliation/ReconcilablePaymentRecord.php              (nouveau)
apps/platform/app/Shared/Payments/ValueObjects/ProviderPaymentResult.php           (modifié)
apps/platform/app/Shared/Payments/GeniusPayAdapter.php                            (modifié)
apps/platform/app/Modules/Reconciliation/**                                       (nouveau module)
apps/platform/app/Modules/AdvertiserWallet/Application/Services/AdvertiserWalletReconcilablePaymentDirectory.php (nouveau)
apps/platform/app/Modules/AdvertiserWallet/Infrastructure/Providers/AdvertiserWalletServiceProvider.php (modifié)
apps/platform/app/Modules/Subscriptions/Application/Services/SubscriptionsReconcilablePaymentDirectory.php (nouveau)
apps/platform/app/Modules/Subscriptions/Infrastructure/Providers/SubscriptionsServiceProvider.php (modifié)
apps/platform/app/Modules/Identity/Console/SeedFounderCommand.php                  (modifié)
apps/platform/bootstrap/providers.php                                             (modifié)
apps/platform/config/reconciliation.php                                          (nouveau)
apps/platform/resources/js/Components/AdminReconciliationPanel.vue                (nouveau)
apps/platform/resources/js/Pages/Identity/AdminShell.vue                          (modifié)
apps/platform/tests/Feature/Reconciliation/ReconciliationTest.php                 (nouveau, 10 tests)
docs/chantiers/P011-B-RAPPROCHEMENT.md, P011-B-RAPPORT.md                         (nouveaux)
```

## 8. Limites restantes

- Pas d'import de relevé prestataire (voir §3.1 du chantier) — uniquement revérification par
  référence + journal webhook.
- `Subscriptions` n'a pas de journal webhook persistant comme `AdvertiserWallet` — la classification
  `duplicate` n'est donc opérante que pour les dépôts annonceur (documenté dans
  `SubscriptionsReconcilablePaymentDirectory`).
- L'extension de `ProviderPaymentResult` avec `amountMinor`/`currency` repose sur une hypothèse de
  forme de réponse GeniusPay non revérifiée sur du trafic réel non-sandbox.
- Aucune correction automatique du Grand Livre — un résultat `amount_mismatch`/`status_mismatch`/
  `manual_review` reste une observation pour revue humaine, jamais une écriture générée seule.
- Rapprochement déclenché manuellement (commande ou bouton admin), pas de planification récurrente
  automatique.
- Retraits (P011-C) toujours non traités — nécessite un chantier KYC minimal préalable.

## 9. État Git

`php artisan test` : 200/200. `pint --test` : vert. Frontend : format/lint/types/build verts.
Répertoire de travail propre après commit. Prêt pour push et PR.

## 10. Chantier suivant recommandé

P011-C — Retraits utilisateur (`docs/06` §22), conditionné à un chantier KYC minimal préalable ; ou
P012 — Reporting et dashboards (`docs/ROADMAP-INDEX.md`), qui ne dépend que de P011 (déjà livré).
