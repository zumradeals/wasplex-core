# P006 — Campagne, audience, devis et budget

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `3726795` (P005 fusionné)
**Dépendances déclarées (roadmap) :** P004 (classes économiques), P005 (marques, bibliothèque, Wallet annonceur)
**Spécifications :** `docs/13-studio-annonceur-wasplex.md` §22, §31-48, §83-105 (phase 5 uniquement, §106) ;
`docs/05-modele-economique-publicitaire-wasplex.md` (partage 50/50, catalogue de prix, devis) ;
`docs/04-moteur-matching-et-distribution-publicitaire-wasplex.md` (audience agrégée, seuil minimal) ;
`docs/17-donnees-permissions-consentements-techniques-wasplex.md` (interdiction Santé/Alertes/Fonds/KYC pour le ciblage)
**Statut :** proposed

Ce chantier remplace l'ancienne version pré-réinitialisation (branche `codex/p006-campaign-quote-budget`,
conservée dans l'historique Git à titre d'audit mais ne décrivant plus l'état réel du dépôt reconstruit).
La structure de données change de nom pour suivre `docs/05` §28 (`advertising_price_*`) plutôt que
la version pré-réinitialisation (`campaign_price_catalogs`), mais l'intention produit est la même.

---

## 1. Objectif

Permettre à un annonceur, même non technicien, de créer une **campagne rapide** financée et
soumise en moins de cinq minutes (`docs/13` §32, critère d'acceptation §105.9), avec un aperçu
fidèle façon Feed vertical (§22) et une estimation d'audience agrégée jamais nominative.

`docs/13` §106 place explicitement la "Campagne avancée" en Phase 8, **après** le Feed (Phase 7).
Ce chantier livre uniquement la Phase 5 : campagne rapide, audience, budget, aperçu, autosave.

## 2. Périmètre inclus

- Assistant en 7 étapes (§32) : Marque → Objectif → Contenu → Audience → Budget → Vérification →
  Soumission, avec autosave (chaque étape persiste immédiatement un brouillon).
- Aperçu publicitaire temps réel (§22) : simulation smartphone/Feed vertical — créatif, titre,
  CTA, marque, gain utilisateur affiché, barre de progression, indicateurs son/sous-titres. Mise à
  jour réactive locale (Vue) au fil de la saisie — ce n'est **pas** un canal temps réel Reverb,
  aucun autre utilisateur n'observe cet aperçu.
- Audience agrégée (§36-39, docs/04 §10-11) : ciblage par territoire (pays du compte) et classes
  économiques, estimation de taille en fourchette anonymisée, seuil minimal de segment.
- Catalogue de prix versionné et administrable (`docs/05` §28) : `advertising_price_catalogs`,
  `advertising_price_versions` — un admin doit publier une version tarifée avant qu'un devis ne
  puisse être produit (même discipline que P004 : aucun prix inventé).
- Devis figé (§42, `docs/05` §13-19) : montant, partage 50/50, répartition par classe normalisée,
  gain unitaire, nombre d'événements estimé, reliquat tracé, expiration.
- Réservation du budget (§43-44, `docs/05` §15) sur le Wallet annonceur existant (P003) : nouveau
  compte Grand Livre `advertiser.budget.reserved`, transfert idempotent depuis
  `advertiser.budget.available`.
- Soumission (§48) : budget réservé → campagne à l'état `submitted`, prête pour la file de revue
  administrative (P007, non construite ici).
- API annonceur (`docs/13` §90-91, sous-ensemble pertinent) et administration minimale du
  catalogue de prix (`docs/05` §30, sous-ensemble prix uniquement — la revue de campagne
  elle-même est hors périmètre, voir §4).

## 3. Décisions explicites de réduction de périmètre et d'interprétation

1. **`campaign_versions` porte les configurations en JSON** (`creative_configuration`,
   `audience_configuration`, `budget_configuration`, `cta_configuration`), exactement les champs
   donnés par `docs/13` §86, plutôt que de créer des tables normalisées séparées
   (`campaign_creatives`, `campaign_audiences`, `campaign_audience_versions`) issues de la liste
   aspirationnelle §83 — la même discipline que la réduction de périmètre P005 §3.2/§3.3 : la
   spécification au niveau champ (§86) fait autorité sur la liste d'entités générale (§83).
2. **Aucun prix inventé.** Aucune formule chiffrée de "prix commercial" (`docs/05` §9 :
   base × format × durée × précision × territoire × rareté × classe × volume) n'existe dans le
   corpus au-delà des coefficients de classe déjà seedés en P004 (1,00/1,15/1,35/1,60). Seuls deux
   axes réels sont implémentés : format (image/vidéo, coefficient admin) et classe ciblée
   (coefficient déjà publié par Subscriptions, jamais dupliqué). Durée, territoire, précision,
   rareté et volume restent neutres (coefficient 1,0) jusqu'à décision du fondateur — documenté
   plutôt que silencieux. Le catalogue est seedé en `draft`, base price à 0 : un admin doit fixer
   le vrai prix et publier avant qu'une campagne puisse être devisée (même discipline que
   Premium/Gold/Platine en P004).
3. **Audience limitée à deux critères réels : territoire (pays du compte) et classe économique.**
   `docs/13` §36 liste aussi intérêt, usage déclaré, projet déclaré, audience enregistrée —
   toutes nécessitent SmartProfile et les consentements (P008, non construit). Explicitement
   interdit par construction : toute clé de configuration d'audience hors
   `territory`/`economic_classes` est rejetée (422) — cela couvre structurellement l'interdiction
   `docs/17` L103 (Santé/Alertes/Fonds/KYC jamais utilisés pour le ciblage publicitaire), puisque
   ces domaines ne peuvent littéralement pas apparaître dans la configuration acceptée.
4. **Estimation d'audience jamais nominative.** Calculée en interne comme un comptage réel de
   comptes actifs (abonnement actif dans les classes ciblées, filtré par pays si spécifié), mais
   restituée uniquement sous forme de fourchette arrondie — jamais un identifiant individuel.
   Nouveaux contrats internes : `Identity\AccountCountryLookupContract` (comptage par pays,
   jamais les identifiants eux-mêmes ne quittent Subscriptions) et
   `Subscriptions\EconomicClassCatalogContract` (classes publiées + estimation agrégée).
5. **Segment trop petit → devis refusé, pas juste avertit.** Seuil configurable
   (`config/campaigns.php`, `minimum_segment_size`), conforme à `docs/04` §11 ("élargi, fusionné,
   retardé, ou refusé" — le refus est une option explicitement listée).
6. **`type` de campagne fixé à `fast` dans ce chantier.** Le champ existe (`docs/13` §85) pour
   rester compatible avec la campagne avancée future (Phase 8), mais seule la valeur `fast` est
   acceptée ici.
7. **Statuts de campagne limités à `draft`/`quoted`/`funded`/`submitted`.** Les statuts
   `approved`/`active`/`paused`/`completed`/`refunded`/... (`docs/05` §22, `docs/13` §53)
   appartiennent à la revue administrative (P007) et au Super Moteur (P009), non construits ici.
8. **Pas de libération automatique de réservation dans ce chantier** (pause/annulation/expiration
   de devis après financement) — `docs/13` §58-60 est hors périmètre P006 selon la roadmap
   (dépend de la revue administrative, P007). Un devis expiré avant financement bloque simplement
   la tentative de financement (422), sans réservation à libérer puisque aucune n'a encore été
   créée.
9. **Aucune donnée sensible dans l'aperçu ou le devis** — le devis et l'estimation d'audience ne
   contiennent que des agrégats, jamais un compte, un email ou un téléphone (`docs/13` §102).

## 4. Périmètre exclu

- Campagne avancée, segments, variantes, horaires, équipes (`docs/13` Phase 8).
- Revue administrative, correction, approbation, suspension (P007).
- Activation Feed, Matching, diffusion réelle, crédit utilisateur, événements qualifiés (P008/P009).
- Pause, annulation, remboursement, reliquat global post-financement (P007+).
- Duplication de campagne, modèles de campagne (§67-68).
- Reporting de campagne (§62-64) — nécessite une diffusion réelle, inexistante.

## 5. Modèle de données

```text
advertising_price_catalogs   code
advertising_price_versions   catalog_id, status, currency, base_price_minor_per_event,
                              image_multiplier, video_multiplier, effective_from, effective_to,
                              published_at
campaigns                    organization_id, brand_id, type, objective_code, status, currency,
                              budget_amount_minor, scheduled_start, scheduled_end, created_by
campaign_versions            campaign_id, version_number, creative_configuration,
                              audience_configuration, budget_configuration, cta_configuration,
                              price_version_id, status
campaign_quotes              campaign_version_id, currency, gross_amount_minor,
                              net_distributable_amount_minor, estimated_events,
                              estimated_reach_min, estimated_reach_max, class_breakdown,
                              expires_at, price_version_id, status
campaign_budget_reservations campaign_id, campaign_quote_id, organization_id, amount_minor,
                              status, idempotency_key, ledger_transaction_id
```

## 6. Contrats internes nouveaux

- `App\Modules\Identity\Application\Contracts\AccountCountryLookupContract` —
  `countInCountry(array $accountIds, string $countryCode): int`.
- `App\Modules\Subscriptions\Application\Contracts\EconomicClassCatalogContract` —
  `listPublished(): array`, `estimateAudience(array $classCodes, ?string $countryCode): AudienceEstimate`.
- `App\Modules\AdvertiserStudio\Application\Contracts\BrandDirectoryContract` —
  `find(string $organizationId, string $brandId): ?BrandSummary`.
- `App\Modules\AdvertiserStudio\Application\Contracts\CreativeAssetDirectoryContract` —
  `find(string $organizationId, string $assetId): ?CreativeAssetSummary`.
- `App\Modules\AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract` —
  `reserve(string $organizationId, string $campaignId, int $amountMinor, string $idempotencyKey): void`
  (poste au Grand Livre via `LedgerPostingContract`, même discipline que `AdvertiserWalletCreditor`).

## 7. Capacités

`advertiser.campaign.view`, `advertiser.campaign.manage` (créer/éditer/devis/financer),
`advertiser.campaign.submit` — accordées au créateur de l'organisation comme les capacités P005.
Admin : `admin.advertising.pricing.manage`.

## 8. Tests obligatoires (`docs/13` §97-99, `docs/05` §32, sous-ensemble pertinent)

Création rapide en moins de cinq minutes (parcours complet testé) ; autosave ; aucune fuite de
marque/asset entre organisations ; audience — clé de ciblage interdite rejetée, segment trop
petit refusé, estimation cohérente avec le nombre réel d'abonnements actifs ; devis — bloqué sans
catalogue publié, partage 50/50 exact, poids de classe normalisés, gain unitaire, reliquat
tracé, expiration ; financement — solde insuffisant refusé, double financement impossible
(idempotence), réservation Grand Livre réelle ; soumission — nécessite un financement actif.

## 9. Chantier suivant recommandé

P007 — Revue administrative et activation de campagne.
