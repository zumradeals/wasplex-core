# P005 — STUDIO ANNONCEUR, MARQUES ET FINANCEMENT

**Statut :** `in_progress`  
**Branche :** `codex/p005-advertiser-studio-brand-wallet`  
**Commit de base :** `38c8593f68fc3f05f02be768f021bb4b8fd2bebe`  
**Autorisation fondatrice :** 3 août 2026  
**Dépendances :** P001, P003 et P004 déployés

## 1. Objectif

Donner à un annonceur non technicien un Studio cohérent, responsive et réellement exploitable pour :

1. compléter son identité professionnelle ;
2. créer et versionner une ou plusieurs marques ;
3. importer des images et vidéos dans une médiathèque isolée ;
4. associer les médias aux marques ;
5. consulter et financer le Wallet annonceur livré par P003 ;
6. arriver à P006 avec une base annonceur prête, sans créer de campagne prématurément.

## 2. Inclus

- module Laravel `AdvertiserStudio` autonome ;
- profil annonceur séparé du profil personnel ;
- mise à jour du nom affiché de l’espace et de l’organisation ;
- marques isolées par espace annonceur ;
- versions publiées immuables de l’identité de marque ;
- nom, slogan, description, deux couleurs et logo ;
- médiathèque image/vidéo avec stockage local public ou S3 compatible ;
- empreinte SHA-256, type MIME, taille et dimensions image lorsque disponibles ;
- métadonnées média versionnées ;
- association média-marque et protection d’un logo actif ;
- dashboard Studio avec état de préparation ;
- intégration du Wallet P003 sans dupliquer sa logique financière ;
- capacités explicites, commande de rattrapage et isolation organisationnelle ;
- vues desktop et mobile ;
- tests SQLite et PostgreSQL 17.

## 3. Exclus

- campagne, objectif, audience ou territoire ;
- estimation de segment ;
- catalogue de prix et devis ;
- réservation de budget de campagne ;
- revue administrative publicitaire ;
- traitement vidéo avancé, transcodage ou modération automatique ;
- paiement réel hors GeniusPay sandbox déjà délimité par P003 ;
- partage 50/50 et crédit utilisateur.

Ces éléments appartiennent à P006 et aux chantiers suivants.

## 4. Données détenues par P005

- `advertiser_profiles` ;
- `brands` ;
- `brand_versions` ;
- `creative_assets` ;
- `creative_asset_versions` ;
- `brand_assets`.

P005 ne devient pas propriétaire des tables Wallet, Ledger, comptes, organisations ou capacités.

## 5. Capacités

- `advertiser.profile.view` ;
- `advertiser.profile.manage` ;
- `advertiser.brand.view` ;
- `advertiser.brand.manage` ;
- `advertiser.media.view` ;
- `advertiser.media.upload` ;
- `advertiser.media.manage` ;
- capacités Wallet P003 conservées.

La commande `studio:bootstrap` initialise les profils et capacités des espaces annonceurs déjà présents avant P005.

## 6. Règles de sécurité

- toutes les lectures et écritures sont limitées à l’espace annonceur actif ;
- un identifiant de marque ou de média d’un autre espace retourne 404 ou une erreur de validation ;
- un logo actif ne peut pas être archivé avant son remplacement ;
- les fichiers sont validés par MIME et taille ;
- le chemin de stockage contient l’ULID de l’espace ;
- aucune URL ou donnée d’une autre organisation n’est exposée ;
- aucune écriture financière nouvelle n’est implémentée dans P005.

## 7. Acceptation

Le chantier est acceptable lorsque le scénario suivant fonctionne :

1. un compte active son Studio Annonceur ;
2. il complète le profil de son organisation ;
3. il importe une image et une vidéo ;
4. il crée la marque GamaDeals avec logo, slogan et couleurs ;
5. il modifie la marque et une seconde version est conservée ;
6. il ouvre son Wallet annonceur et voit le budget disponible ;
7. aucune marque ni média d’un autre annonceur n’est accessible ;
8. Pint, Larastan, Pest SQLite/PostgreSQL, Prettier, ESLint, TypeScript et Vite sont verts.

## 8. Déploiement prévu

- sauvegarde PostgreSQL ;
- récupération de `main` ;
- installation Composer et npm ;
- build Vite ;
- `php8.4 artisan migrate --force` ;
- `php8.4 artisan studio:bootstrap` ;
- reconstruction des caches ;
- contrôle visuel desktop/mobile.
