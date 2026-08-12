# P016-A — Santé citoyen et capsule médicale d’urgence

## Statut

En cours de validation technique.

## Objectif

Livrer le premier cœur citoyen de Wasplex Santé à l’intérieur du module Alertes, sans créer un sixième onglet principal et sans simuler les espaces professionnels/institutionnels qui relèvent de P019.

## Périmètre fonctionnel

### Ma Santé

L’onglet Santé d’Alertes devient un espace réel avec quatre vues :

- Capsule ;
- Consentements ;
- Représentants ;
- Accès.

### Capsule d’urgence

La capsule conserve uniquement les informations potentiellement utiles immédiatement :

- groupe sanguin déclaré ;
- allergies critiques ;
- pathologies critiques utiles ;
- traitements vitaux ;
- instructions urgentes ;
- contact d’urgence ;
- médecin ou établissement de référence ;
- provenance ;
- date de dernière vérification / mise à jour.

Une donnée saisie par le membre porte la provenance `self_declared` et ne peut jamais être présentée comme médicalement certifiée.

Le payload médical est chiffré au repos.

### Consentement d’urgence

Le membre peut accorder ou révoquer le consentement `emergency_capsule`.

Sans ce consentement, le contrat `EmergencyCapsuleProvider` ne renvoie aucune projection médicale.

Ce consentement ne vaut pas autorisation de consulter un futur dossier médical complet. Le véritable bris de glace professionnel reste réservé à P019 et devra exiger au minimum : professionnel habilité, institution active, MFA récente, SOS identifié, justification, durée limitée et audit.

### Représentants

Le membre peut ajouter une personne comme :

- contact d’urgence ;
- représentant légal à vérifier.

Une relation déclarée ne donne aucun pouvoir sensible. Toute nouvelle entrée reste `pending` avec `proof_status=unverified` jusqu’au futur parcours de vérification.

Le nom et le téléphone du représentant sont chiffrés au repos.

Le membre peut suspendre un représentant.

### Historique des accès

Le schéma `health_access_events` et l’écran citoyen sont préparés pour afficher les futurs accès professionnels / bris de glace.

P016-A n’invente aucun accès institutionnel. Tant que P019 n’est pas raccordé, l’historique externe peut naturellement rester vide.

## Séparation technique

Santé est un domaine autonome :

- provider dédié ;
- migrations dédiées ;
- modèles dédiés ;
- routes dédiées ;
- contrat `EmergencyCapsuleProvider` pour la projection minimale contrôlée.

Alertes ne doit pas lire directement les tables Santé.

Les domaines Publicité, Matching, Feed économique et Wallet ne doivent jamais lire ou exploiter les données Santé.

## Tables P016-A

La migration `2026_08_12_130000_create_health_core_tables.php` crée :

- `health_patients` ;
- `health_emergency_capsules` ;
- `health_consents` ;
- `health_representatives` ;
- `health_access_events`.

`health_patients.account_id` est nullable afin de préserver l’architecture future où un patient peut exister sans compte Wasplex actif (mineur, nouveau-né, personne inconsciente, création institutionnelle).

## Audit

Les opérations citoyennes sensibles sont auditées :

- mise à jour de capsule ;
- consentement accordé/révoqué ;
- représentant ajouté ;
- représentant suspendu.

Les événements d’audit n’enregistrent pas les valeurs médicales elles-mêmes.

## Hors périmètre P016-A

- certification par médecin ou laboratoire ;
- comptes professionnels ;
- établissements de santé vérifiés ;
- consultation professionnelle de patients ;
- bris de glace réel ;
- dossier médical longitudinal ;
- prescriptions, résultats et documents cliniques complets ;
- routage institutionnel Santé.

Ces éléments seront raccordés avec P019 puis les étapes Santé suivantes.

## Déploiement futur

Comme P016-A ajoute un nouveau ServiceProvider, le cache Laravel doit être vidé avant les migrations :

```bash
cd /root/wasplex-core/apps/platform
php8.4 artisan optimize:clear
php8.4 artisan migrate --force
```

Le build et le recache viennent ensuite.
