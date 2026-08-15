# P018-A.1 — Recentrage Live Annonceur

**Décision :** 2026-08-15  
**Statut :** correctif de cadrage P018-A  
**Priorité :** avant tout test fonctionnel Live

## Décision produit

Un Live Wasplex est **créé et piloté par un annonceur depuis le Studio annonceur**.

L'espace membre ne crée pas de Live. Il sert de surface spectateur : découverte, entrée, présence et sortie.

Cette décision précise le périmètre opérationnel de P018-A et lève l'ambiguïté historique de la fiche générale Live qui évoquait plusieurs catégories de porteurs. Dans l'implémentation actuelle, les futurs partenaires, institutions ou autres organisations qui devront diffuser un Live passeront par un contexte annonceur/organisation autorisé, tant qu'un espace spécialisé n'est pas explicitement conçu et validé.

## Parcours canonique P018-A.1

```text
Studio annonceur
→ Live
→ Créer un Live
→ programmer ou démarrer
→ piloter

Feed membre
→ Live
→ En direct / À venir
→ Entrer
→ Quitter
```

## Frontières

### Studio annonceur

Le Studio possède la création et le pilotage :

- brouillon ;
- programmation ;
- démarrage ;
- pause ;
- reprise ;
- fin ;
- historique des Lives de l'organisation active.

### Espace membre

La surface `/live` est strictement spectateur :

- Lives publics en cours ;
- Lives publics programmés ;
- détail ;
- entrée ;
- sortie ;
- compteur spectateurs.

Aucun bouton `Créer un Live` ni `Mes Lives` n'y est exposé.

## Sécurité d'espace

Les routes de gestion sont déplacées de :

```text
/api/creator/lives
```

vers :

```text
/api/advertiser/lives
```

Elles exigent :

- authentification ;
- session valide ;
- espace annonceur actif ;
- organisation annonceur résolue depuis cet espace ;
- capacité organisationnelle de consultation ou gestion ;
- cohérence entre le Live et l'organisation annonceur active.

## Portée des données

P018-A.1 ajoute `advertiser_organization_id` à `lives`.

Le compte créateur reste enregistré par `owner_account_id` pour la responsabilité et l'audit, mais la portée métier du Live est l'organisation annonceur. Cela empêche un même compte ayant accès à plusieurs espaces annonceurs de mélanger leurs Lives.

## Feed

Le bouton `Live` en haut du Feed quitte le mécanisme `Bientôt disponible` et ouvre désormais `/live`.

Le bouton `Explorer` reste inchangé tant que son chantier n'est pas livré.

## Économie

Aucun changement économique :

- aucun WP ;
- aucun débit Wallet ;
- aucun budget réservé ;
- aucune sponsorisation ;
- aucune écriture Grand Livre.

Le but de P018-A.1 est uniquement de corriger la propriété produit et la séparation des espaces avant le premier test réel.
