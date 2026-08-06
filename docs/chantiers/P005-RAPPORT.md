# RAPPORT — P005 : Studio Annonceur — marques, bibliothèque créative et financement

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `0b0d1f6` (P004 fusionné)
**Chantier :** `docs/chantiers/P005-CHANTIER.md`
**Spécification :** `docs/13-studio-annonceur-wasplex.md`
**Statut :** ready_for_review

---

## 1. Objectif

Livrer les phases 1 à 3 de `docs/13` §106 (profil annonceur, marques, bibliothèque créative) plus
le dépôt supervisé (§28), en s'appuyant sur les primitives Identity (P001) et Wallet annonceur
(P003) déjà en place plutôt qu'en les dupliquant.

## 2. Réalisé

### 2.1. Profil annonceur sur les primitives Identity existantes

`advertiser_profiles` référence `organization_id` par valeur et ne porte que ce qu'Identity ne
connaît pas encore : sous-type commercial (`individual`/`business`/`agency`/
`institutional_advertiser`) et cycle de vérification Studio
(`draft`→`pending_verification`→`verified`→`active`→`restricted`/`suspended`/`closed`).
`AdvertiserProfileService::getOrCreate()` crée le profil à la volée (draft, sous-type null) sans
qu'aucun formulaire de création dédié ne soit nécessaire — cohérent avec l'activation d'espace déjà
existante en P001/P003.

### 2.2. Marques et charte graphique

`BrandService` couvre création, lecture scopée par organisation (`BrandNotFoundException` plutôt
qu'une fuite d'existence entre organisations), mise à jour, remplacement des couleurs et
typographies (suppression puis recréation — pas d'historique de versions, décision §3.2 du
chantier), mise à jour de la charte (`updateOrCreate`), vérification et rejet avec motif par un
administrateur.

### 2.3. Bibliothèque créative

`CreativeLibraryService::upload()` valide extension et taille contre `config/advertiser_studio.php`
(formats et tailles maximales configurables par type image/vidéo), stocke le fichier sur le disque
`public` (décision §3.6 : pas de S3 configuré dans ce bac à sable, migration transparente plus
tard), lit les dimensions des images via `getimagesize()` sur le chemin absolu. Aucune vérification
de durée/dimensions vidéo (pas de `ffprobe` disponible — limite documentée). Validation
synchrone uniquement : le statut passe directement `uploading`→`ready` ou
`needs_changes`/`rejected`, jamais `processing` (décision §3.3 : aucun pipeline asynchrone réel
n'existe). La modération admin (`CreativeModerationCase`, décisions approve/request_changes/reject)
crée un enregistrement d'audit horodaté par `decided_at` et met à jour le statut de l'asset.

**Bug trouvé et corrigé par les tests** : `CreativeModerationCase` levait
`PDOException: column "created_at" ... does not exist` car la migration ne définit que
`decided_at`, mais le modèle ne neutralisait que `UPDATED_AT`. Corrigé avec
`public $timestamps = false;` — `decided_at` reste l'unique horodatage explicite de cet
enregistrement d'audit append-only.

### 2.4. Dépôt supervisé (docs/13 §28)

`SupervisedDepositService` réutilise le cycle de vie déjà existant d'`advertiser_wallet_deposits`
(P003) avec `provider_code = 'manual_supervised'` — aucune nouvelle table. Règle des deux personnes
appliquée : l'administrateur qui **propose** (`create()`) ne peut jamais être celui qui
**approuve** (`approve()`), même invariant que les corrections du Grand Livre en P002
(`SupervisedDepositSelfApprovalException`). Une décision déjà terminale ne peut être rejouée
(`SupervisedDepositAlreadyDecidedException`, HTTP 409).

**Extraction technique** : la logique de crédit Grand Livre, auparavant privée dans
`GeniusPayWebhookHandler`, a été extraite vers `AdvertiserWalletCreditor` (partagée) pour que
dépôt GeniusPay confirmé et dépôt supervisé approuvé postent des écritures identiques avec le même
schéma de clé d'idempotence (`advertiser-deposit:{id}`) — même discipline que l'extraction
`app/Shared/Payments` en P004.

### 2.5. Déplacement du middleware `EnsureActiveAdvertiserOrganization`

Déplacé de `App\Modules\AdvertiserWallet\Http\Middleware` vers
`App\Modules\Identity\Http\Middleware` : AdvertiserStudio en a besoin autant qu'AdvertiserWallet,
et il n'a pas vocation à vivre dans le namespace d'un module frère. Toutes les références mises à
jour (routes AdvertiserWallet, docblock `EnsureCapability`), zéro référence résiduelle vérifiée.

### 2.6. API et permissions

Routes utilisateur sous `/advertiser` (session + `EnsureActiveAdvertiserOrganization` +
`EnsureCapability` scopée à l'organisation), routes admin sous `/admin` (MFA récente +
capacité dédiée). Capacités créateur ajoutées à `OrganizationRegistrationService` :
`advertiser.profile.manage`, `advertiser.brand.manage`, `advertiser.media.upload`,
`advertiser.media.manage`. Capacités fondateur ajoutées :
`admin.advertisers.manage`, `admin.brands.moderate`, `admin.advertiser-wallet.supervised-deposit`.

### 2.7. UI

- `AdvertiserStudioPanel.vue` (onglet "Marques" d'`AdvertiserShell`, précédemment un
  placeholder) : carte profil (type + raison sociale + statut), liste des marques avec création
  rapide, détail de marque (couleurs avec sélecteur + ajout, bibliothèque créative avec upload de
  fichier).
- `AdminAdvertisersPanel.vue` (nouvel onglet "Annonceurs" d'`AdminShell`) : table des annonceurs
  (vérifier/restreindre), marques à vérifier, médias en attente de modération
  (approuver/demander des changements/rejeter), formulaire de création de dépôt supervisé.
- Captures : voir section 5.

## 3. Décisions explicites de réduction de périmètre

Reprises intégralement de `docs/chantiers/P005-CHANTIER.md` §3 (numérotées 1 à 6) :

1. Pas de tables `advertiser_spaces`/`advertiser_organizations` séparées — Identity les possède
   déjà (`organizations`/`user_spaces`).
2. Pas de `brand_versions`/`brand_assets` séparées — le logo est un `creative_asset` référencé par
   valeur ; la charte est mise à jour en place, non historisée.
3. Pas de `creative_asset_versions`/`creative_processing_jobs` — aucun pipeline asynchrone réel,
   validation technique synchrone uniquement.
4. Pas de transfert Wallet personnel → annonceur (§29, explicitement optionnel, aucun Wallet
   personnel réel n'existe encore — P011).
5. Pas d'équipes/agences multi-client (§69-72) — mono-organisation pour l'instant.
6. Stockage média sur le disque local `public`, pas S3 — aucune configuration MinIO/S3 dans ce bac
   à sable ; migration ultérieure = une ligne de configuration, pas de changement applicatif.

## 4. Tests exécutés

- `php artisan test` (Pest 4) — **122 tests, 779 assertions, aucune régression** (99 avant ce
  chantier + 23 nouveaux : 4 profil annonceur, 4 marques/charte, 5 bibliothèque créative,
  5 API admin, 5 dépôt supervisé).
- Couverture explicite des tests obligatoires `docs/13` §97-103 (sous-ensemble pertinent) :
  activation du profil ; création de marque et rejet si annonceur restreint ; couleurs et
  typographies ; charte ; upload valide avec lecture des dimensions ; rejet de format non
  autorisé ; rejet de taille excessive ; décision de modération admin ; **aucune fuite entre
  organisations** (marque et média) ; dépôt supervisé — création, approbation avec crédit Grand
  Livre réel, refus d'auto-approbation (règle des deux personnes), rejet avec motif, refus de
  rejouer une décision déjà terminale (409).
- `./vendor/bin/pint --test` — vert.
- `npm run format:check` / `lint:check` (`eslint . --fix`, aucun changement) / `types:check`
  (`vue-tsc --noEmit`) / `build` — tous verts.
- `migrate:fresh` → `migrate:rollback --step=7` → `migrate` — aller-retour propre sur les 7
  nouvelles migrations.
- Parcours navigateur (Playwright/Chromium) : inscription → activation organisation annonceur →
  Studio → onglet Marques (profil vide) → renseignement du profil → création de marque → ajout
  d'une couleur ; connexion fondateur → MFA → onglet Annonceurs (table annonceurs, marques à
  vérifier, dépôt supervisé).

## 5. Captures

- Studio Annonceur, onglet Marques, profil vide et aucune marque (`p005-studio-profile-empty.png`).
- Studio Annonceur, marque "GamaDeals" créée, bibliothèque créative visible
  (`p005-studio-brand-created.png`).
- Studio Annonceur, couleur "Orange principal" ajoutée à la charte
  (`p005-studio-brand-colors.png`).
- Administration, onglet Annonceurs : table des organisations, marques à vérifier, formulaire de
  dépôt supervisé (`p005-admin-advertisers.png`).

## 6. Fichiers modifiés/ajoutés

```text
app/Modules/AdvertiserStudio/                                    (nouveau module complet)
  Database/Migrations/ (7 fichiers)
  Infrastructure/Models/ (AdvertiserProfile, Brand, BrandColor, BrandTypography,
                           BrandGuideline, CreativeAsset, CreativeModerationCase)
  Infrastructure/Providers/AdvertiserStudioServiceProvider.php
  Application/Services/ (AdvertiserProfileService, BrandService, CreativeLibraryService,
                          exceptions)
  Http/Controllers/Advertiser/ (ProfileController, BrandsController, AssetsController)
  Http/Controllers/Admin/ (AdvertisersController, BrandsController, CreativeModerationController)
  Http/routes/api.php
config/advertiser_studio.php                                      (nouveau)
app/Modules/Identity/Http/Middleware/EnsureActiveAdvertiserOrganization.php
                                                                    (déplacé depuis AdvertiserWallet)
app/Modules/Identity/Http/Middleware/EnsureCapability.php          (modifié — docblock)
app/Modules/Identity/Application/Services/OrganizationRegistrationService.php
                                                                    (modifié — capacités créateur)
app/Modules/Identity/Console/SeedFounderCommand.php                (modifié — capacités admin)
app/Modules/AdvertiserWallet/Application/Services/AdvertiserWalletCreditor.php
                                                                    (nouveau — extrait)
app/Modules/AdvertiserWallet/Application/Services/SupervisedDepositService.php  (nouveau)
app/Modules/AdvertiserWallet/Application/Services/SupervisedDeposit*Exception.php (nouveau, x2)
app/Modules/AdvertiserWallet/Application/Services/GeniusPayWebhookHandler.php
                                                                    (modifié — réutilise le creditor)
app/Modules/AdvertiserWallet/Http/Controllers/Admin/SupervisedDepositsController.php (nouveau)
app/Modules/AdvertiserWallet/Http/routes/api.php                   (modifié — routes admin)
bootstrap/providers.php                                            (modifié)
resources/js/Components/AdvertiserStudioPanel.vue                  (nouveau)
resources/js/Components/AdminAdvertisersPanel.vue                  (nouveau)
resources/js/Pages/Identity/AdvertiserShell.vue                    (modifié)
resources/js/Pages/Identity/AdminShell.vue                         (modifié)
tests/Feature/AdvertiserStudio/ (4 fichiers, 18 tests)
tests/Feature/AdvertiserWallet/SupervisedDepositTest.php           (nouveau, 5 tests)
docs/chantiers/P005-CHANTIER.md, P005-RAPPORT.md                   (réécrits)
```

## 7. Migrations, API, événements, permissions

- **Migrations** : 7 nouvelles tables (§2 du chantier), aucune modification de tables existantes
  hors le déplacement mécanique du middleware `EnsureActiveAdvertiserOrganization`.
- **API** : voir §2.6 — routes utilisateur `/api/advertiser/{profile,brands,assets}`, routes admin
  `/api/admin/{advertisers,brands,creative-assets,advertiser-wallet/deposits}`.
- **Événements** : aucun événement outbox dédié dans ce chantier — les changements de statut
  (profil, marque, asset, dépôt) sont directement persistés et audités via les tables existantes
  (`account_audit_events` pour les actions admin sensibles, `advertiser_wallet_deposit_events` pour
  le dépôt supervisé qui réutilise le cycle P003).
- **Permissions** : créateur d'organisation — `advertiser.profile.manage`, `advertiser.brand.manage`,
  `advertiser.media.upload`, `advertiser.media.manage` (nouvelles) ; admin —
  `admin.advertisers.manage`, `admin.brands.moderate`, `admin.advertiser-wallet.supervised-deposit`
  (nouvelles).

## 8. Limites restantes

- Aucun historique de version pour la charte graphique ni pour les assets créatifs (décisions §3.2
  et §3.3 du chantier) — un nouvel upload remplace, ne versionne pas.
- Aucune vérification de durée/dimensions vidéo (pas de `ffprobe` dans ce bac à sable) — seule la
  validation format/taille s'applique aux vidéos.
- Stockage média sur disque local `public`, pas S3 (limite réseau du bac à sable, décision §3.6).
- Aucune équipe/agence multi-client — Studio mono-organisation.
- Aucun transfert Wallet personnel → annonceur (§29) — aucun Wallet personnel réel n'existe encore.
- Outils créatifs assistés par IA (§23) explicitement hors V1 (§108.12 de la spécification).

## 9. État Git

`php artisan test` : 122/122. `pint --test` : vert. Frontend : format/lint/types/build verts.
Migration round-trip vérifié. Prêt pour commit, push et PR.

## 10. Chantier suivant recommandé

P006 — Campagne, audience, devis et budget.
