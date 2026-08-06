# RAPPORT — P011 : Temps réel (Wallet)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `fea940a` (P010-B fusionné)
**Chantier :** `docs/chantiers/P011-CHANTIER.md`
**Spécifications :** `docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md` §23-24, §39-40 ;
`docs/06-wallet-et-grand-livre-wasplex.md` §6, §22, §34, §38, §39.6, §56
**Statut :** ready_for_review

`docs/ROADMAP-INDEX.md` annonçait P011 comme « temps réel, rapprochement et retraits utilisateur ».
Ce chantier ne couvre que le **temps réel** (voir §2 « Réduction de périmètre » ci-dessous et
`docs/chantiers/P011-CHANTIER.md` §2) : le rapprochement (`docs/06` §34) et les retraits
(`docs/06` §22) sont différés à des chantiers explicites P011-B et P011-C, faute de fondations
existantes (aucun module KYC, aucune capacité de paiement sortant chez le prestataire).

## 1. Réalisé

### 1.1. Infrastructure temps réel

Aucune trace de Laravel Reverb n'existait dans le dépôt malgré sa présence dans la stack
officielle (`CLAUDE.md` §4). Installé et configuré :

- `laravel/reverb` (Composer), `laravel-echo` / `pusher-js` / `@laravel/echo-vue` (npm).
- `config/broadcasting.php`, `config/reverb.php` publiés.
- `routes/channels.php` : un seul canal privé `wallet.{accountId}`, autorisé uniquement pour le
  compte propriétaire — jamais un accès élargi (`$account->id === $accountId`).
- `resources/js/app.ts` : `configureEcho({ broadcaster: 'reverb', auth: { headers: {
  'X-XSRF-TOKEN': ... } } })` — le cookie `XSRF-TOKEN` est lu côté client et transmis
  explicitement, car le transport interne de pusher-js pour `/broadcasting/auth` ne passe pas par
  l'instance axios de l'application (qui gère déjà le CSRF pour toutes les autres requêtes).

### 1.2. Diffusion `wallet.balance.changed`

- `UserWalletContract` (Wallet) étendu d'une méthode `notifyBalanceChanged()` — Feed n'importe
  aucune classe d'événement du module Wallet, la diffusion reste entièrement encapsulée derrière
  le contrat (`CLAUDE.md` §6).
- `App\Modules\Wallet\Events\WalletBalanceChanged` (`ShouldBroadcastNow`), diffusé sur
  `PrivateChannel("wallet.{accountId}")` sous le nom `wallet.balance.changed` (`docs/07` §23),
  charge utile : `amount_minor`, `balance_minor`, `origin`, `operation`, `ledger_transaction_id`
  (référence de version de projection), `occurred_at`.
- Déclenché après (jamais dans) les transactions qui créditent réellement le Wallet :
  `AttentionService::complete()` (capture normale — pas la mise en attente antifraude) et
  `FeedRiskReviewService::release()` (pas `reject()`).
- `WalletPanel.vue` : abonnement `useEcho('wallet.{accountId}', '.wallet.balance.changed', load)`
  — recharge le solde et l'historique dès réception, sans dépendre de la réponse HTTP de l'action
  qui a crédité.

### 1.3. Réduction de périmètre assumée : pas d'outbox/rejeu

`docs/07` §40 décrit un mode dégradé avec rejeu via outbox pour le temps réel en général. Cette
diffusion précise est une notification de confort d'affichage, pas une opération financière : le
Grand Livre est déjà commité de façon synchrone et atomique avant l'appel de diffusion. Si Reverb
est indisponible ou le client déconnecté, seul le rafraîchissement live est manqué — le solde réel
n'est jamais affecté, et `WalletPanel` le récupère au prochain chargement. Aucune infrastructure
d'outbox générique n'existe encore pour un seul événement de ce dépôt ; en construire une ferait
sortir ce chantier de son périmètre Wallet. Documenté explicitement comme limite (§4).

## 2. Décisions explicites (voir `docs/chantiers/P011-CHANTIER.md` §2)

1. Rapprochement (`docs/06` §34) différé à P011-B.
2. Retraits (`docs/06` §22) différés à P011-C — absence de module KYC et de capacité de paiement
   sortant chez le prestataire (`PaymentProviderContract` ne fait que du dépôt).
3. Un seul canal, un seul événement — pas de bus temps réel générique pour d'autres modules.
4. Pas d'outbox/relais avec rejeu pour cette diffusion (voir §1.3).

## 3. Contrats internes

- `UserWalletContract` (étendu) : `notifyBalanceChanged(string $accountId, int $amountMinor,
  string $origin, string $operation, string $ledgerTransactionId): void`.
- Aucun nouveau contrat cross-module.

## 4. Migrations

Aucune.

## 5. Événements et canaux

- Canal privé `wallet.{accountId}` (`routes/channels.php`).
- Événement `wallet.balance.changed` (`App\Modules\Wallet\Events\WalletBalanceChanged`).

## 6. Permissions

Aucune nouvelle capacité — l'autorisation du canal est portée par l'identité du compte connecté.

## 7. Tests exécutés

- `php artisan test` (Pest 4) — **190 tests, 2290 assertions, aucune régression** (186 avant ce
  chantier + 4 nouveaux dans `tests/Feature/Feed/FeedRealtimeTest.php`) :
  - diffusion de `wallet.balance.changed` sur le bon canal privé avec la bonne charge utile après
    un crédit Feed normal (`complete()`) ;
  - aucune diffusion lors d'une mise en attente antifraude (`complete()` → `held`) ;
  - diffusion après un déblocage administratif (`FeedRiskReviewService::release()`) ;
  - aucune diffusion lors d'un rejet (`reject()`) ;
  - aucune double diffusion sur une relecture idempotente de `complete()`.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- Preuve réelle bout-en-bout : `php artisan reverb:start` + `php artisan serve` + `npm run dev`
  lancés localement ; un compte candidat connecté dans un navigateur réel (Playwright/Chromium)
  affiche l'onglet Wallet (0 WP, abonné au canal `wallet.{accountId}`) ; un parcours Feed complet
  (réservation → démarrage → heartbeat → complétion) est ensuite déclenché **depuis une session
  HTTP entièrement distincte** (script `curl` séparé, cookies indépendants — aucune requête n'est
  émise par l'onglet ouvert) ; le solde affiché passe de `0 WP` à `675 WP` avec la ligne
  d'historique correspondante, **sans rechargement de page et sans que l'onglet lui-même n'ait
  émis la moindre requête pour ce crédit** — la seule voie possible est la diffusion WebSocket
  Reverb reçue par `WalletPanel.vue`.

## 8. Fichiers modifiés/ajoutés

```text
apps/platform/composer.json, composer.lock                                              (modifiés — laravel/reverb)
apps/platform/package.json, package-lock.json                                           (modifiés — laravel-echo, pusher-js, @laravel/echo-vue)
apps/platform/bootstrap/app.php                                                         (modifié — routing channels:)
apps/platform/config/broadcasting.php, config/reverb.php                                (nouveaux)
apps/platform/routes/channels.php                                                       (nouveau)
apps/platform/resources/js/app.ts                                                       (modifié — configureEcho + CSRF)
apps/platform/app/Modules/Wallet/Application/Contracts/UserWalletContract.php           (modifié)
apps/platform/app/Modules/Wallet/Application/Services/UserWalletQueryService.php        (modifié)
apps/platform/app/Modules/Wallet/Events/WalletBalanceChanged.php                        (nouveau)
apps/platform/app/Modules/Feed/Application/Services/AttentionService.php                (modifié)
apps/platform/app/Modules/Feed/Application/Services/FeedRiskReviewService.php           (modifié)
apps/platform/resources/js/Components/WalletPanel.vue                                   (modifié)
apps/platform/tests/Feature/Feed/FeedRealtimeTest.php                                   (nouveau, 4 tests)
docs/chantiers/P011-CHANTIER.md, P011-RAPPORT.md                                        (nouveaux)
```

## 9. Limites restantes

- Rapprochement (`docs/06` §34) et retraits (`docs/06` §22) non traités — chantiers P011-B et
  P011-C recommandés, ce dernier nécessitant au préalable un chantier KYC minimal.
- Pas d'outbox/rejeu pour `wallet.balance.changed` (voir §1.3) — best-effort assumé et documenté,
  sans impact sur l'exactitude du solde.
- Aucun autre module (Feed, Alertes) ne diffuse encore d'événement temps réel — hors périmètre
  Wallet de ce chantier.

## 10. État Git

`php artisan test` : 190/190. `pint --test` : vert. Frontend : format/lint/types/build verts.
Répertoire de travail propre après commit. Prêt pour push et PR.

## 11. Chantier suivant recommandé

P011-B — Rapprochement Grand Livre / prestataire GeniusPay (`docs/06` §34), ou P011-C — Retraits
utilisateur (`docs/06` §22, nécessite un chantier KYC minimal préalable).
