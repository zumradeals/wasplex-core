# P011 — Temps réel, rapprochement et retraits utilisateur

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `fea940a` (P010-B fusionné)
**Dépendances :** P003 (Wallet annonceur), P009 (Feed, attention, crédit automatique), P010
(Antifraude)
**Spécifications :** `docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md` §23-24, §39-40 ;
`docs/06-wallet-et-grand-livre-wasplex.md` §6, §22, §34, §38, §39.6, §56

## Rappel du point de départ (état réel du dépôt après P010-B)

`docs/ROADMAP-INDEX.md` ligne 45 : « P011 industrialise le temps réel, le rapprochement et les
sorties de valeur. » Le titre du chantier couvre donc trois sujets distincts. État réel constaté
avant ce chantier :

- **Temps réel** : aucune trace de Laravel Reverb, de `config/broadcasting.php`, de
  `routes/channels.php` ni d'un seul événement `ShouldBroadcast` dans tout le dépôt, alors que
  Reverb fait partie de la stack officielle (`CLAUDE.md` §4). Le Wallet se met à jour uniquement
  via la réponse HTTP de l'action qui vient de créditer (`FeedPanel.vue` rappelle `WalletPanel`
  après `complete()`) — aucune mise à jour live si un autre onglet, un autre appareil, ou une
  revue administrative (déblocage d'un hold P010) change le solde en arrière-plan.
- **Rapprochement** (`docs/06` §34) : aucun code de rapprochement entre les écritures internes du
  Grand Livre et les relevés/statuts du prestataire GeniusPay n'existe.
- **Retraits** (`docs/06` §22) : aucune route, service ou modèle de retrait n'existe. Le parcours
  documenté exige un contrôle d'éligibilité KYC selon le niveau requis (`docs/06` §22, règle
  « KYC selon niveau requis ») — **aucun module KYC n'existe dans `app/Modules/`** (confirmé par
  recherche exhaustive). Par ailleurs `PaymentProviderContract`/`GeniusPayAdapter`
  (`app/Shared/Payments`, extrait en P004) n'exposent que des méthodes de dépôt
  (`createPayment`, `verifyWebhookSignature`, `parseWebhookPayload`, `fetchPaymentStatus`) —
  aucune méthode de paiement sortant (payout/virement).

## 1. Objectif

```text
solde Wallet modifié en base (crédit Feed, déblocage antifraude)
→ événement wallet.balance.changed émis sur un canal privé par compte
→ client abonné (Echo/Reverb) met à jour l'affichage sans rechargement
```

## 2. Réduction de périmètre explicite

Ce chantier ne couvre que le **temps réel** du triptyque annoncé par le titre. Décisions :

1. **Rapprochement (`docs/06` §34) différé à P011-B.** Justification : sujet indépendant et
   suffisamment large pour son propre chantier (comparaison écritures internes / relevés
   prestataire / webhooks, statuts `matched`/`partially_matched`/`unmatched`/`duplicate`/
   `amount_mismatch`/`status_mismatch`/`manual_review`, séparation des tâches `docs/06` §38) ;
   aucune dépendance technique du temps réel ne l'exige.
2. **Retraits (`docs/06` §22) différés à P011-C.** Justification : le parcours documenté exige un
   contrôle d'éligibilité KYC — inexistant dans ce dépôt — et une capacité de paiement sortant
   chez le prestataire — également inexistante. Construire un écran de retrait sans ces deux
   fondations produirait une fonctionnalité qui ne peut pas respecter `docs/06` §22 (« KYC selon
   niveau requis », « destination vérifiée »). Un chantier KYC minimal est un préalable non encore
   planifié.
3. **Pas de canal générique « tous événements ».** Un seul canal privé par compte
   (`wallet.{accountId}`) et un seul événement (`wallet.balance.changed`), strictement celui déjà
   nommé par `docs/07` §23 — pas de bus temps réel générique pour d'autres modules (Feed, Alertes,
   etc.), qui sortiraient du périmètre Wallet de ce chantier.
4. **Pas d'outbox/relais avec rejeu pour cette diffusion.** `docs/07` §40 décrit un mode dégradé
   avec rejeu via outbox pour le temps réel en général. Ici, la diffusion `wallet.balance.changed`
   est une notification de confort d'affichage, pas une opération financière : le Grand Livre est
   déjà commité de façon synchrone et atomique **avant** l'appel de diffusion (`docs/07` §40 : « le
   grand livre reste prioritaire »). Si Reverb est indisponible ou le client déconnecté, le solde
   réel n'est jamais affecté — seule la mise à jour live est manquée, récupérée au prochain
   chargement de `WalletPanel`. Construire une infrastructure d'outbox générique avec rejeu
   n'existe encore pour aucun événement de ce dépôt et constituerait un chantier d'infrastructure
   à part entière, hors du périmètre « Wallet temps réel » annoncé ici. Utilisation directe de
   `ShouldBroadcastNow`.

## 3. Contrats internes

- `App\Modules\Wallet\Application\Contracts\UserWalletContract` (étendu) : nouvelle méthode
  `notifyBalanceChanged(string $accountId, int $amountMinor, string $origin, string $operation,
  string $ledgerTransactionId): void`. Encapsule entièrement la diffusion derrière le contrat
  Wallet — Feed n'importe aucune classe d'événement du module Wallet, conformément à
  `CLAUDE.md` §6.
- Aucun nouveau contrat cross-module créé.

## 4. Migrations

Aucune. `wallet.balance.changed` transporte son état à la volée (solde recalculé en direct depuis
le Grand Livre par `UserWalletQueryService::balanceMinor()`, jamais une valeur mise en cache).

## 5. Événements et canaux

- Canal privé `wallet.{accountId}` (`routes/channels.php`) : autorisé uniquement pour le compte
  propriétaire (`$account->id === $accountId`) — jamais un accès élargi.
- Événement `App\Modules\Wallet\Events\WalletBalanceChanged`, diffusé sous le nom
  `wallet.balance.changed` (`docs/07` §23), charge utile : `amount_minor`, `balance_minor`,
  `origin`, `operation`, `ledger_transaction_id` (sert de référence de version de projection —
  identifie sans ambiguïté l'écriture du Grand Livre à l'origine du nouveau solde), `occurred_at`.
- Points de déclenchement (après commit, jamais dans la transaction) :
  `AttentionService::complete()` (capture normale uniquement — pas la mise en attente antifraude,
  qui ne modifie aucun solde) et `FeedRiskReviewService::release()` (pas `reject()`, qui ne
  crédite rien).

## 6. API / frontend

- `resources/js/app.ts` : `configureEcho({ broadcaster: 'reverb', auth: { headers: {
  'X-XSRF-TOKEN': ... } } })` — l'authentification `/broadcasting/auth` utilise le même schéma de
  session/cookie CSRF que le reste de l'application (pas de jeton Sanctum séparé).
- `WalletPanel.vue` : abonnement `useEcho` au canal privé `wallet.{accountId}` de l'événement
  `.wallet.balance.changed`, recharge l'affichage à réception.

## 7. Permissions

Aucune nouvelle capacité — l'autorisation du canal est portée par l'identité du compte connecté,
pas par une capacité administrative.

## 8. Tests

- `Event::fake([WalletBalanceChanged::class])` : l'événement est diffusé avec le bon canal privé
  et la bonne charge utile après un crédit Feed normal (`complete()`) et après un déblocage
  antifraude (`FeedRiskReviewService::release()`).
- Aucune diffusion lors d'une mise en attente antifraude (`complete()` → `held`), d'un rejet
  (`reject()`), ni d'une relecture idempotente d'une livraison déjà `completed`.
- Suite complète (`php artisan test`) sans régression.

## 9. Critères de fin

- Code conforme, tests verts, `pint --test` vert, `npm run format/lint/types:check/build` verts.
- Capture réelle (Playwright + `php artisan reverb:start` + `php artisan serve` + `npm run dev`)
  démontrant une mise à jour Wallet reçue par WebSocket, pas seulement par la réponse HTTP de
  l'action qui crédite.
- Rapport de chantier, mise à jour de `docs/ROADMAP-INDEX.md`.
- Limites explicites : rapprochement (P011-B) et retraits (P011-C) restent non traités.
