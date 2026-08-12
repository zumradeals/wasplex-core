# P019 — Rapport de clôture

## Objet

P019 fournit le socle commun des espaces professionnels et institutionnels Wasplex sans créer de second système de comptes et sans donner d'accès transversal aux données Alertes, Santé, Wallet ou Advertising.

## Base

- Branche : `feat/p019-professional-institutional-spaces`
- Base : `ae036a0cc89b1de17f08533191a40164d8c13d76`
- Dépendances : P001 Identity et P012 administration/audit disponibles sur `main`

## Inclus

- type de `UserSpace` : `professional` ;
- organisations professionnelles nominatives avec workflow de vérification ;
- types d'espaces : partenaire, commerçant, prestataire, institution de sécurité, établissement Santé, professionnel Santé, opérateur financier, agent terrain, modération, opérations Wasplex ;
- territoire, finalités, restrictions et numéro d'enregistrement chiffré au repos ;
- rôles professionnels nominatifs ;
- points de service ;
- demande d'espace depuis Mon Espace ;
- shell `/pro` responsive desktop/mobile ;
- revue et décision dans la console d'administration ;
- capacités administratives `admin.professional-spaces.view` et `admin.professional-spaces.decide` ;
- capacités professionnelles scoppées à l'organisation ;
- compatibilité rôle/type d'institution ;
- audit des demandes, décisions, accès, rôles et points de service ;
- réutilisation des memberships et invitations Identity existants.

## Sécurité et confidentialité

- aucun compte partagé générique ;
- aucun accès professionnel sensible avant vérification ;
- les grants professionnels sont limités à l'organisation ;
- `security_officer` n'est accepté que dans une institution de sécurité ;
- `health_professional` n'est accepté que dans un contexte Santé compatible ;
- suspension/restriction révoque l'accès professionnel sensible ;
- P019 ne lit ni ne copie un dossier Santé ou Alertes complet ;
- les modules P015/P016 restent propriétaires de leurs données, consentements et journaux ;
- le numéro d'enregistrement officiel est chiffré avec le cast Laravel `encrypted`.

## Données

Migration :

`2026_08_12_140000_create_professional_space_foundation.php`

Tables :

- `professional_spaces` ;
- `professional_role_assignments` ;
- `professional_service_points`.

La migration est réversible et supprime les trois tables dans l'ordre inverse.

## API

### Compte utilisateur

- `GET /api/professional-spaces/mine`
- `POST /api/professional-spaces`

### Espace professionnel actif

- `GET /api/professional/workspace`
- `POST /api/professional/service-points`
- `POST /api/professional/roles`

### Administration

- `GET /api/admin/professional-spaces`
- `GET /api/admin/professional-spaces/{professionalSpace}`
- `POST /api/admin/professional-spaces/{professionalSpace}/decision`

Les routes de décision administrative exigent MFA récent et capacités explicites.

## UI

- carte « Espace professionnel » dans Mon Espace ;
- demande guidée avec type, identité légale, territoire et finalités ;
- ouverture de l'espace vérifié dans `/pro` ;
- shell Wasplex Pro orienté desktop de pilotage et mobile terrain ;
- onglet « Professionnels & institutions » dans Utilisateurs de la console admin.

## Tests

Couverture ajoutée :

- demande citoyenne sans droit sensible ;
- chiffrement du numéro d'enregistrement ;
- impossibilité de basculer dans un espace non vérifié ;
- vérification admin avec MFA et capacités ;
- activation des capacités scoppées ;
- restriction avec motif et révocation ;
- refus admin sans capacité dédiée ;
- rôle `security_officer` limité aux capacités sécurité ;
- refus d'un rôle Santé dans une institution de sécurité ;
- bootstrap fondateur idempotent via le catalogue partagé des capacités.

La preuve finale attendue avant fusion est la CI complète du dépôt : Prettier, ESLint, TypeScript, build Vite, Pint et suite Pest sur PostgreSQL/Redis.

## Raccordements suivants

P019 fournit désormais la racine institutionnelle vérifiée nécessaire à :

- P015 Alertes : projection institutionnelle, prise en charge et transfert ;
- P016 Santé : établissement/professionnel vérifié, accès normal et break-glass ;
- P017 Carte : partenaires/points de service ;
- P014 Fonds : prestataires vérifiés.

Ces raccordements doivent rester dans leurs modules propriétaires et ne pas déplacer leurs données vers Identity.

## Rollback

1. désactiver les routes/shell professionnels en revenant au commit précédent ;
2. exécuter le rollback de la migration P019 ;
3. les organisations et `UserSpace` créés par P019 doivent être supprimés/neutralisés avant rollback si des données de production existent ;
4. aucun mouvement Ledger ou Wallet n'est créé par P019.
