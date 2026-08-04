# P006 — CAMPAGNE, AUDIENCE, DEVIS ET BUDGET

**Statut :** `in_progress`  
**Branche :** `codex/p006-campaign-quote-budget`  
**Commit de base :** `370dc7dcfcc439c6f08638d131edd43781211c45`  
**Autorisation fondatrice :** 4 août 2026  
**Dépendances :** P004 et P005 déployés

## 1. Objectif

Permettre à un annonceur non technicien de transformer une marque et ses médias en campagne publicitaire :

1. choisir la marque ;
2. sélectionner les contenus ;
3. définir l’objectif et le message ;
4. choisir les classes économiques autorisées ;
5. renseigner le territoire et le calendrier ;
6. obtenir un devis figé et une estimation agrégée ;
7. réserver le budget dans le Wallet puis soumettre la campagne à P007.

## 2. Principes fondateurs

- aucun coefficient technique n’est exposé à l’annonceur ;
- les brouillons sont sauvegardés automatiquement et versionnés ;
- l’estimation P006 est une estimation de planification sans SmartProfile ni donnée personnelle ;
- le devis référence une version précise du catalogue sandbox ;
- la répartition économique est exactement 50 % plateforme et 50 % valeur destinée aux utilisateurs ;
- le financement utilise exclusivement la réservation Wallet P003 ;
- la soumission ne capture pas la réservation et ne déclenche aucune diffusion ;
- P007 reste seul propriétaire de la décision administrative ;
- P008 et P009 restent seuls propriétaires du Matching et de la diffusion.

## 3. Inclus

- module Laravel `Campaign` autonome ;
- assistant Inertia/Vue responsive en sept étapes ;
- liste des campagnes et états visibles ;
- campagnes et versions immuables ;
- rattachement des médias P005 aux versions ;
- audience de planification agrégée ;
- catalogue de prix sandbox versionné ;
- devis figé avec échéance ;
- partage 50/50 en montants entiers ;
- réservation atomique du Wallet annonceur ;
- libération de la réservation lors d’une annulation avant soumission ;
- soumission pour revue administrative ;
- capacités explicites et commande `campaign:bootstrap` ;
- tests SQLite et PostgreSQL 17.

## 4. Exclus

- approbation, rejet et demande de correction administrative ;
- activation ou programmation de la campagne ;
- Matching basé sur SmartProfile ;
- segmentation personnelle ou réidentification ;
- livraison dans le Feed ;
- validation de l’attention ;
- capture du budget et crédit utilisateur ;
- prix commerciaux définitifs ou paiement live ;
- carte géographique avancée et géocodage externe.

## 5. Données détenues par P006

- `campaign_price_catalogs` ;
- `campaigns` ;
- `campaign_versions` ;
- `campaign_creatives` ;
- `campaign_audiences` ;
- `campaign_quotes` ;
- `campaign_fundings` ;
- `campaign_budget_reservations`.

P006 référence les marques et médias P005 ainsi que les Wallets et réservations P003, sans devenir propriétaire de leurs tables.

## 6. États

### Campagne

```text
draft → quoted → funded → submitted
   └──────────────→ cancelled
```

### Devis

```text
issued → funded
   └──→ void
```

### Financement

```text
reserved → submitted
    └────→ released
```

## 7. Capacités

- `advertiser.campaign.view` ;
- `advertiser.campaign.create` ;
- `advertiser.campaign.manage` ;
- `advertiser.campaign.quote` ;
- `advertiser.campaign.fund` ;
- `advertiser.campaign.submit`.

Les capacités Wallet existantes demeurent obligatoires pour consulter et financer.

## 8. Sécurité financière

- montants entiers positifs et devise explicite ;
- devis immuable après émission ;
- clé d’idempotence déterministe par devis ;
- réservation sous verrou Wallet P003 ;
- solde disponible jamais négatif ;
- aucune mutation directe de projection ;
- annulation compensée par libération P003 ;
- réservation conservée après soumission jusqu’à P007 ;
- aucune capture dans P006.

## 9. Estimation de planification

P006 ne dispose volontairement pas du SmartProfile P008. L’estimation :

- utilise le territoire, le rayon et le nombre de classes sélectionnées ;
- s’appuie sur une base sandbox configurable ;
- produit une fourchette minimale et maximale ;
- porte explicitement `personal_data_used=false` et `smartprofile_used=false` ;
- ne doit jamais être présentée comme une audience réelle ou garantie.

## 10. Acceptation

Le chantier est acceptable lorsque :

1. GamaDeals crée une campagne Autodesk ;
2. une image ou vidéo P005 est sélectionnée ;
3. Abidjan et un rayon sont renseignés ;
4. les quatre classes peuvent être choisies sans coefficient ;
5. un devis de campagne est figé avec partage 50/50 ;
6. le Wallet annonceur réserve exactement le montant du devis ;
7. un solde insuffisant bloque le financement ;
8. l’annulation libère la réservation ;
9. la soumission conserve la réservation et prépare P007 ;
10. aucune donnée d’un autre annonceur n’est accessible ;
11. Pint, Larastan, Pest SQLite/PostgreSQL, Prettier, ESLint, TypeScript et Vite sont verts.

## 11. Déploiement prévu

- sauvegarde PostgreSQL ;
- récupération de `main` après fusion ;
- installation Composer sous PHP 8.4 ;
- npm et build Vite sous Node.js 24 ;
- `php8.4 artisan migrate --force` ;
- `php8.4 artisan campaign:bootstrap` ;
- reconstruction des caches et redémarrage des workers ;
- recette manuelle GamaDeals : brouillon, devis, financement et soumission.
