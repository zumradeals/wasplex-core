# WASPLEX — FEUILLE DE ROUTE D'IMPLÉMENTATION

**Version :** 1.0 — proposition issue de l'audit validé  
**Date :** 2026-08-01  
**Statut :** à valider par le fondateur avant P000  
**Dépôt :** `zumradeals/wasplex-core`  
**Branche de base :** `main`  
**Commit de base audité :** `d899d61f232342eb859bd6599006354aeec85564`  

## 1. Décisions fondatrices validées

| Sujet | Décision |
|---|---|
| Backend | PHP + Laravel |
| Architecture | Monorepo, monolithe modulaire |
| Application principale | `apps/platform` |
| Base | PostgreSQL |
| Cache, sessions et queues | Redis |
| Frontend | Inertia + Vue 3 + TypeScript |
| Design | Tailwind CSS + tokens Wasplex |
| Build | Vite |
| Stockage | S3 compatible |
| Temps réel | Laravel Reverb ou solution compatible Laravel |
| Tests backend | Pest au-dessus de PHPUnit |
| Source financière | Grand Livre en double entrée |
| Document maître | `docs/MASTER-WASPLEX.md` canonique |

Les versions exactes seront vérifiées, verrouillées et documentées dans P000. Aucune version expérimentale ne doit être adoptée par défaut.

## 2. Résultat recherché

La première livraison n'est pas une collection d'écrans. C'est une verticale réellement fonctionnelle :

```text
Compte utilisateur
→ espace annonceur
→ marque
→ dépôt annonceur sandbox supervisé
→ campagne et audience
→ devis et budget réservé
→ revue fondateur/admin
→ Matching protégé
→ Feed utilisateur
→ attention qualifiée
→ Grand Livre
→ Wallet utilisateur
→ notification après commit
→ reporting annonceur et fondateur
→ audit
```

## 3. Noyau de Wasplex

Le noyau initial est construit par P000 à P004 :

1. socle Laravel et conventions ;
2. compte universel, espaces, organisations et capacités ;
3. Grand Livre ;
4. Wallet et réservations ;
5. configurations économiques et classes.

Le noyau partagé ne doit pas absorber les règles de publicité, Fonds, Alertes, Santé, Carte ou Live. Ces règles restent dans leurs domaines.

## 4. Phases

| Phase | Chantiers | Résultat |
|---|---|---|
| A — Noyau | P000 à P004 | plateforme exécutable, sécurisée et financièrement correcte |
| B — Annonceur et campagne | P005 à P007 | campagne financée et approuvée |
| C — Distribution et valeur | P008 à P011 | publicité livrée, attention validée, Wallet crédité |
| D — Pilotage et stabilisation | P012 à P013 | dashboards, reporting, audit, verticale démontrable |
| E — Extensions métier | P014 à P020 | Fonds, Alertes, Santé, Carte, Live, espaces, confiance |
| F — Production | P021 | intégrations et préparation opérationnelle |

## 5. Chemin critique

```text
P000 → P001 → P002 → P003 → P004
→ P005 → P006 → P007 → P008 → P009 → P010 → P011
→ P012 → P013
```

P014 à P021 ne doivent pas retarder la preuve de cette première verticale.

## 6. Règles communes à tous les chantiers

Chaque chantier doit :

- partir d'une branche dédiée `codex/pNNN-slug` ou `claude/pNNN-slug` ;
- enregistrer le commit de base ;
- déclarer inclus et exclu ;
- conserver la propriété des données par module ;
- utiliser des capacités explicites ;
- écrire les tests avant de déclarer la fin ;
- fournir captures pour toute UI ;
- fournir migrations réversibles et plan de rollback ;
- vérifier le diff, les secrets et l'état Git ;
- produire `docs/chantiers/PXXX-RAPPORT.md` ;
- ne jamais pousser, merger ou déployer sans instruction explicite du fondateur.

Pour les opérations financières : entier/decimal strict, devise explicite, idempotence, double entrée, compensation, tests de concurrence et aucun crédit côté client.

## 7. Definition of Ready

Un chantier peut commencer lorsque :

- ses notes sources sont identifiées ;
- ses dépendances sont fusionnées ou explicitement disponibles ;
- ses décisions bloquantes sont closes ;
- son périmètre et ses critères sont acceptés ;
- ses données de démonstration sont définies ;
- la branche et le commit de base sont confirmés.

## 8. Definition of Done

Un chantier est terminé lorsque :

- code, migrations, API, permissions et événements sont cohérents ;
- tests unitaires, intégration et sécurité pertinents sont verts ;
- build frontend et analyse statique passent ;
- erreurs, logs et observabilité adaptée existent ;
- documentation et rapport sont à jour ;
- captures mobile/desktop sont fournies si UI ;
- rollback est documenté ;
- aucun changement hors périmètre n'est présent.

---

# PHASE A — NOYAU

## P000 — Socle du dépôt et stack

**Objectif :** rendre le monorepo reproductible, exécutable et testable sans créer de règles métier.

**Sources :** notes 00, 18, 19, 20, 21 et document maître.  
**Dépendances :** aucune.  
**Branche proposée :** `codex/p000-platform-foundation`.

**Inclus :**

- arborescence `apps/platform`, `docs`, `infra` ;
- Laravel, Inertia, Vue 3, TypeScript, Tailwind, Vite ;
- PostgreSQL et Redis configurés par environnement ;
- Pest, analyse statique, formatage contrôlé et build frontend ;
- structure modulaire Laravel et providers de modules ;
- conventions UUID/ULID, temps UTC, erreurs JSON, `trace_id` ;
- health endpoint, logs structurés, configuration cache/queue/session ;
- CI minimale ;
- `.env.example`, aucune valeur secrète ;
- tokens visuels fondamentaux et page technique de vérification ;
- intégration des documents validés : master, roadmap et index.

**Exclus :** authentification fonctionnelle, tables métier, Ledger, Wallet, campagne, dashboards métier.

**Migrations/API/événements :** aucune migration métier ; `GET /up` ou endpoint de santé équivalent ; aucun événement métier.

**Tests et preuves :** installation propre, démarrage local, connexion PostgreSQL/Redis, test Pest témoin, build Vite, TypeScript, lint/analyse statique, health check, absence de secret, capture de la page technique.

**Acceptation :** un développeur peut cloner, configurer, migrer, tester et construire le frontend avec les commandes documentées ; CI verte ; modules vides enregistrés sans dépendances circulaires.

**Rollback :** suppression de la branche P000 ; aucune donnée métier à restaurer.

## P001 — Compte universel, espaces, capacités et shells

**Objectif :** établir l'identité unique et les contextes utilisateur, annonceur et administration dès le début.

**Sources :** notes 09, 12, 14, 16 et 17.  
**Dépendance :** P000.  
**Branche :** `codex/p001-identity-spaces-capabilities`.

**Inclus :** comptes, identifiants, sessions, appareils, profil minimal, espaces, organisations, memberships, invitations, capacités accordées/expirables/révocables, audit d'accès, authentification et MFA obligatoire pour l'administration ; shells responsive utilisateur, Studio Annonceur et console fondateur avec navigation vide mais réelle.

**Données :** `accounts`, `account_identifiers`, `account_sessions`, `account_devices`, `personal_profiles`, `user_spaces`, `space_memberships`, `organizations`, `organization_memberships`, `organization_invitations`, `capability_grants`, événements d'audit associés.

**API :** `/api/me`, `/api/me/spaces`, switch d'espace, sécurité/sessions ; organisations et invitations ; administration minimale des comptes, organisations et capacités.

**Événements :** `AccountCreated`, `UserSpaceCreated`, `UserSpaceSwitched`, `OrganizationCreated`, `OrganizationMemberInvited`, `CapabilityGranted`, `CapabilityRevoked`.

**Capacités minimales :** `admin.dashboard.view`, `admin.capabilities.grant`, `admin.capabilities.revoke`, capacités de gestion de son propre compte et de son espace annonceur.

**Exclus :** SmartProfile complet, KYC complet, Ledger, Wallet, logique campagne.

**Tests/preuves :** séparation des espaces, refus sans capacité, capacité expirée, MFA admin, session révoquée, isolation d'organisation, captures des trois shells mobile/desktop selon leur doctrine.

**Acceptation :** un même compte ouvre son espace utilisateur et active un espace annonceur ; le fondateur entre dans une console distincte avec MFA ; aucune capacité globale implicite.

## P002 — Grand Livre minimal

**Objectif :** créer la source de vérité financière avant tout Wallet ou budget publicitaire.

**Sources :** notes 05, 06, 07, 12, 16, 18.  
**Dépendance :** P001.  
**Branche :** `codex/p002-ledger-core`.

**Inclus :** comptes comptables, journaux, transactions, écritures, liens, clé d'idempotence, double entrée, devise, métadonnées minimales, immutabilité logique, compensation, service de posting, contrôle d'équilibre, audit et contrat interne.

**Données :** `ledger_accounts`, `ledger_account_types`, `ledger_transactions`, `ledger_entries`, `ledger_transaction_links`, `ledger_idempotency_keys`.

**API :** contrat/application interne pour poster, consulter et compenser ; consultation admin en lecture seule. Les clients ne postent jamais directement.

**Événements :** `LedgerTransactionPosted`, `LedgerTransactionReversed`, `LedgerImbalanceDetected`.

**Capacités :** `wallet.ledger.view`, `wallet.correction.propose`, `wallet.correction.approve`, `wallet.audit.view`.

**Exclus :** dépôts réels, retraits, Wallet UI, réservations publicitaires.

**Tests :** équilibre, devise, idempotence, doublon, compensation, immutabilité, deux workers, concurrence, rollback transactionnel, somme débit = crédit.

**Acceptation :** aucune transaction déséquilibrée n'est commise ; un retry ne duplique pas ; toute correction crée une nouvelle transaction.

## P003 — Wallet, projections et réservations

**Objectif :** projeter le Ledger dans des Wallets utilisateur/annonceur et réserver de la valeur sans mutation directe de solde.

**Sources :** notes 05, 06, 07, 13 et 18.  
**Dépendance :** P002.  
**Branche :** `codex/p003-wallet-reservations`.

**Inclus :** Wallets typés, balances projetées, historique, reconstruction, réservations, capture, release, expiration, opération de dépôt sandbox supervisée, vue utilisateur, vue annonceur minimale et supervision admin.

**Données :** `wallets`, `wallet_balance_projections`, `wallet_operations`, `wallet_reservations`, `wallet_deposits`, `wallet_audit_events`.

**API :** lecture Wallet/historique ; création et consultation d'un dépôt sandbox ; contrats internes reserve/capture/release ; file admin de revue des dépôts.

**Événements :** `WalletCreated`, `WalletBalanceChanged`, `ValueReserved`, `ValueReservationCaptured`, `ValueReservationReleased`, `DepositCreated`, `DepositApproved`, `DepositCredited`, `DepositRejected`.

**Capacités :** `wallet.view.self`, `advertiser.wallet.view`, `advertiser.wallet.fund`, `wallet.deposit.review`, `wallet.deposit.approve`, `wallet.configuration.manage`.

**Exclus :** Mobile Money réel, retrait, transfert, Carte, Fonds.

**Tests :** reconstruction, projection après commit, réservation concurrente, expiration, double capture impossible, double webhook sandbox, disponible jamais négatif, accès par espace.

**Acceptation :** le fondateur approuve un dépôt annonceur sandbox ; le Ledger est écrit ; le Wallet annonceur est projeté ; l'audit explique l'opération.

## P004 — Configurations économiques, plans et classes

**Objectif :** rendre plans, classes, poids, quotas, frais et paramètres administrables et versionnés.

**Sources :** notes 03, 05, 07 et 12.  
**Dépendances :** P001 et P003.  
**Branche :** `codex/p004-economic-configuration`.

**Inclus :** définitions, versions, simulation, approbation, publication, suspension, cache invalidé après commit ; classes initiales Gratuit/Premium/Gold/Platine ; quotas et poids configurables ; souscription minimale et compteurs de quota ; centre de configuration fondateur.

**Données :** `configuration_definitions`, `configuration_versions`, `configuration_scopes`, `configuration_publications`, `subscription_plans`, `subscription_plan_versions`, `economic_classes`, `economic_class_versions`, `user_subscriptions`, `subscription_quota_counters`.

**API :** plans et abonnement courant ; quotas ; administration des configurations, plans et classes ; validation des poids.

**Événements :** `AdminConfigurationPublished`, `SubscriptionActivated`, `SubscriptionExpired`, `AdQuotaConsumed`, `AdQuotaRestored`, `AdQuotaReset`.

**Capacités :** `admin.configuration.view`, `admin.configuration.manage`, permissions plans/classes.

**Exclus :** paiement réel d'abonnement, moteur de Matching et publicité.

**Tests/preuves :** version non publiée sans effet, simulation, publication atomique, cache invalidé, poids valides, quota séparé du gain, captures du centre de configuration.

**Acceptation :** le fondateur modifie un quota dans une nouvelle version, simule, publie et voit l'audit ; la nouvelle valeur s'applique sans déploiement.

---

# PHASE B — ANNONCEUR ET CAMPAGNE

## P005 — Studio Annonceur, marques et financement

**Objectif :** donner à l'annonceur un espace complet et cohérent pour créer une marque et disposer d'un budget.

**Sources :** notes 00, 06, 09 et 13.  
**Dépendances :** P001, P003, P004.  
**Branche :** `codex/p005-advertiser-studio-brand-wallet`.

**Inclus :** dashboard Studio mobile/desktop, activation de l'espace, profil annonceur, marque, logo/couleurs/slogan, bibliothèque média initiale S3 compatible, Wallet annonceur, dépôt sandbox et historique.

**Données :** `advertiser_spaces`, `advertiser_profiles`, `brands`, `brand_versions`, `brand_assets`, `creative_assets`, `creative_asset_versions`.

**API :** dashboard/profil, CRUD marques, upload/lecture assets, Wallet et dépôts annonceur.

**Événements :** `AdvertiserSpaceCreated`, `BrandCreated`, `BrandUpdated`, `CreativeAssetUploaded`, `CreativeAssetProcessed`.

**Capacités :** `advertiser.brand.view/manage`, `advertiser.media.upload`, `advertiser.wallet.view/fund`.

**Tests/preuves :** isolation de marques, validation fichiers, URL signée, responsive complet, parcours GamaDeals et dépôt de 100 000 FCFA sandbox.

**Acceptation :** un non-technicien active le Studio, crée GamaDeals, importe une vidéo verticale et voit son budget disponible.

## P006 — Campagne, audience, devis et budget

**Objectif :** permettre la création rapide d'une campagne financée avant toute soumission.

**Sources :** notes 03, 04, 05, 13 et 17.  
**Dépendances :** P004 et P005.  
**Branche :** `codex/p006-campaign-quote-budget`.

**Inclus :** assistant en sept étapes, autosave, contenu, objectif, audience autorisée, classes, territoire, estimation agrégée, catalogue de prix versionné, devis figé, partage 50/50, réservation du budget, aperçu et soumission.

**Données :** `campaigns`, `campaign_versions`, `campaign_creatives`, `campaign_audiences`, `campaign_audience_versions`, `campaign_quotes`, `campaign_fundings`, `campaign_budget_reservations`, catalogues/prix/enveloppes nécessaires.

**API :** CRUD campagne, estimate audience, quote, fund, submit, budget ; taxonomies et audiences annonceur.

**Événements :** `CampaignCreated`, `SegmentEstimated`, `CampaignQuoted`, `CampaignFunded`, `CampaignSubmitted`.

**Capacités :** `advertiser.campaign.create`, `advertiser.campaign.submit`, permissions d'audience, de budget et de média.

**Exclus :** approbation, activation Feed, crédit utilisateur.

**Tests :** budget insuffisant, prix expiré, double financement, arrondis 50/50, audience interdite, segment trop petit, identité non exposée, création mobile/desktop en moins de cinq minutes.

**Acceptation :** campagne Orange/Cocody/Gold-Platine devisée et entièrement réservée avant soumission.

## P007 — Revue administrative et activation de campagne

**Objectif :** donner au fondateur/admin une file opérationnelle de revue, correction et décision.

**Sources :** notes 12, 13, 16 et 18.  
**Dépendance :** P006.  
**Branche :** `codex/p007-campaign-admin-review`.

**Inclus :** file de revue, détails média/audience/budget, demande de correction, resoumission, approbation, rejet, suspension, historique et audit ; widget dashboard fondateur.

**Données :** `campaign_review_cases`, `campaign_review_events`, `campaign_status_events`, tâches admin minimales.

**API :** listes/détails de revues ; approve/request-changes/reject ; suspend campagne.

**Événements :** `CampaignChangesRequested`, `CampaignApproved`, `CampaignRejected`, `CampaignSuspended`.

**Capacités :** capacités de revue et suspension publicitaire ; aucune autorité dérivée du seul rôle.

**Tests/preuves :** séparation demandeur/décideur si configurée, refus sans capacité, média rejeté, resoumission, réservation conservée/libérée selon décision, captures console.

**Acceptation :** une campagne approuvée devient éligible à la distribution ; une campagne rejetée ne peut jamais entrer dans le Feed.

---

# PHASE C — DISTRIBUTION ET VALEUR

## P008 — SmartProfile, consentements et Matching minimal

**Objectif :** sélectionner des utilisateurs éligibles sans révéler leur identité ni utiliser de données sensibles.

**Sources :** notes 04, 09, 16 et 17.  
**Dépendances :** P004 et P007.  
**Branche :** `codex/p008-smart-profile-matching`.

**Inclus :** questions/réponses volontaires minimales, finalité publicitaire versionnée, consentement/retrait, faits avec provenance, segment, estimation, éligibilité, fréquence, fatigue, explication « Pourquoi cette publicité ? ».

**Données :** sous-ensemble SmartProfile/consentements ; `advertising_segments`, règles, estimations, matches, compteurs et audits.

**API :** profil publicitaire et consentements ; taxonomies/estimations annonceur ; contrat interne d'éligibilité et endpoint d'explication.

**Événements :** `AdvertisingProfileUpdated`, `AdvertisingConsentGranted/Withdrawn`, `CampaignMatched`.

**Tests :** retrait de consentement, données Santé/Alertes/Fonds/KYC interdites, anonymat annonceur, changement de réponse, fréquence, explication, isolation pays/classe.

**Acceptation :** un utilisateur Gold à Cocody est éligible à la campagne et peut comprendre pourquoi, sans que l'annonceur reçoive son identité.

## P009 — Feed utilisateur et livraison publicitaire

**Objectif :** livrer réellement une campagne approuvée dans un shell mobile-first fidèle à l'identité Wasplex.

**Sources :** notes 00, 04, 08 et 09.  
**Dépendances :** P005, P007, P008.  
**Branche :** `codex/p009-user-feed-delivery`.

**Inclus :** Feed « Pour toi », session, item abstrait, vidéo verticale, buffer, navigation, livraison, CTA, dismiss, compteur de quota, explication et états réseau faible ; affichage Wallet résumé.

**Données :** `feed_sessions`, `feed_items`, `feed_item_versions`, `feed_delivery_candidates`, `feed_deliveries`, `feed_delivery_events`, compteurs de fréquence/fatigue.

**API :** création session, prochain item, visible/dismiss/complete, détail et explication.

**Événements :** `FeedSessionStarted`, `FeedItemSelected`, `FeedItemDelivered`, `AdDelivered`, `FeedItemDismissed`, `AdQuotaConsumed`.

**Exclus :** commentaires complets, Alertes, Explorer avancé, récompense avant P010-P011.

**Tests/preuves :** campagne non approuvée exclue, quota épuisé, matching absent, double livraison, reprise réseau, responsive mobile et shell desktop conservé, vidéo GamaDeals.

**Acceptation :** l'utilisateur voit une publicité compatible et son quota est consommé sur livraison réelle, indépendamment du gain.

## P010 — Attention qualifiée et moteur de valeur publicitaire

**Objectif :** valider une preuve d'attention et orchestrer quote, tentative, réservation, capture ou release.

**Sources :** notes 04, 05, 07, 08 et 16.  
**Dépendances :** P003, P006 et P009.  
**Branche :** `codex/p010-qualified-attention-value-engine`.

**Inclus :** registre initial limité à `AD_QUALIFIED_ATTENTION`, règles/versionnement, quote, attempt, heartbeat, preuve, validation serveur, réservation, abandon, expiration, capture, release, reprise et revue antifraude minimale.

**Données :** sous-ensemble `value_event_types`, règles/versions, quotes, attempts, events, reservations, proofs, decisions, idempotency, recovery ; sessions/proofs d'attention Feed.

**API :** start, heartbeat, complete, abandon, consultation tentative ; contrats internes reserve/validate/capture/release.

**Événements :** `ValueAttemptCreated`, `AttentionSessionStarted`, `ValueProofValidated/Rejected`, `ValueCaptured`, `ValueReleased`, `ValueManualReviewRequired`.

**Tests :** arrière-plan, heartbeat falsifié, double complete, abandon, expiration, concurrence, preuve insuffisante, règle modifiée en cours, retry, crash entre validation et capture.

**Acceptation :** le gain exact est affiché avant lecture ; aucune complétion client seule ne produit de valeur ; l'abandon libère la réservation.

## P011 — Crédit Wallet, outbox et notification temps réel

**Objectif :** fermer atomiquement le flux économique et mettre à jour l'interface uniquement après commit.

**Sources :** notes 05, 06, 07, 08, 15 et 18.  
**Dépendances :** P002, P003 et P010.  
**Branche :** `codex/p011-wallet-credit-realtime`.

**Inclus :** transaction Ledger du partage 50/50, consommation budget, revenu Wasplex, crédit utilisateur, projection Wallet, outbox/inbox, queue Redis, consumer idempotent, Reverb, toast/animation mascotte, notification et historique.

**Données :** écritures Ledger, projections Wallet, outbox/inbox, notifications minimales et registres de traitement.

**Événements :** `QualifiedEventValidated`, `CampaignBudgetConsumed`, `UserRewardCredited`, `WasplexRevenueRecognized`, `WalletRewardConfirmed`, `NotificationCreated`.

**Tests :** équilibre financier, arrondis, deux consumers, événement rejoué, panne Reverb, panne worker, notification avant commit interdite, projection reconstruite, animation déclenchée une fois.

**Acceptation :** la vidéo qualifiée crédite le Wallet après commit, diminue le budget annonceur, comptabilise la part Wasplex et actualise l'UI sans double effet.

---

# PHASE D — PILOTAGE ET STABILISATION

## P012 — Reporting économique et dashboards opérationnels

**Objectif :** rendre la verticale pilotable par l'utilisateur, l'annonceur et le fondateur.

**Sources :** notes 12, 13 et 18.  
**Dépendances :** P007, P009 et P011.  
**Branche :** `codex/p012-reporting-dashboards`.

**Inclus :** registre analytique versionné, projections/agrégats, audit append-only, dashboard utilisateur simple, campagne annonceur (budget, livraison, attention, gains agrégés), dashboard fondateur (revenus, budgets, Wallet, incidents), export minimal sécurisé, health queues/outbox.

**Données :** définitions/événements analytiques, agrégats, snapshots, dashboards/widgets, rapports, exports, audit, health checks/incidents minimaux.

**API :** reporting utilisateur, Studio, campagne, admin, audit et observabilité minimale.

**Capacités :** reporting self/advertiser/global, audit global, observability view.

**Tests/preuves :** aucune identité annonceur, réconciliation aux écritures Ledger, événements en retard, doublon analytique, export autorisé, mobile/desktop, captures des trois dashboards.

**Acceptation :** un même événement qualifié est visible de manière cohérente dans le Wallet utilisateur, la campagne annonceur et le dashboard fondateur.

## P013 — Stabilisation et démonstration de la première verticale

**Objectif :** prouver la verticale complète avant d'ouvrir les grands modules suivants.

**Sources :** toutes les notes utilisées par P000-P012.  
**Dépendances :** P000 à P012.  
**Branche :** `codex/p013-first-vertical-stabilization`.

**Inclus :** jeu de données GamaDeals/Orange, parcours E2E, sécurité, concurrence, reprise, réseau faible, accessibilité, performance ciblée, captures, documentation opérateur, rollback, rapport de preuve et correction des défauts de la verticale uniquement.

**Scénario obligatoire :** compte Gold à Cocody → annonceur GamaDeals → dépôt 100 000 FCFA → vidéo → devis → réservation → approbation → Matching → Feed → attention → partage 50/50 → Wallet → notification → reporting → audit.

**Exclus :** Fonds, Alertes, Santé, Carte et Live.

**Acceptation :** scénario rejouable sur environnement de démonstration, tests critiques verts, aucune divergence Ledger/Wallet/reporting, captures utilisateur/annonceur/admin, incidents connus documentés.

---

# PHASE E — EXTENSIONS MÉTIER

## P014 — Fonds Wasplex

**Objectif :** implémenter contribution personnelle, débit collectif, prestataires, frais, régularisation et supervision sans rendre le Wallet négatif.

**Dépendances :** P003, P004, P011, P012.  
**Sources :** notes 01, 06, 07, 12, 14, 16 et 18.  
**Preuves :** verticale Fonds complète, Ledger équilibré, UI utilisateur/prestataire/admin, tests de concurrence et compensation.

## P015 — Alertes

**Objectif :** dossier source, projections publique/institutionnelle, routage, priorité vitale, restitution, visibilité renforcée et canaux temporaires.

**Dépendances :** P001, P009, P011, P012, P019.  
**Sources :** notes 02, 08, 12, 14, 15, 16 et 17.  
**Règles :** aucune alerte rémunérée, aucun quota publicitaire consommé, P0 prioritaire et jamais achetable.

## P016 — Santé séparée

**Objectif :** dossier Santé, capsule d'urgence, consentements, représentants, accès temporaire et break-glass audité.

**Dépendances :** P001, P012, P015, P019.  
**Sources :** notes 02, 09, 12, 14, 16, 17 et 18.  
**Règles :** schémas, permissions et journaux séparés ; aucune donnée Santé dans Advertising.

## P017 — Carte Wasplex et partenaires

**Objectif :** carte virtuelle, opérations autorisées, QR/token, partenaires, commissions, cashback et rapprochement.

**Dépendances :** P003, P011, P012, P019.  
**Sources :** notes 06, 07, 10, 14, 16 et 19.  
**Preuves :** aucune valeur stockée dans la carte elle-même, paiements Ledger, tokenisation, révocation et reporting.

## P018 — Live Wasplex

**Objectif :** Live standard puis campagne sponsorisée, places rémunérées, blocs d'attention, réservations progressives, modération et replay.

**Dépendances :** P006 à P012 et P020.  
**Sources :** notes 07, 08, 11, 13, 16, 18 et 19.  
**Ordre interne :** Live standard → financement → places → attention → valeur → interactions → replay.

## P019 — Espaces professionnels et institutionnels

**Objectif :** organisations nominatives, équipes, capacités, mobile terrain et desktop de pilotage pour partenaires, sécurité, Santé et institutions.

**Dépendances :** P001, P012.  
**Sources :** notes 09, 12, 14, 17 et 18.  
**Règles :** aucun compte partagé générique ; séparation par organisation, territoire et finalité.

## P020 — Communication, modération, sécurité et antifraude globales

**Objectif :** notifications complètes, messagerie, canaux temporaires, signalements, dossiers de modération, risques, sanctions ciblées, holds et réexamen.

**Dépendances :** P001, P011, P012.  
**Sources :** notes 15, 16, 17 et 18.  
**Règles :** audit, rétention, chiffrement adapté, aucune sanction financière sans flux Ledger/compensation.

---

# PHASE F — PRODUCTION

## P021 — Intégrations externes et préparation production

**Objectif :** brancher progressivement paiements, SMS/email/push, stockage/CDN, KYC, cartographie et streaming derrière des contrats Wasplex.

**Dépendances :** modules consommateurs stabilisés.  
**Sources :** notes 16, 18, 19 et 20.  

**Inclus :** adaptateurs, webhooks signés, idempotence, retries, circuit breaker, statut inconnu, rapprochement, secrets, sandbox/staging, sauvegardes/restauration, workers supervisés, SLI/SLO, déploiement contrôlé et runbooks.

**Exclus :** couplage direct d'un module métier à un SDK ; migration prématurée en microservices.

**Acceptation :** chaque fournisseur est remplaçable derrière un contrat, les pannes sont observables/récupérables, restauration testée et déploiement réversible.

---

# 9. Travaux parallélisables

Le parallélisme est autorisé seulement lorsque les fichiers et frontières ne se chevauchent pas.

| Après | Parallélisme possible |
|---|---|
| P001 | approfondissement design system et préparation Ledger |
| P004 | UI Studio P005 et contrats campagne P006, sur branches coordonnées |
| P007 | Matching P008 et préparation média Feed P009 |
| P011 | dashboards P012 et durcissement E2E P013 |
| P013 | P014/P019/P020 peuvent démarrer séparément |

Une seule branche possède une migration ou un contrat transversal donné. Toute dépendance interbranche doit être déclarée par commit, pas copiée manuellement.

# 10. Données de démonstration officielles

```text
Utilisateur : compte Gold, Cocody, consentement publicitaire actif
Annonceur : GamaDeals
Campagne : vidéo verticale Autodesk ou offre compatible de démonstration
Budget annonceur : 100 000 FCFA sandbox
Audience : Gold et Platine, Cocody, critère volontaire autorisé
Administrateur : fondateur avec MFA et capacités explicites
Résultat : attention qualifiée, partage 50/50, Wallet et reporting
```

Les valeurs exactes de gain et de prix viennent des configurations publiées, jamais de constantes cachées dans le code.

# 11. Matrice de validation fondateur

Validation obligatoire avant :

- démarrage de P000 ;
- publication de la structure des classes/quotas ;
- première écriture Ledger réelle ;
- première campagne activée ;
- accès Santé ou break-glass ;
- lancement d'une nouvelle source de valeur ;
- merge sur `main` ;
- déploiement staging/production.

# 12. Ordre de reprise entre agents

Chaque passation Claude/Codex doit fournir :

```text
chantier
branche
commit de base
dernier commit
objectif
fait
non fait
tests exécutés
résultats
fichiers ouverts
décisions en attente
prochaine commande sûre
```

Deux agents ne modifient pas simultanément le même chantier sans coordination explicite.

# 13. Prochaine étape après validation

Après validation de cette roadmap :

1. préparer la fiche détaillée de P000 ;
2. vérifier les versions stables supportées ;
3. créer la branche `codex/p000-platform-foundation` ;
4. initialiser `apps/platform` ;
5. exécuter uniquement P000 ;
6. présenter tests, captures, diff et rapport ;
7. attendre l'autorisation de fusion et de P001.

> La vitesse recherchée vient d'un chemin critique court et vérifiable, pas du lancement simultané de tous les modules.
