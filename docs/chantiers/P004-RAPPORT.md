# P004 — Configurations économiques, plans et classes

## Référence

- Branche : `codex/p004-economic-configuration`
- Base : `aef19414af3be4f47d5c30cab5fc9600cacbd4de`
- Statut : `in_progress`

## Inclus

- module `EconomicConfiguration` ;
- classes économiques stables `FREE`, `PREMIUM`, `GOLD`, `PLATINUM` ;
- versions brouillon, approuvée, publiée et suspendue ;
- quotas initiaux 120/300/600/900 ;
- poids initiaux 10/20/35/35 ;
- coefficients de ciblage exprimés en points de base ;
- simulation et contrôle du total à 100 % ;
- plans commerciaux versionnés sans inventer de prix ;
- souscriptions et compteurs de quota préparés ;
- API administrative protégée par capacités et MFA ;
- cache invalidé après publication ;
- migrations réversibles et tests initiaux.

## Exclus

- paiement réel d'abonnement ;
- renouvellement automatique ;
- Matching, campagne et Feed ;
- consommation du quota par `AdDelivered`, qui appartient à P009 ;
- activation commerciale des plans payants sans décision du fondateur.

## Rollback

1. mettre l'application en maintenance ;
2. sauvegarder PostgreSQL ;
3. exécuter `php artisan migrate:rollback --step=1` ;
4. retirer le provider si retour complet du code ;
5. vider le cache applicatif ;
6. vérifier P003 et les Wallets.

## Validation attendue

- Pint ;
- Larastan niveau 8 ;
- Pest SQLite ;
- Pest PostgreSQL 17 ;
- rollback des migrations ;
- frontend format, lint, types et build.
