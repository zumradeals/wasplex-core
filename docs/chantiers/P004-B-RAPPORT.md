# P004-B — Interface fondatrice de configuration économique

## Référence

- Branche : `codex/p004b-economic-admin-ui`
- Dépendance : P004 fusionné et déployé
- Statut : en validation

## Objet

Rendre le noyau économique P004 réellement administrable depuis la console fondatrice, sans contourner les capacités, le MFA ni le versionnement existant.

## Inclus

- entrée `Économie & abonnements` dans la console fondatrice ;
- page Inertia/Vue `/administration/economie` ;
- visualisation des classes `FREE`, `PREMIUM`, `GOLD` et `PLATINUM` ;
- valeurs publiées, quotas, poids, coefficients et éligibilité au fonds ;
- simulation locale et serveur du total à 10 000 points de base ;
- création d’une nouvelle version brouillon ;
- approbation, publication et suspension motivée ;
- historique visible des douze dernières versions par classe ;
- réponses JSON conservées pour les appels techniques ;
- migration corrective alignant les identifiants d’acteur économique sur les ULID des comptes ;
- tests de rendu, de versionnement, d’approbation, de publication, de suspension et de simulation.

## Gouvernance conservée

- aucune modification directe d’une version publiée ;
- aucune activation commerciale ni invention de prix ;
- publication protégée par MFA récent et capacités explicites ;
- suspension avec motif obligatoire ;
- total publié des quatre poids contrôlé à 100 % ;
- identité de l’acteur enregistrée dans les métadonnées de version.

## Hors périmètre

- paiement réel d’abonnement ;
- renouvellement automatique ;
- définition des prix commerciaux ;
- consommation des quotas publicitaires ;
- Matching, campagnes et Feed ;
- interface mobile d’urgence de la console fondatrice.

## Déploiement attendu

1. récupérer `main` après fusion ;
2. exécuter Composer avec PHP 8.4 ;
3. reconstruire les assets Vite ;
4. exécuter `php8.4 artisan migrate --force` ;
5. vider et reconstruire les caches Laravel ;
6. vérifier `/administration/economie` avec le compte fondateur et un MFA récent.

## Validation attendue

- Pint ;
- Larastan niveau 8 ;
- Pest SQLite ;
- Pest PostgreSQL 17 ;
- rollback des migrations ;
- Prettier ;
- ESLint ;
- TypeScript/Vue ;
- build Vite.
