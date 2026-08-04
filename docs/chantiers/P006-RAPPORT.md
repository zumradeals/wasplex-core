# P006 — RAPPORT DE VALIDATION

**Statut :** `ready_for_review`  
**Branche de validation :** `codex/p006-validation-regularization`  
**Commit P006 présent sur `main` :** `bc68269b2fa73ab34fab37b791dba381312e46ad`  
**Date :** 4 août 2026

## Objet

Régulariser et valider le chantier P006 déjà présent sur `main` afin que son code soit soumis à la même chaîne de contrôle que les chantiers précédents.

## Périmètre contrôlé

- assistant annonceur en sept étapes ;
- campagnes et versions immuables ;
- rattachement des marques et médias P005 ;
- estimation agrégée sans SmartProfile ;
- catalogue tarifaire sandbox versionné ;
- devis figé et partage 50/50 ;
- réservation Wallet P003 ;
- libération avant soumission ;
- soumission vers P007 sans capture ni diffusion ;
- isolation entre annonceurs ;
- migrations réversibles ;
- interfaces Vue responsive.

## Validation attendue

- PHP 8.4 ;
- Pint ;
- Larastan niveau 8 ;
- Pest SQLite ;
- Pest PostgreSQL 17 ;
- rollback PostgreSQL ;
- Prettier ;
- ESLint ;
- TypeScript/Vue ;
- build Vite.

## Décision

P006 ne sera déclaré `merged` puis `deployed` qu’après une CI entièrement verte et une recette VPS du parcours brouillon → devis → financement → soumission.
