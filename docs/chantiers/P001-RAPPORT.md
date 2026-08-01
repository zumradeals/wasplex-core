# P001 — RAPPORT DE CHANTIER

**Branche :** `codex/p001-identity-spaces-capabilities`
**Commit de base :** `31031d58e9644c2df5e31647a2ff006e185f04f4`
**Commit publié :** `f4f0a28f83fc63aa97674fc905a0d2a0ccd83ae0`
**Statut :** branche publiée — validation VPS en attente

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
| Pest | Réussi — 16 tests, 105 assertions |
| Prettier | Réussi |
| ESLint | Réussi |
| TypeScript/Vue | Réussi |
| Build Vite | Réussi — 565 modules transformés |
| Smoke HTTP local | Santé, accueil, connexion et inscription servent les bons composants |
| Diff Git | `git diff --check` sans erreur |

Les tests couvrent notamment la normalisation et l’unicité des identifiants, le profil minimal, les memberships, l’isolation des organisations, le refus d’une capacité expirée, la révocation de session, le MFA fondateur et l’acceptation sécurisée d’une invitation.

## Incident de première bascule

La première tentative VPS a été annulée automatiquement avant migration. Deux causes de préflight ont été identifiées : l’extension `pdo_sqlite` de PHP 8.4 n’était pas installée sur l’hôte et le fichier de maintenance de production affectait les tests HTTP. La dépendance SQLite est désormais déclarée dans Composer et PHPUnit utilise un backend de maintenance isolé en mémoire. Le rollback a restauré `main`, son build et l’état public sans migration P001 appliquée.

## Limites avant revue

1. confirmer les contrôles GitHub Actions lorsqu’ils seront disponibles ;
2. déployer la branche sur le VPS avec sauvegarde PostgreSQL préalable ;
3. exécuter la migration sur PostgreSQL 17 et vérifier Redis/PHP-FPM/Nginx ;
4. capturer les trois shells. Le navigateur automatisé local n’a pas pu être installé dans cet environnement, mais les composants, types, build et routes ont été vérifiés.

## Déploiement VPS prévu après publication

```bash
cd /var/www/html/wasplex-core
git fetch origin codex/p001-identity-spaces-capabilities
git switch codex/p001-identity-spaces-capabilities
git pull --ff-only origin codex/p001-identity-spaces-capabilities

cd apps/platform
export COMPOSER_ALLOW_SUPERUSER=1
php8.4 /usr/local/bin/composer-wasplex install --no-interaction --prefer-dist --no-progress
npm ci
npm run build
php8.4 artisan migrate --force
php8.4 artisan optimize
```

La sauvegarde de la base, le test hors trafic et la commande fondateur doivent précéder toute bascule publique.
