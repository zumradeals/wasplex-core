# P004-C — Configuration fondatrice simplifiée en pourcentages

## Constat

L’interface P004-B exposait directement des notions techniques internes :

- points de base ;
- coefficient de ciblage ;
- création de brouillon ;
- approbation séparée ;
- publication coordonnée séparée.

Ces notions sont nécessaires au noyau économique, mais elles ne doivent pas compliquer la décision du fondateur.

## Décision d’interface

La console fondatrice demande uniquement quatre pourcentages :

- FREE ;
- PREMIUM ;
- GOLD ;
- PLATINUM.

Le total doit être exactement égal à 100 %.

L’utilisateur peut saisir des nombres entiers ou des valeurs décimales comportant jusqu’à deux chiffres utiles dans l’interface. Le backend convertit les valeurs en points de base sans exposer cette représentation.

## Traitement automatique

Lors de l’application d’une nouvelle répartition, Wasplex :

1. valide la présence des quatre classes ;
2. valide chaque valeur entre 0 % et 100 % ;
3. refuse toute somme différente de 100 % ;
4. conserve le nom public, le quota, le coefficient interne et les fonctionnalités de chaque version publiée ;
5. crée une nouvelle version uniquement pour les classes dont le pourcentage change ;
6. enregistre le compte fondateur comme créateur et approbateur ;
7. publie toutes les versions modifiées dans une transaction atomique ;
8. conserve les anciennes versions dans l’historique ;
9. invalide le cache économique après validation de la transaction.

## Gouvernance

La simplification de l’interface ne réduit pas les contrôles :

- session d’administration obligatoire ;
- MFA récent obligatoire ;
- capacités de gestion, d’approbation et de publication obligatoires ;
- aucun écrasement direct d’une version publiée ;
- aucune période intermédiaire dont le total serait différent de 100 % ;
- conservation des identités des acteurs.

## Résultat attendu

Le fondateur voit une page claire avec :

- quatre champs exprimés en pourcentage ;
- les valeurs actuellement publiées ;
- un total recalculé en temps réel ;
- un message indiquant le reste à répartir ou le dépassement ;
- un seul bouton `Appliquer la répartition` ;
- aucune saisie de coefficient ou de points de base.
