# RAPPORT — P009 : Super Moteur, Feed, attention et crédit automatique

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `c424e3b` (P008 fusionné)
**Chantier :** `docs/chantiers/P009-CHANTIER.md`
**Spécifications :** `docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md`,
`docs/08-feed-principal-wasplex.md`, `docs/00-identite-visuelle-wasplex.md`
**Statut :** ready_for_review

Ce chantier livre la promesse produit centrale de Wasplex, rappelée par la « Décision fondatrice
P009 » de `docs/ROADMAP-INDEX.md` : Matching éligible → livraison Feed → gain annoncé et réservé
→ attention validée côté serveur → transaction Grand Livre → Wallet utilisateur crédité
automatiquement. Le fondateur a explicitement demandé une interface « TikTok-like mais pas du
tout TikTok », propre, avec un rail social droit (j'aime/commenter/partager/favoris), un rail
gauche réservé aux futures Alertes, une navigation Feed/Fonds/Wallet/Alertes/Mon Espace bien
assortie (Wallet mis en avant), et un en-tête immersif dans le Feed.

---

## 1. Objectif

```text
utilisateur éligible (Matching, P008)
→ campagne approuvée sélectionnée
→ gain annoncé avant lecture
→ enveloppe de campagne réservée
→ session d'attention réelle (barre, heartbeats)
→ complétion prouvée côté serveur
→ Grand Livre écrit (débit budget réservé annonceur → crédit Wallet utilisateur)
→ Wallet utilisateur crédité, animation, historique
→ quota publicitaire consommé
→ budget campagne diminué
```

Aucun crédit n'est jamais décidé côté client — toute la chaîne est vérifiée et écrite côté
serveur, y compris la durée d'attention réelle.

## 2. Réalisé

### 2.1. Module `Wallet` (nouveau, projection utilisateur — miroir d'`AdvertiserWallet`)

- `user_wallets` (ancre : compte, devise, statut), `UserWalletContract`
  (`getOrCreate`/`availableAccountReference`/`balanceMinor`), `UserWalletQueryService` — même
  architecture que le Wallet annonceur (P003), avec le type de compte `LIABILITY_USER` déjà semé
  au Grand Livre depuis P002 (confirmant que ce choix avait été anticipé).
- `GET /api/me/wallet` (solde), `GET /api/me/wallet/history` (historique paginé, source =
  écritures du Grand Livre).

### 2.2. `capture()` sur `AdvertiserWalletReservationContract` (AdvertiserWallet, étendu)

Troisième extension du contrat (après `reserve()` en P006, `release()` en P007) :
`capture(organizationId, campaignId, amountMinor, LedgerAccountReference $destination,
idempotencyKey): string` — débite `advertiser.budget.reserved`, crédite la référence de compte
fournie par l'appelant (jamais construite en dur pour un autre module), dans une seule écriture
équilibrée du Grand Livre.

### 2.3. Enveloppe de campagne (Campaigns, étendu)

- `campaign_envelope_consumptions` (devis, classe économique, statut
  `reserved/captured/released/expired`, expiration) — suit la capacité anonyme de l'enveloppe par
  classe économique, propriété exclusive du module Campagnes.
- `CampaignEnvelopeContract` (`reserveSlot`/`captureSlot`/`releaseSlot`) et
  `CampaignEnvelopeService` — réservation avant toute lecture, capture uniquement à la
  complétion prouvée, verrouillage `FOR UPDATE` sur le devis pour sérialiser les réservations
  concurrentes (PostgreSQL interdit `FOR UPDATE` combiné à un agrégat, d'où le verrou posé sur le
  devis plutôt que sur un `count()`).

### 2.4. Module `Feed` (nouveau)

- `feed_sessions`, `feed_ad_deliveries` (livraison + session d'attention fusionnées, décision
  §2.5 du chantier), `feed_ad_interactions` (j'aime/favoris à bascule, partage cumulatif),
  `feed_ad_comments` (append-only, sans modération).
- `FeedCompositionService::nextCandidate()` — première application réelle des seuils de
  fréquence/fatigue de `matching_configurations` (P008), exactement comme promis dans
  `docs/chantiers/P008-CHANTIER.md` §3.2 : exclut une campagne dont l'enveloppe de la classe est
  épuisée, déjà livrée ≥ `frequency_max_per_window` sur la fenêtre, ou ayant atteint
  `fatigue_threshold` sur la vie du compte. Sans candidate : état vide honnête, jamais de
  publicité inventée.
- `AttentionService` — orchestrateur complet : `next()` (réserve avec retry sur les 5 prochaines
  candidates si l'enveloppe est épuisée), `start()` (consomme le quota publicitaire à
  l'exposition réelle, jamais avant), `heartbeat()` (borne la durée déclarée par le temps réel
  écoulé côté serveur, exige une progression non décroissante), `complete()` (transaction unique
  : capture l'enveloppe + écrit le Grand Livre + crédite le Wallet, idempotent — un rejeu ne
  crédite jamais deux fois), `abandon()` (libère l'enveloppe sans aucun gain).
- `FeedInteractionService` — j'aime/favoris (bascule), partage (compteur cumulatif),
  commentaires (liste plate).
- `feed:release-expired-deliveries` (commande manuelle, pas de worker planifié — cohérent avec
  P002-P008).

### 2.5. Administration

- `AdminFeedPanel.vue` : WP distribués (gains validés) + répartition des livraisons par statut
  en langage clair, capacité dédiée `admin.feed.dashboard.view`.

### 2.6. UI utilisateur (exigence explicite du fondateur)

- `FeedPanel.vue` : en-tête immersif superposé (logo, onglets Pour toi/Alertes/Explorer, pastille
  solde Wallet), bannière de gain connu avant lecture, barre de progression réelle
  (dégradé bleu→or, `docs/00` §6.3), rail social droit (j'aime/commenter/favoris/partager en
  cercles, compteurs réels), rail gauche réservé aux futures Alertes (cercles désactivés,
  visuellement présents mais inertes jusqu'à P015), bandeau marque/CTA avec « Pourquoi cette
  publicité ? », animation de gain après confirmation serveur puis avancée automatique.
- `WalletPanel.vue` : carte de solde en dégradé « valeur gagnée », historique réel horodaté.
- `UserShell.vue` : navigation basse entièrement restylée en cercles d'icônes, Wallet mis en
  avant au centre (bouton circulaire surélevé en dégradé, anneau doré actif) ; en-tête standard
  masqué sur l'onglet Feed pour laisser l'en-tête immersif seul ; onglets Fonds/Alertes restylés
  avec un badge circulaire assorti pour rester visuellement cohérents avec le reste.

## 3. Décisions explicites (voir `docs/chantiers/P009-CHANTIER.md` §2, 10 décisions numérotées)

1. Pas de « Super Moteur » générique séparé — la chaîne devis→réservation→preuve→transaction est
   implémentée directement et uniquement pour la publicité, dans `Feed`.
2. Pas d'abstraction `FeedItem` polymorphe — un seul type de contenu existe (les campagnes),
   lues directement via `ApprovedCampaignAudienceContract` (P008).
3. Onglets Alertes/Explorer visuels mais inertes (Alertes = P015).
4. Pas de heartbeats persistés individuellement — le serveur borne la durée déclarée par le
   temps réel écoulé, sans journal détaillé (antifraude renforcée = P010).
5. Livraison = session d'attention fusionnées en une seule table (`feed_ad_deliveries`).
6. Commentaires réels mais minimaux — sans fil de réponses, sans modération, sans signalement.
7. Pas de temps réel WebSocket/Reverb — le crédit Wallet est confirmé par la réponse HTTP
   synchrone de `complete()`, l'animation démarre sur cette réponse.
8. Expiration des réservations : commande manuelle (`feed:release-expired-deliveries`), pas de
   worker planifié.
9. Aucune part Wasplex distincte capturée par événement séparé — le reliquat implicite reste
   dans la réservation, sa reconnaissance comptable précise est un chantier de reporting
   distinct (P012).
10. Pas de mode réseau faible, accessibilité avancée, Explorer, Live — hors périmètre.

## 4. Contrats internes (nouveaux ou étendus)

- `Wallet\Application\Contracts\UserWalletContract` (nouveau).
- `AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract::capture()`
  (étendu, 3ᵉ méthode).
- `Campaigns\Application\Contracts\CampaignEnvelopeContract` (nouveau).
- `Matching\Application\Contracts\MatchingContract::activeFrequencyPolicy()` (étendu).
- `Subscriptions\Application\Services\SubscriptionQuotaContract::consume()` — premier appelant
  réel (P004 l'exposait sans consommateur jusqu'ici).

## 5. API et capacités

Utilisateur (self-service, aucune capacité) :

```text
POST /api/feed/sessions
GET  /api/feed/next
POST /api/feed/deliveries/{id}/start
POST /api/feed/deliveries/{id}/heartbeat
POST /api/feed/deliveries/{id}/complete
POST /api/feed/deliveries/{id}/abandon
GET  /api/feed/deliveries/{id}/why
POST /api/feed/campaigns/{id}/like
POST /api/feed/campaigns/{id}/save
POST /api/feed/campaigns/{id}/share
GET  /api/feed/campaigns/{id}/comments
POST /api/feed/campaigns/{id}/comments
GET  /api/me/wallet
GET  /api/me/wallet/history
```

Administration (MFA récente + capacité dédiée) : `GET /api/admin/feed/dashboard`
(`admin.feed.dashboard.view`).

## 6. Tests exécutés

- `php artisan test` (Pest 4) — **175 tests, 1764 assertions, aucune régression** (167 avant ce
  chantier + 8 nouveaux dans `tests/Feature/Feed/FeedVerticalTest.php`).
- Couverture explicite des scénarios obligatoires (§8 du chantier) : verticale complète
  Gold/Côte d'Ivoire avec gain connu avant lecture, réservation réelle, un seul crédit Wallet
  (rejeu de `complete()` non-doublant) ; j'aime/favoris/partage/commentaire ; abandon libère
  l'enveloppe sans gain ; rejet d'une complétion avant la durée requise ; épuisement d'enveloppe
  exclut la campagne ; épuisement de quota retourne un état vide ; capacité admin requise pour le
  tableau de bord, puis lecture réussie des agrégats.
- **Bugs réels trouvés et corrigés pendant les tests** :
  - `FeedSession` déclarait `UPDATED_AT = null` mais la migration n'a ni `created_at` ni
    `updated_at` (seulement `started_at`/`ended_at`) — 500 réel
    (`column "created_at" ... does not exist`). Corrigé avec `public $timestamps = false;`.
  - `CampaignEnvelopeService::reserveSlot()` vérifiait `CampaignQuote::STATUS_ACTIVE`, alors que
    `CampaignService::fund()` (P006) fait déjà basculer le devis en `consumed` au financement —
    chaque campagne approuvée avait donc un devis systématiquement `consumed`, jamais `active`,
    et `next()` ne retournait jamais de candidate. Corrigé en vérifiant `STATUS_CONSUMED`.
  - PostgreSQL refuse `FOR UPDATE` combiné à `count()` — corrigé en verrouillant la ligne du
    devis plutôt que les lignes de consommation.
  - `Carbon::now()->diffInMilliseconds($autre)` renvoie un écart **signé** (négatif si `$autre`
    est dans le passé) dans la version installée, pas une valeur absolue par défaut — la
    protection anti-triche (`max(0, ...)`) ramenait systématiquement la durée réelle écoulée à 0.
    Corrigé en passant `true` en second argument pour une valeur absolue.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts (un cas `possibly null` réel
  détecté par `vue-tsc` dans `FeedPanel.vue::sendHeartbeat()` après réassignation de
  `delivery.value` — corrigé en fixant le résultat dans une variable locale avant de tester
  `progress_percent`).
- `migrate:fresh` → `migrate` : aller-retour propre sur les 6 nouvelles migrations (1 module
  Wallet, 1 extension Campagnes, 4 module Feed).
- Parcours navigateur réel (Playwright/Chromium), base de données seedée via les mêmes services
  internes que la suite de tests (aucun raccourci nouveau) : campagne GamaDeals approuvée et
  financée (300 000 WP), candidat Gold/Côte d'Ivoire avec consentement de personnalisation
  accordé → Feed affiche la bannière de gain (+675 WP, 8 s) avant toute lecture → barre de
  progression réelle pendant la lecture → animation de gain après confirmation serveur → le Feed
  relivre automatiquement jusqu'à la limite de fréquence (3 livraisons dans la fenêtre, seuil par
  défaut) → Wallet utilisateur : solde réel de 2 025 WP (3 × 675), historique horodaté avec 3
  lignes « Gain publicitaire » — chaque ligne provient d'une écriture réelle du Grand Livre →
  tableau de bord administrateur Feed : 2 025 WP distribués, 3 livraisons complétées, chiffres
  strictement identiques à ceux vus côté utilisateur (même source de vérité).

## 7. Captures

Prises en conditions réelles (serveur Laravel + Vite locaux, données créées via les services
applicatifs réels, jamais de données inventées côté client) :

- Feed, bannière de gain avant lecture, en-tête immersif, rail social, rail Alertes désactivé
  (`01-feed-gain-banner.png`).
- Feed, lecture en cours, barre de progression réelle (`02-feed-playing-progress.png`).
- Feed, animation de gain après confirmation serveur (`03-feed-gain-toast.png`).
- Feed, panneau de commentaires ouvert (`04-feed-comments-sheet.png`).
- Wallet, solde réel et historique des gains publicitaires (`05-wallet-balance-history.png`).
- Administration, tableau de bord (avant sélection Feed) (`06-admin-dashboard.png`).
- Administration, tableau de bord Feed : WP distribués et livraisons par statut
  (`07-admin-feed-dashboard.png`).

Les fichiers ont été transmis directement au fondateur (non versionnés dans le dépôt, cohérent
avec la pratique des chantiers précédents).

## 8. Fichiers modifiés/ajoutés

```text
app/Modules/Wallet/                                                (nouveau module)
  Database/Migrations/2026_08_06_140000_create_user_wallets_table.php
  Infrastructure/Models/UserWallet.php
  Application/Contracts/UserWalletContract.php
  Application/Services/UserWalletQueryService.php
  Http/Controllers/User/WalletController.php, Http/routes/api.php
  Infrastructure/Providers/WalletServiceProvider.php
app/Modules/Campaigns/
  Database/Migrations/2026_08_06_140100_create_campaign_envelope_consumptions_table.php (nouveau)
  Infrastructure/Models/CampaignEnvelopeConsumption.php                                 (nouveau)
  Application/ValueObjects/CampaignEnvelopeSlot.php                                     (nouveau)
  Application/Contracts/CampaignEnvelopeContract.php                                    (nouveau)
  Application/Services/CampaignEnvelopeService.php                                      (nouveau)
  Application/Services/CampaignEnvelopeExhaustedException.php                           (nouveau)
  Application/Services/CampaignNotAvailableForDeliveryException.php                     (nouveau)
  Infrastructure/Providers/CampaignsServiceProvider.php                                 (modifié)
app/Modules/AdvertiserWallet/
  Application/Contracts/AdvertiserWalletReservationContract.php                         (modifié)
  Application/Services/AdvertiserWalletReservationService.php                           (modifié)
app/Modules/Matching/
  Application/ValueObjects/FrequencyPolicy.php                                          (nouveau)
  Application/Contracts/MatchingContract.php                                            (modifié)
  Application/Services/MatchingService.php                                              (modifié)
app/Modules/Feed/                                                  (nouveau module)
  Database/Migrations/2026_08_06_140200-140203_*.php                (4 migrations)
  Infrastructure/Models/{FeedSession,FeedAdDelivery,FeedAdInteraction,FeedAdComment}.php
  Application/ValueObjects/FeedCandidate.php
  Application/Services/{FeedCompositionService,AttentionService,FeedInteractionService,
    FeedSessionService}.php (+ exceptions)
  Http/Controllers/{User,Admin}/*.php, Http/routes/api.php
  Infrastructure/Providers/FeedServiceProvider.php
  Console/ReleaseExpiredDeliveriesCommand.php
app/Modules/Identity/Console/SeedFounderCommand.php                (modifié : capacité Feed)
config/campaigns.php                                               (modifié : 2 clés nouvelles)
bootstrap/providers.php                                            (modifié)
resources/js/Components/{FeedPanel,WalletPanel,AdminFeedPanel}.vue (nouveau)
resources/js/Pages/Identity/{UserShell,AdminShell}.vue             (modifié)
tests/Feature/Feed/FeedVerticalTest.php                            (nouveau, 8 tests)
docs/chantiers/P009-CHANTIER.md, P009-RAPPORT.md                   (nouveaux)
```

## 9. Migrations, événements, permissions

- **Migrations** : 6 nouvelles (1 Wallet, 1 extension Campagnes, 4 Feed).
- **Événements** : le Grand Livre reste la seule écriture append-only obligatoire ; aucun
  événement outbox distinct n'a été nécessaire (le crédit Wallet est confirmé de façon
  synchrone, décision §2.7).
- **Permissions** : `admin.feed.dashboard.view` (nouvelle, accordée explicitement au fondateur).

## 10. Limites restantes

- Antifraude simplifiée (bornage temps réel côté serveur, pas de journal détaillé de heartbeats)
  — durcissement complet prévu en P010.
- Pas de temps réel WebSocket/Reverb — le crédit est confirmé par la réponse HTTP synchrone.
- Commentaires sans modération ni fil de réponses.
- Aucune part Wasplex distincte comptabilisée par écriture séparée — reporting financier précis
  prévu en P012.
- Onglets Alertes/Explorer visuels mais inertes (Alertes = P015).
- Expiration des réservations expirées nécessite une exécution manuelle ou planifiée de
  `feed:release-expired-deliveries` (pas de worker automatique dans ce chantier).

## 11. État Git

`php artisan test` : 175/175. `pint --test` : vert. Frontend : format/lint/types/build verts.
Migration round-trip vérifié (6 migrations). Répertoire de travail propre après commit. Prêt
pour push et PR.

## 12. Chantier suivant recommandé

P010 — Antifraude, preuves renforcées et reprise.
