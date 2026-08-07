# RAPPORT — P013 : Stabilisation et démonstration de la première verticale

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `5e12f4b` (P012 fusionné)
**Chantier :** `docs/chantiers/P013-CHANTIER.md`
**Guide opérateur :** `docs/chantiers/P013-GUIDE-OPERATEUR.md`
**Spécifications :** `docs/IMPLEMENTATION-ROADMAP-WASPLEX.md` §P013 (lignes 439-454)
**Statut :** ready_for_review

## 1. Objectif

Prouver, en conditions réelles (serveur + Vite + Reverb, navigateur réel — pas seulement la suite
Pest), la première verticale complète avant d'ouvrir Fonds/Alertes/Santé/Carte/Live (Phase E) :

```text
compte Gold à Cocody → annonceur GamaDeals → dépôt 100 000 FCFA → vidéo → devis → réservation
→ approbation → Matching → Feed → attention → partage 50/50 → Wallet → notification → reporting → audit
```

## 2. Méthode suivie

1. **Audit préalable** de tous les rapports P000-P012 pour consolider les limites déjà documentées
   et acceptées (pas de retest de ce qui est déjà connu — §3 du chantier).
2. **Jeu de données réaliste** (`docs/chantiers/p013-demo/p013-seed.php`) construit en appelant les
   vrais services applicatifs de chaque module (jamais un raccourci écrivant directement dans une
   table d'un autre domaine, `CLAUDE.md` §6) :
   - compte annonceur « GamaDeals CI » via `OrganizationRegistrationService` ;
   - créatif vidéo réellement téléversé puis réellement modéré par un administrateur
     (`CreativeLibraryService::upload` + `::moderate`) — P010-B avait court-circuité cette étape en
     créant l'asset directement à l'état `approved` ;
   - dépôt de 100 000 WP via le flux **dépôt supervisé** (`docs/13` §28), règle des deux personnes
     respectée (proposeur ≠ approbateur) ;
   - campagne créée/configurée (audience Gold + territoire CI)/devisée/financée/soumise/approuvée
     via les vrais services `CampaignService`/`CampaignReviewService` ;
   - candidat Gold déclarant la taxonomie SmartProfile `usage.reseau_orange` et le consentement
     `advertising_personalization`.
3. **Rejeu du parcours candidat** (`docs/chantiers/p013-demo/p013-replay.sh`) : réservation Feed
   réelle → attente serveur de la durée requise → heartbeat → complétion → crédit Wallet réel.
4. **Vérification du reporting et du rapprochement** (`docs/chantiers/p013-demo/p013-reporting.sh`) :
   rapport Studio annonceur, dashboard fondateur, exécution d'un rapprochement GeniusPay réel.
5. **Durcissement ciblé** : sécurité (CSRF/capacité/MFA/injection), reprise
   (`feed:release-expired-deliveries`), réseau faible (throttling Playwright), accessibilité de base
   (ARIA) — sur les points d'entrée de la verticale uniquement, sans réécrire les tests de
   concurrence déjà dédiés de P002/P003/P009/P010/P011 (suite complète rejouée pour confirmer
   qu'ils passent toujours ensemble).
6. **Captures réelles** de chaque étape (candidat, annonceur, fondateur) + documentation opérateur
   + plan de rollback.

## 3. Ce qui a été vérifié (audit §1) — aucune redécouverte

Consolidation des « Limites restantes » de P000 à P012 : absence de KYC (bloque P011-C, hors
périmètre P013), pas de vraie tarification Gold/Premium/Platine publiée (P004), pas de ciblage
réseau mobile réel malgré l'exemple narratif Orange de `docs/04` §13 (P008 — seuls
`economic_classes` et `territory.country_code` sont de vrais axes de Matching), pas d'audit
métier transverse au-delà de l'audit de sécurité de compte et du Grand Livre (P012), pas de
pipeline analytique générique (P012). Aucun de ces points n'a été retesté : ils restent tels que
documentés dans leurs rapports respectifs.

## 4. Défauts trouvés et corrigés (dans le périmètre de la verticale uniquement)

### 4.1. Rapprochement incluant à tort les dépôts supervisés

Trouvé en préparant le jeu de données, avant même le rejeu E2E : `AdvertiserWalletReconcilablePaymentDirectory::pending()`
(P011-B) sélectionnait tous les dépôts, y compris ceux à `provider_code = 'manual_supervised'`. Un
rapprochement aurait alors appelé `PaymentProviderContract::fetchPaymentStatus()` pour interroger
GeniusPay sur une référence qu'il n'a jamais émise (un dépôt supervisé ne passe jamais par
GeniusPay). **Corrigé** : filtre `where('provider_code', '!=', 'manual_supervised')`. Nouveau test
`it('never submits a manual supervised deposit for GeniusPay reconciliation')`. Revérifié en
conditions réelles : le rapprochement du jeu de données P013 (qui contient précisément un dépôt
supervisé) renvoie `total_checked: 0` — comportement attendu, capturé en §6.

### 4.2. `APP_URL` non alignée avec l'adresse réelle du serveur de démonstration

Trouvé pendant le test de réseau faible (throttling Playwright) : avec `APP_URL=http://localhost`
(valeur par défaut Laravel) et `php artisan serve --host=127.0.0.1 --port=8123`, les URLs de médias
créatifs (`Storage::disk('public')->url()`) pointent vers `localhost` (port 80) au lieu de
`127.0.0.1:8123`. Le navigateur échoue à charger la vidéo (`net::ERR_CONNECTION_REFUSED`) — un
échec qui, sous réseau throttlé, ressemble d'abord à un simple ralentissement avant d'être identifié
comme un vrai échec de connexion. **Ce n'est pas un défaut de code** : `APP_URL` doit refléter
l'adresse réellement servie, comme pour toute application Laravel — une configuration
d'environnement, pas un correctif applicatif. Documenté explicitement dans le guide opérateur (§1)
pour qu'il ne soit pas redécouvert à chaque démonstration.

### 4.3. Boutons d'interaction Feed sans intitulé accessible

Les quatre boutons d'action du Feed (aimer, commenter, enregistrer, partager) n'exposaient qu'une
icône et un compteur numérique — un lecteur d'écran n'annonçait que « 0 », sans indiquer l'action.
**Corrigé** : `aria-label` explicite sur chacun (dynamique pour aimer/enregistrer, reflétant l'état
via `aria-pressed`), `aria-hidden="true"` sur les icônes décoratives pour éviter la double
annonce. Vérifié : les quatre boutons restent fonctionnels (`toggleLike` réellement déclenché,
`aria-pressed` bascule correctement), `npm run types:check`/`format`/`lint`/`build` verts.

Aucun autre défaut trouvé dans le périmètre de la verticale (sécurité, reprise, concurrence).

## 5. Preuve en conditions réelles — chiffres qui se recoupent

Rejeu complet contre `php artisan serve` + `npm run dev` + `php artisan reverb:start` réels,
navigateur Chromium réel :

| Point de contrôle | Valeur observée |
|---|---|
| Livraison Feed complétée (candidat) | 2 livraisons, 100 % attention qualifiée |
| Gain WP distribué (Feed) | 1 350 WP (2 × 675 WP) |
| Solde Wallet candidat (`/api/me/wallet`, capture navigateur) | 1 350 WP — mis à jour **en temps réel** sans rechargement de page (Reverb) |
| Rapport Studio annonceur (`GET /advertiser/campaigns/report`) | budget cible 100 000 WP, réservé 100 000 WP, consommé 1 350 WP, attention 100 % |
| Grand Livre (dashboard fondateur) | 4 transactions, débit total = crédit total = 201 350 WP, `is_balanced: true` |
| Wallet utilisateurs en circulation | 1 350 WP (= Wallet candidat) |
| Wallet annonceurs en circulation | 98 650 WP (= 100 000 − 1 350) |
| Rapprochement GeniusPay | `total_checked: 0` — comportement attendu (le seul paiement du jeu de données est un dépôt supervisé, hors périmètre GeniusPay, cf. §4.1) |
| Reprise (`feed:release-expired-deliveries`) | 0 anomalie — aucune livraison orpheline |
| Piste d'audit (`account_audit_events`) | bascule d'espace annonceur enregistrée (`UserSpaceSwitched`) |

Tous les chiffres se recoupent exactement entre le Feed, le Wallet, le Grand Livre, le reporting
Studio et le dashboard fondateur — aucune divergence.

## 6. Captures

Six captures réelles (candidat, annonceur, fondateur) + deux captures de test réseau faible,
prises via Playwright/Chromium contre les serveurs locaux réels :

1. Feed candidat (vidéo réellement chargée et jouée, gain annoncé).
2. Wallet candidat avant crédit (675 WP, historique du premier gain).
3. Wallet candidat après le second crédit — **mise à jour temps réel sans rechargement** (1 350 WP).
4. Studio annonceur — panneau de performance de campagne (2 livraisons, 100 % attention, 1 350 WP).
5. Dashboard fondateur (Grand Livre équilibré, campagnes, Feed, abonnements).
6. Rapprochement GeniusPay (file vide — comportement attendu, cf. §4.1).
7-8. Feed sous réseau throttlé (« Fast 3G » simulé, 300 ms de latence) — chargement complet en
   ~7,9 s, vidéo intégralement bufferisée (`readyState: 4`), aucune erreur après correctif §4.2.

## 7. Sécurité — points vérifiés

- Requête sans jeton CSRF sur une route authentifiée (`POST /api/feed/sessions`) → `419`.
- Requête admin sans MFA récente (`GET /api/admin/dashboard/summary`) → `401 MFA_REQUIRED`, avant
  même la vérification de capacité (défense en profondeur intentionnelle, `EnsureRecentMfa`).
- Tentative d'injection SQL sur `identifier_value` du login (avec jeton CSRF valide, pour isoler le
  test) → rejet propre `401 INVALID_CREDENTIALS` (requêtes Eloquent paramétrées), aucune corruption.
- Refus de capacité déjà couvert par la suite Pest (`ReconciliationTest`, `ReportingTest`) — non
  rejoué en doublon.

## 8. Migrations

Aucune.

## 9. API

Aucune nouvelle route. Utilisation exclusive des API déjà livrées par P001-P012.

## 10. Événements

Aucun nouvel événement. `WalletBalanceChanged` (P011) revérifié en conditions réelles.

## 11. Permissions

Aucune nouvelle capacité.

## 12. Tests exécutés

- `php artisan test` (Pest 4) — **209 tests, 2568 assertions, aucune régression** (208 hérités de
  P012 + 1 nouveau : `it('never submits a manual supervised deposit for GeniusPay reconciliation')`
  dans `tests/Feature/Reconciliation/ReconciliationTest.php`).
- `./vendor/bin/pint --test` — vert.
- `npm run format` / `lint` / `types:check` / `build` — tous verts.
- Preuve réelle détaillée en §5-7 ci-dessus (parcours complet, sécurité, reprise, réseau faible,
  accessibilité).

## 13. Fichiers modifiés/ajoutés

```text
apps/platform/app/Modules/AdvertiserWallet/Application/Services/AdvertiserWalletReconcilablePaymentDirectory.php (modifié — exclusion des dépôts supervisés du rapprochement)
apps/platform/tests/Feature/Reconciliation/ReconciliationTest.php                          (modifié — 1 test ajouté)
apps/platform/resources/js/Components/FeedPanel.vue                                        (modifié — aria-label/aria-pressed/aria-hidden)
docs/chantiers/P013-CHANTIER.md, P013-RAPPORT.md, P013-GUIDE-OPERATEUR.md                  (nouveaux)
docs/chantiers/p013-demo/p013-seed.php, p013-replay.sh, p013-reporting.sh                  (nouveaux — scripts de démonstration reproductibles)
docs/ROADMAP-INDEX.md                                                                       (modifié)
```

## 14. Limites restantes

- Les limites déjà documentées en P000-P012 restent inchangées (§3) : pas de KYC, pas de vraie
  tarification Gold/Premium/Platine publiée, pas de ciblage réseau mobile réel (narratif
  SmartProfile uniquement), pas d'audit métier transverse, pas de pipeline analytique générique.
- Réductions assumées de ce chantier (`docs/chantiers/P013-CHANTIER.md` §2) : pas de test de charge
  en volume (k6/Gatling), réseau faible testé uniquement via le throttling Playwright, accessibilité
  vérifiée seulement sur les écrans traversés par la verticale (pas un audit WCAG complet).
- Le jeu de données de démonstration utilise le flux **dépôt supervisé** au lieu du flux GeniusPay
  checkout réel, faute d'accès réseau sortant vers `geniuspay.ci` dans cet environnement (même
  contrainte que P010-B/P011-B) — documenté et non un raccourci de code.
- L'abonnement Gold du candidat de démonstration reste seedé directement
  (`UserSubscription::create`), la tarification Gold réelle n'étant pas encore publiée (décision
  produit du fondateur, hors mandat de ce chantier).

## 15. Risques

Aucun risque nouveau identifié. Les trois défauts trouvés (§4) sont corrigés et couverts (test
automatisé pour le rapprochement, correctif de configuration documenté pour `APP_URL`, correctif
d'accessibilité vérifié manuellement).

## 16. Décisions ouvertes

- Faut-il publier une vraie tarification Gold/Premium/Platine (déblocage de l'abonnement payant
  réel) ? Décision produit du fondateur, hors mandat de stabilisation.
- Faut-il un chantier KYC minimal pour débloquer P011-C (retraits) ? Déjà signalé en P011-RAPPORT.

## 17. Commit final proposé

Un commit unique couvrant le correctif de rapprochement + son test, le correctif d'accessibilité
Feed, et toute la documentation/les scripts de démonstration P013.

## 18. État Git

`php artisan test` : 209/209. `pint --test` : vert. Frontend : format/lint/types/build verts.
Répertoire de travail propre après commit. Prêt pour push et PR.

## 19. Chantier suivant recommandé

La verticale est stable et prouvée. Deux chantiers indépendants peuvent démarrer séparément
(`docs/IMPLEMENTATION-ROADMAP-WASPLEX.md` — « P013 → P014/P019/P020 peuvent démarrer séparément ») :
P014 (Fonds) ou P019 (Espaces professionnels/institutionnels) ou P020 (Communication, modération et
risques). P011-C (retraits utilisateur) reste conditionné à un chantier KYC minimal préalable, non
encore lancé.
