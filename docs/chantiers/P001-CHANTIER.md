# P001 — COMPTE UNIVERSEL, ESPACES, CAPACITÉS ET SHELLS

**Branche :** `codex/p001-identity-spaces-capabilities`
**Commit de base :** `31031d58e9644c2df5e31647a2ff006e185f04f4`
**Statut :** `in_progress`

## Objectif

Établir une identité Wasplex unique et des contextes utilisateur, annonceur et administration séparés avant toute logique financière ou publicitaire.

## Inclus

- comptes, identifiants normalisés, profil minimal, appareils et sessions révocables ;
- espace personnel, espace annonceur lié à une organisation et console fondateur ;
- memberships explicites d’espace et d’organisation ;
- invitations d’organisation expirables et acceptation liée à l’identifiant invité ;
- capacités explicites, contextualisées, expirables et révocables ;
- journal d’audit des accès et actions sensibles ;
- authentification par session et MFA TOTP obligatoire pour l’administration ;
- shells Inertia/Vue utilisateur, Studio Annonceur et administration ;
- API minimale du compte, des espaces, sessions, organisations et capacités.

## Exclus

- vérification d’adresse électronique et adaptateur d’envoi d’invitation ;
- SmartProfile complet, KYC et gestion documentaire ;
- Ledger, Wallet, paiements et réservations ;
- marques, campagnes, Feed et logique publicitaire ;
- fournisseur MFA externe et codes de récupération.

## Invariants

- aucune capacité globale implicite ;
- une session applicative peut être révoquée indépendamment de la session Laravel ;
- une capacité peut être limitée à un espace et à une organisation ;
- une invitation ne peut être acceptée que par le compte possédant l’identifiant ciblé ;
- la console d’administration exige un espace dédié et une preuve MFA récente ;
- les secrets TOTP sont chiffrés au repos et ne sont jamais renvoyés après l’enrôlement.

## Preuves attendues

- migrations aller/retour sur PostgreSQL 17 ;
- Pint, Larastan niveau 8 et suite Pest ;
- Prettier, ESLint, TypeScript et build Vite ;
- création de compte, activation et changement d’espace ;
- refus d’une capacité expirée et d’un accès inter-organisation ;
- révocation d’une session ;
- parcours MFA fondateur ;
- invitation et adhésion à une organisation ;
- captures des shells selon leurs doctrines responsive.

## Rollback

Avant déploiement, sauvegarder PostgreSQL et le fichier `.env`. En cas d’échec avant toute donnée P002, remettre le code P000 puis exécuter le rollback de la migration P001. Après création de comptes réels, restaurer la sauvegarde plutôt que supprimer les tables d’identité.
