# WASPLEX — BASELINE DE STACK

**État :** prescrit mais non implémenté  
**Commit observé :** `d899d61f232342eb859bd6599006354aeec85564`

| Composant | Exigence documentaire | État réel | Décision P000 |
|---|---|---|---|
| Langage backend | PHP | Absent | Fixer une version stable supportée |
| Framework | Laravel | Absent | Initialiser une version stable supportée |
| Base transactionnelle | PostgreSQL | Absente | Configurer local/test/staging/prod |
| Cache | Redis | Absent | Configurer cache, sessions et queues |
| Queues | Laravel Queue + Redis | Absentes | Ajouter conventions et workers |
| Scheduler | Laravel Scheduler | Absent | Ajouter le socle |
| Temps réel | Reverb ou compatible Laravel | Absent | Préparer sans surconstruire |
| CSS | Tailwind CSS | Absent | Initialiser avec les tokens Wasplex |
| Build frontend | Vite | Absent | Initialiser avec le frontend retenu |
| Frontend | À déterminer après audit | Aucun existant | Choix bloquant avant P000 |
| API | REST JSON Laravel | Absente | Poser conventions erreurs/versionnement |
| Stockage | S3 compatible | Absent | Contrat + configuration locale de test |
| Tests backend | Pest ou PHPUnit | Absents | Choisir un seul standard principal |
| Tests navigateur | Outil adapté | Absent | À fixer avec le frontend |
| Serveur | Linux + Nginx + PHP-FPM | Absent | Documenter la cible |
| Observabilité | logs, métriques, traces, health checks | Absente | Socle minimal dans P000 |
| CI | Requise | Absente | Ajouter lint, tests et build |

## Recommandation technique à valider

Pour une application comportant un Feed interactif, des interfaces mobiles riches et plusieurs dashboards complexes, la direction recommandée est :

```text
Laravel
+ Inertia
+ Vue 3
+ TypeScript
+ Tailwind CSS
+ Vite
```

Cette direction permet une interface réactive sans créer une API frontend totalement séparée. L'alternative Blade + Livewire est plus simple au départ, mais moins homogène pour le Feed vidéo et les interfaces riches attendues.

## Structure de monorepo recommandée

```text
apps/
  platform/       application Laravel principale
packages/
  design-system/  tokens et composants partagés si extraction utile
docs/             documentation canonique
infra/            environnement local et déploiement
```

La modularité métier doit rester dans l'application Laravel principale. Aucun microservice n'est requis en V1.

## Versions

Les numéros de versions ne sont pas figés dans cet audit. Ils doivent être vérifiés et verrouillés au début de P000, puis documentés dans le dépôt avec leurs politiques de support.
