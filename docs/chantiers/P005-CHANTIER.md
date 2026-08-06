# P005 — Studio Annonceur : marques, bibliothèque créative et financement

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `0b0d1f6` (P004 fusionné)
**Dépendances déclarées (roadmap) :** P001 (comptes/espaces/organisations), P003 (Wallet annonceur), P004 (classes/plans)
**Spécification :** `docs/13-studio-annonceur-wasplex.md` (109 sections — chantier volontairement borné, voir §3)
**Statut :** proposed

Ce chantier remplace l'ancienne version pré-réinitialisation (branche
`codex/p005-advertiser-studio-brand-wallet`, déployée avant le 2026-08-05, conservée dans
l'historique Git à titre d'audit mais ne décrivant plus l'état réel du dépôt reconstruit).

---

## 1. Objectif

Livrer les phases 1 à 3 de `docs/13` §106 (Espace annonceur, Marques, Bibliothèque créative) plus
l'extension "financement" de son titre : le dépôt supervisé (§28). Le Wallet annonceur lui-même
(phase 4) existe déjà intégralement depuis P003 et n'est pas reconstruit.

Ce chantier ne construit **aucune campagne** (P006), aucune revue administrative de campagne
(P007), aucun Feed (P008/P009+), aucun Live (P018). `docs/13` couvre ces sujets dans le même
fichier ; ce chantier s'arrête délibérément avant.

## 2. Périmètre inclus

- **Profil annonceur** (`advertiser_profiles`) : sous-type (`individual`/`business`/`agency`/
  `institutional_advertiser`, §9), identité légale, cycle de vérification
  (`draft`→`pending_verification`→`verified`→`active`→`restricted`/`suspended`/`closed`, §11).
- **Marques** : `brands` (champs exacts §84), `brand_colors` (§15), `brand_typographies` (§16),
  `brand_guidelines` (charte §14 : ton, mentions obligatoires/interdites, règles d'usage).
- **Bibliothèque créative** : `creative_assets` (métadonnées exactes §19, statuts §20), modération
  (`creative_moderation_cases`).
- **Dépôt supervisé** (§28) : un administrateur peut créer et approuver un dépôt Wallet annonceur
  sans passer par GeniusPay (preuve, référence, motif, idempotence, Grand Livre, audit) — réutilise
  le cycle de vie déjà existant d'`advertiser_wallet_deposits` (P003), pas une nouvelle table.
- API utilisateur et admin correspondantes (§88, §93, sous-ensemble pertinent).
- Tableau de bord Studio simplifié (§12) : solde Wallet, marques, statut de vérification — sans les
  métriques de campagne qui n'existent pas encore.
- UI : gestion des marques + charte, bibliothèque, dépôt supervisé côté admin, intégrées dans
  `AdvertiserShell`/`AdminShell` existants.

## 3. Décisions explicites de réduction de périmètre

`docs/13` §83 liste un modèle de données bien plus large que ce qu'un premier chantier peut
livrer sérieusement. Chaque réduction ci-dessous est documentée plutôt que silencieuse :

1. **Pas de tables `advertiser_spaces`/`advertiser_organizations` séparées.** Identity (P001)
   possède déjà `organizations` (nom, type, pays, statut) et `user_spaces` (espace annonceur
   rattaché à l'organisation). Dupliquer ces primitives violerait CLAUDE.md §6 (un module ne
   possède qu'une fois chaque donnée). `advertiser_profiles` référence `organization_id` par
   valeur et ne porte que ce qu'Identity ne connaît pas : sous-type commercial et cycle de
   vérification Studio.
2. **Pas de `brand_versions` ni `brand_assets` séparées.** Le logo/les visuels d'une marque sont
   des `creative_assets` référencés par `brand_id` — une table `brand_assets` parallèle
   dupliquerait la bibliothèque. La charte graphique (`brand_guidelines`) est mise à jour en place
   (`updated_at`), pas historisée en V1 : aucun historique de charte n'est demandé explicitement
   par les critères d'acceptation §105.
3. **Pas de `creative_asset_versions` ni `creative_processing_jobs`.** Aucun pipeline de
   traitement asynchrone réel (encodage vidéo, génération de vignette) n'existe dans ce dépôt ; une
   table de jobs sans jobs réels serait un stub. Un nouvel upload crée un nouvel asset. Le statut
   passe directement `uploading`→`ready` (validation technique synchrone, §21, limitée aux
   vérifications faisables sans traitement lourd : format, taille, dimensions déclarées) ou
   `needs_changes`/`rejected` si la validation échoue.
4. **Aucun transfert Wallet personnel → annonceur (§29).** Explicitement documenté comme
   optionnel ("si activée") et aucun Wallet personnel utilisateur n'existe encore avec un solde
   réel (P011). Prématuré et hors périmètre.
5. **Aucune notion d'équipe/agence multi-client (§69-72) dans ce chantier.** Le Studio reste
   mono-organisation pour l'instant (le créateur = seul membre actif, comme en P001/P003).
   Équipes et agences sont un chantier ultérieur si le fondateur le priorise.
6. **Stockage des médias : disque local du disque applicatif (`storage/app/public`), pas S3.**
   `docs/CLAUDE.md` §4 mentionne un stockage S3-compatible dans la stack officielle, mais aucune
   configuration MinIO/S3 fonctionnelle n'existe dans ce bac à sable (limite réseau). Le disque
   `public` de Laravel est utilisé comme implémentation immédiate, derrière la même interface
   `Illuminate\Filesystem` qu'un futur disque S3 — migrer plus tard ne change qu'une ligne de
   configuration, pas le code applicatif.

## 4. Périmètre exclu

- Campagnes, audiences, devis, budgets (P006).
- Revue administrative de campagne (P007).
- Feed, Matching, Live (P008+, P018).
- Équipes et agences multi-client (§69-72).
- Transfert Wallet personnel → annonceur (§29).
- Outils créatifs assistés par IA (§23) — explicitement hors V1 par §108.12.

## 5. Modèle de données (résumé)

```text
advertiser_profiles       organization_id, advertiser_type, legal_name, registration_number,
                           address, representative_account_id, status, verified_at
brands                     advertiser_profile_id, name, legal_name, sector, description, status,
                           country_code, website, primary_logo_asset_id, secondary_logo_asset_id,
                           slogan, verified_at
brand_colors               brand_id, name, hex, rgb, cmyk, usage, priority
brand_typographies         brand_id, role (principale/secondaire/remplacement), family, usages
brand_guidelines           brand_id, tone, custom_instructions, mandatory_mentions,
                           forbidden_mentions, usage_rules
creative_assets            brand_id, type, filename, format, size, width, height, duration,
                           language, rights_status, moderation_status, storage_path, created_by
creative_moderation_cases  creative_asset_id, decision, reason, decided_by, decided_at
```

## 6. Capacités

`advertiser.profile.view`, `advertiser.profile.manage`, `advertiser.brand.view`,
`advertiser.brand.manage`, `advertiser.media.view`, `advertiser.media.upload`,
`advertiser.media.manage` — accordées au créateur de l'organisation, comme
`advertiser.wallet.*` en P003. Admin : `admin.advertisers.manage`, `admin.brands.moderate`,
`admin.advertiser-wallet.supervised-deposit`.

## 7. Tests obligatoires (docs/13 §97-103, sous-ensemble pertinent à ce périmètre)

Activation profil annonceur ; création marque ; charte ; upload et validation technique ;
statuts de modération ; dépôt supervisé (idempotence, approbation, Grand Livre, audit) ; aucune
fuite entre organisations ; confidentialité (aucune donnée sensible exposée) ; responsive de base
(captures mobile/desktop).

## 8. Chantier suivant recommandé

P006 — Campagne, audience, devis et budget.
