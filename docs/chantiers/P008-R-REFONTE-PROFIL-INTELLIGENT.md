# P008-R — Refonte internationale du Profil intelligent

## Statut

- **Chantier :** P008-R
- **Branche :** `agent/p008r-intelligent-profile-refactor`
- **Marché initial par défaut :** Mali (`ML`)
- **Portée :** internationale dès le noyau
- **État :** implémentation fondatrice en revue
- **Fusion et déploiement :** interdits sans autorisation explicite du fondateur

## 1. Cause racine

Le P008 initial a transformé un scénario télécom de démonstration en structure principale du Profil intelligent. Le profil était limité à quatre questions, à une commune d’Abidjan, à un faux pourcentage de complétude et à des catégories non administrables.

Cette construction contredisait la promesse Wasplex : permettre aux annonceurs de constituer des segments multidimensionnels pertinents dans tous les secteurs autorisés, tout en laissant l’utilisateur contrôler ses informations.

## 2. Décision fondatrice

Le Profil intelligent devient un système international et évolutif de signaux commerciaux autorisés. Il distingue notamment :

- intérêt ;
- habitude ;
- préférence ;
- projet ;
- besoin actuel ;
- situation déclarée ;
- territoire approximatif.

Aucun secteur ne constitue le secteur principal de Wasplex.

## 3. Marché initial

Le Mali est le marché proposé par défaut :

- code : `ML` ;
- devise : `XOF` ;
- fuseau : `Africa/Bamako` ;
- langue initiale : français ;
- langue utilisateur préparée : bambara (`bm`).

La Côte d’Ivoire reste enregistrée pour assurer la compatibilité avec les données P008 existantes. Une taxonomie sans pays explicite est internationale.

## 4. Nouveau référentiel

### Marchés

La table `advertising_markets` porte les pays activés, leur devise, leur fuseau, leur langue par défaut et leurs langues prises en charge.

### Secteurs

La table `advertising_sectors` porte un arbre hiérarchique de secteurs. Les libellés sont séparés dans `advertising_sector_translations` afin de préparer le multilingue.

Le catalogue initial comprend notamment :

- achats et commerce ;
- automobile et mobilité ;
- immobilier et habitat ;
- formation et compétences ;
- technologie ;
- télécommunications ;
- entreprise et entrepreneuriat ;
- alimentation et restauration ;
- mode et beauté ;
- voyage et loisirs ;
- maison et quotidien ;
- services professionnels.

### Taxonomies

Les taxonomies existantes deviennent :

- rattachables à un secteur et à un parent ;
- limitées à certains pays ou internationales ;
- classées par nature de signal ;
- visibles ou invisibles pour l’utilisateur ;
- configurables pour l’assistance IA ;
- associées à une durée de fraîcheur ;
- ordonnables depuis le référentiel.

## 5. Signaux de profil

La table `advertising_profile_signals` introduit un format générique et append-only pour :

- les informations déclarées ;
- les informations confirmées ;
- les activités autorisées observées dans Wasplex ;
- les propositions dérivées par IA ;
- les imports futurs explicitement consentis ;
- les corrections utilisateur.

Chaque signal porte notamment :

- sa source ;
- son statut ;
- sa valeur ;
- son marché ;
- sa confiance ;
- son explication ;
- ses preuves autorisées ;
- sa date de confirmation ;
- sa date d’expiration ;
- le signal qu’il remplace.

## 6. Rôle de l’IA

L’IA peut proposer, classer et expliquer. Elle ne constitue jamais l’autorité de gouvernance.

Une proposition IA :

1. doit concerner une taxonomie autorisée ;
2. doit dépasser le seuil de confiance publié ;
3. doit comporter une explication ;
4. est enregistrée comme `proposed` ;
5. reste exclue du Matching ;
6. ne devient `active` qu’après confirmation de l’utilisateur.

Les catégories sensibles et interdites restent bloquées par des règles déterministes, indépendamment du comportement d’un modèle IA.

## 7. Nouvel écran utilisateur

L’écran P008-R supprime le faux objectif de complétude à 100 %.

Il présente désormais :

- le marché initial ;
- le nombre d’informations actives ;
- les secteurs explorés ;
- les suggestions éventuelles à confirmer ;
- une carte multisectorielle ;
- des questions progressives regroupées par secteur ;
- une distinction visible entre intérêt, habitude, préférence, projet, besoin et situation ;
- un centre d’autorisations simplifié ;
- les catégories toujours interdites.

## 8. Administration du catalogue

La nouvelle page `/administration/publicite/catalogue` permet aux administrateurs autorisés de :

- consulter les marchés ;
- consulter les secteurs et sous-secteurs ;
- créer un secteur ;
- créer une taxonomie ;
- choisir la nature du signal ;
- rédiger la question et ses options ;
- fixer la durée de fraîcheur ;
- limiter une taxonomie à certains pays ;
- interdire ou encadrer l’assistance IA.

L’annonceur ne crée jamais directement une taxonomie de profil.

## 9. Compatibilité P008

Les anciennes réponses P008 ne sont pas détruites.

- les quatre taxonomies historiques restent conservées ;
- elles sont marquées comme exemples hérités ;
- la commune d’Abidjan est limitée à la Côte d’Ivoire ;
- les anciennes réponses continuent d’être lisibles ;
- le moteur peut combiner progressivement réponses historiques et nouveaux signaux.

## 10. Commande d’initialisation

Après migration :

```bash
php8.4 artisan advertising:bootstrap-intelligence
```

Cette commande appelle l’initialisation P008 existante, active le Mali, prépare la Côte d’Ivoire, crée les secteurs et publie un catalogue de départ multisectoriel.

## 11. Variables d’environnement

```dotenv
PROFILE_INTELLIGENCE_DEFAULT_MARKET=ML
PROFILE_INTELLIGENCE_DEFAULT_LOCALE=fr
PROFILE_INTELLIGENCE_AI_ENABLED=false
PROFILE_INTELLIGENCE_AI_MINIMUM_CONFIDENCE=0.75
```

L’IA reste désactivée par défaut jusqu’au choix d’un fournisseur, à la mise en place de l’audit et à la validation de la gouvernance.

## 12. Validation attendue

Avant toute fusion :

- Pint ;
- Larastan niveau 8 ;
- tests SQLite ;
- tests PostgreSQL 17 ;
- migrations et rollback PostgreSQL ;
- Prettier ;
- ESLint ;
- TypeScript ;
- build Vite ;
- recette visuelle utilisateur ;
- recette visuelle administration ;
- validation fondatrice du catalogue multisectoriel.

## 13. Phases suivantes

Le présent chantier pose le socle et la première expérience. Les phases suivantes devront ajouter :

1. édition et versionnement complet des secteurs et taxonomies ;
2. traductions administrables ;
3. assistant IA conversationnel utilisateur ;
4. validation et contestation des propositions IA dans l’interface ;
5. constructeur de segments annonceur en langage naturel ;
6. combinaisons avancées ET, OU et exclusions ;
7. score de pertinence explicable après éligibilité déterministe ;
8. mesure de qualité et de fraîcheur du catalogue ;
9. activation progressive d’autres marchés.

## 14. Principe non négociable

> Les règles contrôlent. L’IA assiste, comprend, classe, suggère et explique. L’utilisateur et les administrateurs autorisés gardent la décision finale.
