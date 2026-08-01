# P001 — RAPPORT DE CHANTIER

**Branche :** `codex/p001-identity-spaces-capabilities`
**Commit de base :** `31031d58e9644c2df5e31647a2ff006e185f04f4`
**Commit fonctionnel validé :** `33978e2fc4d4b32a6fa64a99fbf6cbd57ebd4d2c`
**Statut :** accepté et déployé en production

## Réalisé

- module Laravel `Identity` organisé en domaine, application, infrastructure et HTTP ;
- migration réversible pour comptes, identifiants, profils, appareils, sessions, MFA, espaces, organisations, memberships, invitations, capacités et audit ;
- compte universel avec identifiant e-mail normalisé et profil minimal ;
- session applicative nommée, suivie par appareil et révocable ;
- espace personnel créé au provisionnement ;
- activation d’un espace annonceur et de son organisation ;
- appartenance explicite aux espaces et organisations ;
- invitation expirante, jeton stocké sous forme de hash et acceptation limitée au compte ciblé ;
- capacités liées au compte, à l’espace et à l’organisation, avec validité et révocation ;
- commande nominative `identity:bootstrap-founder` sans rôle global ;
- MFA TOTP chiffré, confirmé et exigé récemment pour la console fondateur ;
- journal d’audit avec acteur, session, appareil, espace, organisation, capacité, résultat et trace ;
- API du compte, profil, espaces, sessions, MFA, organisations, invitations et administration ;
- shells Vue/Inertia séparés : mobile utilisateur, responsive annonceur et desktop administration ;
- page d’accueil P001 avec entrées d’inscription et de connexion.

## Contrats principaux

| Domaine | Contrats livrés |
|---|---|
| Authentification | inscription, connexion, déconnexion, session applicative |
| Compte | lecture et mise à jour du profil minimal, liste/révocation des sessions |
| Espaces | liste, activation annonceur, changement d’espace |
| Organisation | création lors de l’activation, invitation, acceptation et membership |
| Capacités | contrôle contextuel, attribution, expiration et révocation |
| Administration | comptes, organisations et capacités avec espace admin + MFA récent |

## Tests exécutés

| Contrôle | Résultat |
|---|---|
| Pint | Réussi — 52 fichiers analysés par Larastan après formatage |
| Larastan niveau 8 | Réussi — aucune erreur |
| Pest | Réussi — 17 tests, 115 assertions |
| Prettier | Réussi |
| ESLint | Réussi |
| TypeScript/Vue | Réussi |
| Build Vite | Réussi — 565 modules transformés |
| Smoke HTTP local | Santé, accueil, connexion et inscription servent les bons composants |
| Diff Git | `git diff --check` sans erreur |

Les tests couvrent notamment la normalisation et l’unicité des identifiants, le profil minimal, les memberships, l’isolation des organisations, le refus d’une capacité expirée, la révocation de session, le MFA fondateur et l’acceptation sécurisée d’une invitation.

## Incident de première bascule

La première tentative VPS a été annulée automatiquement avant migration. Deux causes de préflight ont été identifiées : l’extension `pdo_sqlite` de PHP 8.4 n’était pas installée sur l’hôte et le fichier de maintenance de production affectait les tests HTTP. La dépendance SQLite est désormais déclarée dans Composer et PHPUnit utilise un backend de maintenance isolé en mémoire. Le rollback a restauré `main`, son build et l’état public sans migration P001 appliquée.

## Validation VPS et recette

- sauvegarde PostgreSQL effectuée avant migration ;
- dépendances Composer et npm installées depuis les fichiers de verrouillage ;
- migration Identity appliquée avec succès sur PostgreSQL 17 ;
- Nginx sert `wasplex-core/apps/platform/public` via PHP 8.4-FPM ;
- Redis 7.4 persistant, PostgreSQL 17, PHP-FPM et Nginx actifs ;
- accueil, connexion, inscription, liveness et santé API validés en HTTPS ;
- compte `admin@wasplex.com` promu nominativement comme fondateur ;
- MFA TOTP configuré, confirmé et vérifié ;
- bascule entre « Mon espace » et « Console fondateur » validée ;
- console d’administration validée avec 1 compte, 2 espaces et 4 capacités critiques ;
- correction UX publiée : le navigateur redirige vers la configuration ou le challenge MFA au lieu d’exposer un refus 403 lors de la bascule ;
- dépôt VPS propre sur le commit `33978e2`.

## Décision de clôture

P001 est accepté fonctionnellement et techniquement. Les contrats d’identité, de sessions, d’espaces, d’organisations, de capacités contextuelles, de MFA et d’audit constituent désormais le socle requis par P002.
