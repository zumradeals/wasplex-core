# RAPPORT — P006 : Campagne, audience, devis et budget

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `3726795` (P005 fusionné)
**Chantier :** `docs/chantiers/P006-CHANTIER.md`
**Spécifications :** `docs/13-studio-annonceur-wasplex.md` (§22, §31-48, §83-105), `docs/05-modele-economique-publicitaire-wasplex.md`, `docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md`, `docs/17-donnees-permissions-consentements-techniques-wasplex.md`
**Statut :** ready_for_review

Ce rapport remplace l'ancienne version pré-réinitialisation (branche `codex/p006-validation-regularization`,
conservée dans l'historique Git à titre d'audit mais ne décrivant plus l'état réel du dépôt reconstruit).

---

## 1. Objectif

Permettre à un annonceur non technicien de créer une **campagne rapide** en sept étapes
(Marque → Objectif → Contenu → Audience → Budget → Vérification → Soumission), avec un aperçu
smartphone/Feed vertical fidèle et réactif, une estimation d'audience agrégée jamais nominative,
un devis figé au partage 50/50 exact, et une réservation réelle du budget sur le Wallet annonceur
(P003) avant soumission pour revue administrative (P007, non construite ici).

## 2. Réalisé

### 2.1. Nouveau module `Campaigns`

`campaigns` / `campaign_versions` (configurations en JSON, décision §3.1) / `campaign_quotes` /
`campaign_budget_reservations`, plus `advertising_price_catalogs` / `advertising_price_versions`
(`docs/05` §28) pour le catalogue de prix administrable.

### 2.2. Cinq nouveaux contrats internes cross-module

Aucun module ne lit directement les tables d'un autre (`docs/CLAUDE.md` §6) :

- `Identity\AccountCountryLookupContract` — compte, jamais ne restitue les identifiants eux-mêmes.
- `Subscriptions\EconomicClassCatalogContract` — classes publiées + estimation d'audience agrégée
  (bandée à 5 près, jamais un effectif exact — `docs/13` §39).
- `AdvertiserStudio\BrandDirectoryContract` / `CreativeAssetDirectoryContract` — vérifient
  qu'une marque/un média appartient bien à l'organisation appelante.
- `AdvertiserWallet\AdvertiserWalletReservationContract` — transfère `advertiser.budget.available`
  → `advertiser.budget.reserved` via `LedgerPostingContract` (même discipline que
  `AdvertiserWalletCreditor`, P005).

`SubscriptionQuotaContract` (P004) attendait un premier appelant réel depuis P004 ; ce chantier
lui donne enfin un cousin avec un vrai appelant (`EconomicClassCatalogContract`, consommé par
`CampaignQuoteService`).

### 2.3. Aperçu publicitaire temps réel (`docs/13` §22)

`CampaignPreviewPhone.vue` — simulation smartphone verticale (créatif, marque, titre, CTA, gain
utilisateur, barre de progression, icônes son/sous-titres), réactive localement (Vue) au fil de la
saisie dans l'assistant. **Ce n'est pas un canal Reverb** : aucun autre utilisateur n'observe cet
aperçu, seul l'annonceur qui le construit — précision documentée pour éviter toute confusion avec
le "temps réel visible" du Super Moteur (P009).

### 2.4. Audience — deux critères réels seulement

Territoire (pays déclaré du compte, `accounts.country_code`) et classe économique. Toute autre clé
de configuration d'audience (`AudienceConfigurationValidator`) est rejetée structurellement — ce
qui rend l'interdiction `docs/17` L103 (Santé/Alertes/Fonds/KYC jamais utilisés pour le ciblage)
impossible à contourner plutôt que simplement non implémentée : ces domaines ne peuvent
littéralement pas apparaître dans la configuration acceptée. L'estimation compte réellement les
abonnements actifs dans les classes ciblées (filtrés par pays si spécifié), mais la restitue en
fourchette arrondie au multiple de 5 le plus proche — jamais un effectif exact ni une liste.

**Décision explicite** : intérêt, usage déclaré, projet déclaré et audience enregistrée
(`docs/13` §36) nécessitent SmartProfile/consentements (P008, non construit) — hors périmètre,
documenté plutôt que silencieux.

### 2.5. Catalogue de prix — aucun prix inventé

Même discipline que les plans Premium/Gold/Platine en P004 : `campaigns:seed-price-catalog` crée
une version en `draft`, prix de base à 0. Un administrateur doit fixer le vrai montant
(`PATCH /api/admin/advertising/pricing/{id}`) et publier avant qu'un devis ne puisse être produit
— sinon `NoPublishedPriceCatalogException` (422), jamais un prix silencieusement inventé.

Seuls deux axes de prix sont réels dans ce chantier : le format (image/vidéo, coefficient admin)
et la classe ciblée (coefficient déjà publié par Subscriptions — **jamais dupliqué**, lu en direct
via le contrat). Durée, territoire, précision, rareté et volume (formule conceptuelle `docs/05` §9)
restent neutres (coefficient 1,0) faute de valeurs réelles dans le corpus — documenté au lieu
d'inventer des coefficients arbitraires.

### 2.6. Devis — partage 50/50 exact, sans fraction perdue

`CampaignQuoteService` : `net_distributable_amount_minor = gross_amount_minor` (aucune taxe
séparée dans le corpus), enveloppe utilisateurs = moitié entière (`intdiv`), répartie par classe
ciblée via un algorithme du plus grand reste (*largest-remainder apportionment*) sur les poids
normalisés — les parts entières somment exactement à l'enveloppe totale, jamais de centime
silencieusement perdu (`docs/05` §18). Par classe : coût par événement (base × multiplicateur
format × coefficient classe, arrondi au FCFA supérieur pour ne jamais promettre plus que
l'enveloppe ne finance), nombre d'événements financés, gain unitaire exact
(`enveloppe ÷ événements`, formule littérale `docs/05` §13), et reliquat tracé
(`gain × événements + reliquat == enveloppe`, vérifié par test).

Devis bloqué (422) si le segment ciblé est trop petit (`config('campaigns.minimum_segment_size')`,
`docs/04` §11 : "élargi, fusionné, retardé, ou **refusé**") ou si aucun catalogue n'est publié.
Expiration configurable (`quote_validity_hours`, défaut 24h).

### 2.7. Financement — réservation réelle sur le Grand Livre

`AdvertiserWalletReservationContract::reserve()` transfère le montant exact du devis de
`advertiser.budget.available` vers un nouveau compte `advertiser.budget.reserved`, sous la même
clé d'idempotence dérivée du devis (`campaign-budget-reservation:{quoteId}`). Solde insuffisant
refusé (422, montants exacts renvoyés) ; double financement idempotent — `CampaignService::fund()`
vérifie d'abord sa propre table `campaign_budget_reservations` avant tout appel au Wallet, et une
violation d'unicité concurrente sur `idempotency_key` est rattrapée comme un no-op (même pattern
que `SubscriptionQuotaService`, P004) ; devis expiré refusé (422) sans réservation créée.

### 2.8. Soumission

Budget réservé → campagne `submitted`, prête pour la file de revue administrative (P007). Aucune
édition possible après soumission dans ce chantier (pas de correction — `docs/13` §56 appartient à
P007).

### 2.9. API et permissions

Routes annonceur (`docs/13` §90-91, sous-ensemble) sous `EnsureActiveAdvertiserOrganization` +
`EnsureCapability` ; administration du catalogue de prix uniquement (`docs/05` §30) sous
`EnsureRecentMfa`. Capacités créateur ajoutées : `advertiser.campaign.view`,
`advertiser.campaign.manage`, `advertiser.campaign.submit`. Capacité admin ajoutée :
`admin.advertising.pricing.manage`.

### 2.10. UI

- `CampaignsPanel.vue` (onglet "Campagnes" d'`AdvertiserShell`, précédemment un placeholder) :
  liste des campagnes, assistant 7 étapes avec indicateur de progression, autosave débouncée
  (500 ms) à chaque changement de champ, aperçu smartphone permanent à côté du formulaire sur
  desktop (`docs/13` §6).
- `AdminAdvertisingPricingPanel.vue` (dans l'onglet "Annonceurs" d'`AdminShell`, sous le dépôt
  supervisé) : édition du catalogue en brouillon, publication.

**Bug trouvé et corrigé pendant les captures** : `CampaignService::create()` renvoyait un modèle
`Campaign` sans la relation `versions` chargée, provoquant un crash JS
(`e.versions is not iterable`) dès la création d'une campagne dans l'assistant. Corrigé en
chargeant `versions` avant de renvoyer la campagne créée.

## 3. Décisions explicites (résumé, voir aussi `docs/chantiers/P006-CHANTIER.md` §3)

1. `campaign_versions` porte les configurations en JSON (champs exacts `docs/13` §86) plutôt que
   des tables normalisées séparées issues de la liste aspirationnelle §83.
2. Aucun prix de base inventé — catalogue seedé en `draft` à 0, publication manuelle requise.
3. Audience limitée à territoire + classe économique — SmartProfile hors périmètre (P008).
4. Segment trop petit → devis refusé (option explicitement listée par `docs/04` §11).
5. `type` de campagne fixé à `fast` — la campagne avancée est Phase 8 dans `docs/13` §106,
   après le Feed (Phase 7), donc hors P006.
6. Statuts de campagne limités à `draft`/`quoted`/`funded`/`submitted` — le reste appartient à
   P007 (revue) et P009 (Super Moteur).
7. Aucune libération automatique de réservation (pause/annulation) — hors périmètre P006.

## 4. Tests exécutés

- `php artisan test` (Pest 4) — **142 tests, 996 assertions, aucune régression** (122 avant ce
  chantier + 20 nouveaux : 8 assistant/autosave/isolation, 9 devis/financement/soumission,
  3 administration du catalogue de prix).
- Couverture explicite des tests obligatoires du chantier (§8) : création < 5 min (parcours
  complet testé et rejoué manuellement via Playwright), autosave, aucune fuite de marque/campagne
  entre organisations, clé de ciblage interdite rejetée, classe économique inconnue rejetée,
  segment trop petit refusé, estimation cohérente avec le nombre réel d'abonnements actifs et
  filtrée par pays, devis bloqué sans catalogue publié, partage 50/50 exact avec poids normalisés,
  aucune fraction perdue (gain × événements + reliquat == enveloppe), financement refusé si solde
  insuffisant, financement idempotent (un seul enregistrement de réservation après deux appels),
  devis expiré refusé, soumission exige un financement actif, aucune édition après soumission.
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- `migrate:rollback --step=6` → `migrate` — aller-retour propre sur les 6 nouvelles migrations.
- Parcours navigateur (Playwright/Chromium) : inscription → organisation annonceur → marque →
  campagne → objectif → contenu → audience (estimation réelle sur abonnés GOLD synthétiques) →
  budget → devis (partage 50/50, gain affiché dans l'aperçu smartphone) → financement réel sur le
  Grand Livre → soumission ; connexion fondateur → MFA → onglet Annonceurs → catalogue de prix
  publié visible.

## 5. Captures

- Assistant, étape Objectif sélectionnée, aperçu smartphone à droite (`p006-wizard-objective.png`).
- Assistant, étape Audience avec estimation réelle affichée (`p006-wizard-audience.png`).
- Assistant, étape Vérification : devis généré (budget, événements estimés, portée, expiration),
  aperçu smartphone affichant le gain utilisateur "+675 WP" (`p006-wizard-quote.png`).
- Assistant, étape Soumission : campagne soumise, en attente de revue administrative
  (`p006-wizard-submitted.png`).
- Administration, onglet Annonceurs : catalogue de prix publicitaire publié
  (`p006-admin-pricing.png`).

## 6. Fichiers modifiés/ajoutés

```text
app/Modules/Campaigns/                                            (nouveau module complet)
  Database/Migrations/ (6 fichiers)
  Infrastructure/Models/ (AdvertisingPriceCatalog, AdvertisingPriceVersion, Campaign,
                           CampaignVersion, CampaignQuote, CampaignBudgetReservation)
  Infrastructure/Providers/CampaignsServiceProvider.php
  Application/Services/ (CampaignService, CampaignQuoteService, AudienceConfigurationValidator,
                          exceptions)
  Http/Controllers/Advertiser/ (CampaignsController, TargetingController)
  Http/Controllers/Admin/PricingController.php
  Http/routes/api.php
  Console/SeedPriceCatalogCommand.php
config/campaigns.php                                               (nouveau)
app/Modules/Identity/Application/Contracts/AccountCountryLookupContract.php      (nouveau)
app/Modules/Identity/Application/Services/AccountCountryLookupService.php       (nouveau)
app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php       (modifié)
app/Modules/Identity/Application/Services/OrganizationRegistrationService.php   (modifié)
app/Modules/Identity/Console/SeedFounderCommand.php                             (modifié)
app/Modules/Subscriptions/Application/Contracts/EconomicClassCatalogContract.php (nouveau)
app/Modules/Subscriptions/Application/Services/EconomicClassCatalogService.php   (nouveau)
app/Modules/Subscriptions/Application/ValueObjects/ (EconomicClassSummary, AudienceEstimate)
app/Modules/Subscriptions/Infrastructure/Providers/SubscriptionsServiceProvider.php (modifié)
app/Modules/AdvertiserStudio/Application/Contracts/ (BrandDirectoryContract,
                                                      CreativeAssetDirectoryContract) (nouveau)
app/Modules/AdvertiserStudio/Application/Services/ (BrandDirectoryService,
                                                     CreativeAssetDirectoryService) (nouveau)
app/Modules/AdvertiserStudio/Application/ValueObjects/ (BrandSummary, CreativeAssetSummary)
app/Modules/AdvertiserStudio/Infrastructure/Providers/AdvertiserStudioServiceProvider.php (modifié)
app/Modules/AdvertiserWallet/Application/Contracts/AdvertiserWalletReservationContract.php (nouveau)
app/Modules/AdvertiserWallet/Application/Services/ (AdvertiserWalletReservationService,
                                                     InsufficientAdvertiserBalanceException) (nouveau)
app/Modules/AdvertiserWallet/Infrastructure/Providers/AdvertiserWalletServiceProvider.php (modifié)
bootstrap/providers.php                                            (modifié)
resources/js/Components/CampaignsPanel.vue                         (nouveau)
resources/js/Components/CampaignPreviewPhone.vue                   (nouveau)
resources/js/Components/AdminAdvertisingPricingPanel.vue           (nouveau)
resources/js/Pages/Identity/AdvertiserShell.vue                    (modifié)
resources/js/Pages/Identity/AdminShell.vue                         (modifié)
tests/Feature/Campaigns/ (3 fichiers, 20 tests)
docs/chantiers/P006-CHANTIER.md, P006-RAPPORT.md                   (réécrits)
```

## 7. Migrations, API, événements, permissions

- **Migrations** : 6 nouvelles tables (§2.1), aucune modification de tables existantes.
- **API** : voir §2.9 — `/api/advertiser/{campaigns,economic-classes}`,
  `/api/admin/advertising/pricing`.
- **Événements** : aucun événement outbox dédié — les transitions de statut sont directement
  persistées ; la traçabilité financière passe par le Grand Livre (transaction
  `CAMPAIGN_BUDGET_RESERVED`, référence métier = campaign id).
- **Permissions** : créateur d'organisation — `advertiser.campaign.view`,
  `advertiser.campaign.manage`, `advertiser.campaign.submit` (nouvelles) ; admin —
  `admin.advertising.pricing.manage` (nouvelle).

## 8. Limites restantes

- Aucune audience par intérêt/usage déclaré/projet déclaré/audience enregistrée — nécessite
  SmartProfile et les consentements (P008).
- Coefficients de prix pour durée/territoire/rareté/volume neutres (1,0) — aucune valeur réelle
  dans le corpus (décision §3.2 du chantier).
- Aucune libération de réservation (pause/annulation avant approbation) — P007+.
- Aucun événement outbox métier dédié aux transitions de campagne (contrairement à
  `subscription_events` en P004) — non demandé explicitement par le sous-ensemble de spécification
  couvert ici.
- Campagne avancée, équipes, modèles de campagne, duplication — Phase 8 (`docs/13` §106), après
  le Feed.

## 9. État Git

`php artisan test` : 142/142. `pint --test` : vert. Frontend : format/lint/types/build verts.
Migration round-trip vérifié. Prêt pour commit, push et PR.

## 10. Chantier suivant recommandé

P007 — Revue administrative et activation de campagne.
