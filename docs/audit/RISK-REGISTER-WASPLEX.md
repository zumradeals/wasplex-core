# WASPLEX — REGISTRE INITIAL DES RISQUES

| ID | Risque | Gravité | Probabilité | Réponse recommandée |
|---|---|---:|---:|---|
| R-001 | Coder avant validation de l'audit et de la roadmap | Critique | Moyenne | Respecter la séquence audit → validation → roadmap → P000 |
| R-002 | Deux sources maîtres concurrentes | Haute | Haute | Générer un seul `MASTER-WASPLEX.md` canonique et transformer la note 22 en source/redirect explicite |
| R-003 | Déplacer immédiatement 23 notes et casser les références | Moyenne | Haute | Planifier une réorganisation atomique avec liens vérifiés |
| R-004 | Introduire plusieurs frameworks frontend | Haute | Moyenne | Choisir une direction avant P000 |
| R-005 | Surdimensionner le noyau partagé | Haute | Haute | N'y placer que primitives et contrats stables |
| R-006 | Construire tous les modules avant une verticale | Haute | Haute | Livrer le chemin publicitaire complet par chantiers |
| R-007 | Implémenter un Wallet à solde mutable | Critique | Moyenne | Ledger double entrée, projection Wallet, tests d'idempotence/concurrence |
| R-008 | Créer un moteur universel prématuré | Haute | Haute | Implémenter seulement les usages nécessaires aux chantiers actifs |
| R-009 | Oublier l'administration et les configurations | Haute | Moyenne | Inclure les capacités fondatrices et vues de pilotage dans chaque verticale concernée |
| R-010 | Utiliser des données sensibles pour le ciblage | Critique | Faible à moyenne | Projections minimales, consentements, tests de cloisonnement |
| R-011 | Dépendances non verrouillées ou expérimentales | Haute | Moyenne | Vérifier versions stables supportées et committer les locks |
| R-012 | Aucun pipeline de qualité | Haute | Haute | CI minimale dès P000 |
| R-013 | Dépendance à un prestataire externe trop tôt | Moyenne | Moyenne | Contrats et adaptateurs sandbox, aucune logique métier dans les SDK |
| R-014 | Claude et Codex travaillent sur le même périmètre sans passation | Haute | Moyenne | Une branche/mission, rapport d'état et commit de base explicites |

## Risques bloquants avant P000

- validation de la structure du monorepo ;
- choix frontend ;
- clarification du document maître canonique ;
- autorisation de créer une branche dédiée et d'initialiser les dépendances.
