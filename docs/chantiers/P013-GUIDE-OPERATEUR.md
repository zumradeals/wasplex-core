# GUIDE OPÉRATEUR — Rejouer la première verticale (P013)

Ce guide décrit comment rejouer, en conditions réelles, le scénario obligatoire de
`docs/chantiers/P013-CHANTIER.md` §1 sur un environnement de démonstration, et comment revenir en
arrière si besoin. Il ne concerne pas la production (aucun déploiement n'existe encore — P021).

## 1. Prérequis

- Base PostgreSQL migrée à jour : `php artisan migrate`.
- Catalogues de base seedés (idempotents, à rejouer sans risque) :

```bash
php artisan identity:seed-founder --identifier=fondateur@wasplex.test --password='<mot-de-passe>'
php artisan ledger:seed-catalog
php artisan subscriptions:seed-catalog
php artisan smartprofile:seed-catalog
php artisan campaigns:seed-price-catalog
php artisan matching:seed-configuration
```

- `.env` : `APP_URL` doit correspondre exactement à l'adresse réellement utilisée pour
  `php artisan serve` (hôte + port). **Défaut trouvé pendant P013** : avec
  `APP_URL=http://localhost` et `php artisan serve --host=127.0.0.1 --port=8123`, les URLs de
  médias créatifs générées par `Storage::disk('public')->url()` pointent vers `localhost` (port 80)
  au lieu de `127.0.0.1:8123` — le navigateur échoue alors à charger la vidéo
  (`net::ERR_CONNECTION_REFUSED`), y compris et surtout sous réseau throttlé où l'échec passe
  facilement pour un simple ralentissement. Ce n'est pas un défaut de code : `APP_URL` doit
  simplement refléter l'adresse publique réelle (comme pour toute application Laravel).
- Trois processus réels, pas seulement la suite Pest (`docs/chantiers/P013-CHANTIER.md` §3.2) :

```bash
php artisan serve --host=127.0.0.1 --port=8123
php artisan reverb:start --host=127.0.0.1 --port=8080
npm run dev -- --host 127.0.0.1
```

## 2. Jeu de données GamaDeals/Orange

Le jeu de données est construit en appelant les vrais services applicatifs (la même couche que
les contrôleurs HTTP), jamais en écrivant directement dans les tables d'un autre module
(`CLAUDE.md` §6). Deux substitutions assumées, imposées par l'absence d'accès réseau sortant de cet
environnement (voir `docs/chantiers/P013-RAPPORT.md` pour le détail) :

- dépôt annonceur via le flux **dépôt supervisé** (`docs/13` §28) au lieu du flux GeniusPay
  checkout ;
- abonnement Gold du candidat seedé directement (`UserSubscription::create`), car seul le plan
  FREE est publié par défaut.

```bash
export P013_CREATIVE_PATH=/chemin/vers/un/court/fichier.mp4   # ou .webm — aucun contenu créatif réel n'est versionné
php artisan tinker --execute="require '`pwd`/docs/chantiers/p013-demo/p013-seed.php';"
```

Le script (`docs/chantiers/p013-demo/p013-seed.php`) :

1. crée deux comptes Gold de remplissage (seuil minimal de segment) ;
2. publie le tarif publicitaire de base s'il est encore en brouillon ;
3. crée un second administrateur (règle des deux personnes du dépôt supervisé) ;
4. enregistre l'organisation annonceur « GamaDeals CI » via `OrganizationRegistrationService` ;
5. crée la marque « GamaDeals », téléverse un créatif vidéo réel puis le fait approuver par un vrai
   administrateur (`CreativeLibraryService::upload` + `::moderate`) ;
6. propose puis approuve un dépôt supervisé de 100 000 WP ;
7. crée, configure (audience Gold + CI, budget 100 000 WP), devise, finance et soumet la
   campagne ;
8. fait approuver la campagne par le fondateur (`CampaignReviewService::approve`) ;
9. crée le compte candidat Gold et déclare la taxonomie `usage.reseau_orange` + le consentement
   `advertising_personalization` (`docs/04` §13 — narratif uniquement, pas un axe de ciblage réel :
   voir limite documentée dans le rapport).

## 3. Rejouer le parcours candidat (Feed → Wallet)

```bash
# Connexion, réservation Feed, attente de la durée requise, heartbeat, complétion.
bash docs/chantiers/p013-demo/p013-replay.sh
```

Chaque appel utilise les vraies routes API (`/api/feed/sessions`, `/api/feed/next`,
`/api/feed/deliveries/{id}/{start,heartbeat,complete}`, `/api/me/wallet`), avec jeton CSRF réel.
Le Wallet candidat est crédité par une vraie transaction Grand Livre (`AttentionService::complete`),
et l'événement `wallet.balance.changed` est diffusé en temps réel via Reverb (observable dans un
navigateur ouvert sur `/app` → onglet Wallet pendant l'exécution du script).

## 4. Vérifier le reporting et le rapprochement

```bash
bash docs/chantiers/p013-demo/p013-reporting.sh
```

Enchaîne : connexion annonceur → bascule d'espace → `GET /advertiser/campaigns/report` ; connexion
fondateur → première vérification MFA (TOTP) → `GET /admin/dashboard/summary` →
`POST /admin/reconciliation/runs`. Tous les chiffres doivent se recouper (budget consommé =
WP distribués Feed = solde Wallet utilisateur ; Grand Livre équilibré débit = crédit).

## 5. Recette de reprise

```bash
php artisan feed:release-expired-deliveries   # réservations expirées, livraisons bloquées, orphelins Grand Livre
```

À exécuter après toute interruption du scénario (navigateur fermé en cours de livraison, etc.).
Idempotent, sans effet si rien n'est bloqué.

## 6. Plan de rollback

Le Grand Livre est *append-only* (`CLAUDE.md` §7) : aucune étape de ce scénario ne peut être «
annulée » en supprimant ou modifiant une écriture. Deux niveaux distincts :

**Environnement de démonstration/développement (ce dépôt aujourd'hui, aucun déploiement réel) :**
revenir en arrière = réinitialiser la base (`php artisan migrate:fresh`) puis rejouer les seeds de
catalogue (§1) et, si besoin, le jeu de données (§2). Aucune donnée réelle n'est en jeu.

**Si ce scénario était rejoué contre un environnement partagé (à anticiper avant P021 — aucun
déploiement n'existe actuellement) :**

- une campagne mal configurée se **suspend** (`admin.campaigns.suspend`, déjà livré en P007), elle
  ne se supprime pas ;
- un dépôt supervisé erroné se **rejette** avant approbation (`SupervisedDepositService::reject`)
  ou, une fois crédité, ne peut être repris que par une **transaction compensatoire** au Grand
  Livre (`CLAUDE.md` §8 — jamais un correctif direct de solde) ;
- un compte de démonstration se **restreint** (`AdvertisersController::restrict`), il ne se
  supprime pas, pour préserver la piste d'audit ;
- aucune action de ce guide ne doit jamais effacer une écriture, falsifier un audit ou rendre une
  action invisible (`CLAUDE.md` §24-25).
