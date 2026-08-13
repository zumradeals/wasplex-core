# P014-G — Stabilisation finale du Fonds

## Objectif

P014-G ne crée aucun nouveau métier majeur. Il ferme les risques résiduels de P014-A à P014-F avant de déclarer le module Fonds stable.

## Invariants durcis

- urgence vitale vérifiée avant la file ordinaire ;
- file ordinaire strictement FIFO : la réciprocité reste un critère d’éligibilité et ne donne jamais de priorité ;
- création de seconde vague idempotente tant que la vague courante n’a pas été exécutée ;
- le moteur P014-D ne peut plus reprendre une collecte lorsqu’une vague complémentaire existe ;
- le statut global reste `partially_funded` entre deux vagues, et non `cancelled` ;
- frais Wasplex facturé au plus une fois par participant et par vœu, sur la base d’un frais réellement payé ;
- toute écriture de débit positive Fonds doit référencer une transaction Ledger ;
- aucune collecte ne peut dépasser son objectif global ;
- toute allocation de réserve est sérialisée et reste antérieure au snapshot immuable ;
- l’ancien endpoint P014-E ne peut plus augmenter la réserve après snapshot ;
- une seule réhabilitation active peut exister par adhésion ;
- consommation de réserve et montants payés ne peuvent dépasser leurs autorisations/dus.

## Observabilité

La commande suivante vérifie les invariants critiques en production :

```bash
php8.4 artisan funds:integrity-check
```

Elle échoue si elle détecte une surcollecte, un débit sans Ledger, un double frais payé, une priorité ordinaire non nulle, une surconsommation de réserve ou plusieurs réhabilitations actives pour la même adhésion.

## Validation

P014-G doit passer : Pint, Prettier, ESLint, TypeScript, build Vite, tests Fonds ciblés, suite Laravel complète et `funds:integrity-check` sur une base de test migrée.
