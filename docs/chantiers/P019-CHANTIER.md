# P019 — Espaces professionnels et institutionnels

## Statut

En cours de validation sur `feat/p019-professional-institutional-spaces`.

## Base

- branche de base : `main`
- commit de base : `ae036a0cc89b1de17f08533191a40164d8c13d76`
- dépendances disponibles : P001 Identity, P012 audit/admin, P015-A Alertes citoyennes, P016-A Santé citoyen

## Objectif

Fournir à Wasplex un socle institutionnel réel : chaque professionnel agit avec son compte personnel, dans une organisation identifiée, un espace métier vérifié, un territoire et des capacités explicites.

Le chantier ne crée aucun compte générique Police, Hôpital ou Partenaire.

## Parcours

```text
Compte personnel
→ demande d’espace professionnel
→ organisation pending_verification
→ propriétaire nominatif
→ revue Wasplex avec MFA + capacité dédiée
→ vérification / restriction / refus
→ espace professional actif
→ rôles + capacités scoppées à l’organisation
→ points de service
→ bureau desktop / terrain mobile
→ raccordements Alertes et Santé par contrats dédiés
```

## Types supportés dans le premier socle

- `partner`
- `merchant`
- `service_provider`
- `security_institution`
- `healthcare_institution`
- `health_professional`
- `financial_operator`
- `field_agent`

Les types `moderation_team` et `wasplex_operations` sont réservés à l’administration interne et ne sont pas proposés dans le formulaire public P019.

## Données

Migration `2026_08_12_140000_create_professional_space_foundation.php` :

- `professional_spaces`
- `professional_role_assignments`
- `professional_service_points`

`professional_spaces` référence l’`organization` et le `user_space` Identity existants. Aucun second système de comptes ou d’organisations n’est créé.

Le numéro d’immatriculation/référence officielle est chiffré au repos.

## États de vérification

- `pending_verification`
- `under_review`
- `verified`
- `restricted`
- `suspended`
- `rejected`

Un espace `professional` reste non actif tant que sa vérification n’est pas `verified`.

## Capacités

Avant vérification, le propriétaire ne reçoit que des capacités neutres de gestion :

- `organization.manage.self`
- `professional.space.view.self`
- `professional.organization.manage.self`

Après vérification :

- `professional.space.access`
- `professional.team.manage`
- `professional.service-points.manage`

Selon le type :

### Sécurité

- `professional.alerts.institution.view`
- `professional.alerts.institution.act`

### Santé institution

- `professional.health.institution.manage`
- `professional.health.access.request`

### Professionnel Santé

- `professional.health.access.request`
- `professional.health.emergency-capsule.request`

Ces capacités n’accordent pas à elles seules un accès général aux données Alertes ou Santé. Les domaines consommateurs doivent encore vérifier leurs propres consentements, projections, finalités, MFA et durées.

## Administration

Capacités :

- `admin.professional-spaces.view`
- `admin.professional-spaces.decide`

La revue administrative exige la MFA récente. Les actions de consultation et de décision sont auditées.

Pour `restrict`, `reject` et `suspend`, un motif est obligatoire. La restriction ou suspension révoque les capacités sensibles de l’organisation.

## Interfaces

### Mon Espace

Une carte « Espace professionnel » permet :

- de choisir le type d’organisation ;
- de renseigner identité légale, territoire et finalités ;
- de suivre l’état de vérification ;
- de voir le motif de correction/restriction ;
- d’ouvrir l’espace après vérification avec switch automatique.

### Wasplex Pro

Route `/pro` :

- desktop de pilotage ;
- mobile opérationnel ;
- vue d’ensemble ;
- dossiers (surface prête, alimentée par les domaines consommateurs) ;
- opérations ;
- équipe ;
- points de service ;
- audit ;
- organisation active et territoire visibles.

### Administration

Administration → Utilisateurs → Professionnels & institutions :

- files par statut ;
- identité et territoire ;
- finalités ;
- référence officielle privée ;
- responsables nominatifs ;
- vérification, restriction, refus, suspension.

## Frontières avec Alertes et Santé

P019 possède :

- identité de l’organisation ;
- vérification institutionnelle ;
- équipe ;
- rôles ;
- capacités ;
- territoire ;
- points de service.

P019 ne possède pas :

- les déclarations Alertes ;
- les dossiers institutionnels Alertes ;
- les capsules médicales ;
- les consentements Santé ;
- le dossier médical.

P015/P016 consommeront le contexte professionnel vérifié, sans lecture générale de la base Identity.

## Sécurité

- aucun compte partagé ;
- aucune capacité sensible avant vérification ;
- capacités scoppées à `organization_id` ;
- MFA récente pour la décision admin ;
- audit des demandes et décisions ;
- référence officielle chiffrée ;
- suspension = révocation des capacités sensibles ;
- aucun espace professionnel ne donne accès à toute la base Wasplex.

## Tests prévus

- demande crée un espace pending et nominatif ;
- référence officielle chiffrée ;
- espace pending impossible à activer ;
- aucune capacité sécurité/Santé avant vérification ;
- admin sans capacité refusé ;
- MFA récente obligatoire ;
- vérification active espace et capacités scoppées ;
- restriction exige motif et révoque capacités sensibles ;
- workspace professionnel isolé à l’organisation active ;
- build/TypeScript/ESLint/Pint/suite Laravel complets.

## Rollback

La migration supprime, dans l’ordre :

1. `professional_service_points`
2. `professional_role_assignments`
3. `professional_spaces`

Elle ne supprime pas les comptes, organisations ou espaces historiques Identity.
