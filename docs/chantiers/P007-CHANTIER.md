# P007 — REVUE ADMINISTRATIVE ET ACTIVATION DE CAMPAGNE

**Statut :** `merged`  
**Branche :** `codex/p007-campaign-admin-review`  
**Pull Request :** `#10`  
**Commit de base :** `63d355409b30cac77340f59243322d611e59e18e`  
**Commit fusionné :** `b66f97c1aa60d1f1c042a4d13fb5fdc62a36978e`  
**Date d’ouverture :** 4 août 2026  
**Date d’acceptation et de fusion :** 4 août 2026  
**Autorité :** fondateur Wasplex

## 1. Objectif

Donner au fondateur et aux administrateurs explicitement autorisés une file opérationnelle permettant d’examiner une campagne financée, de demander des corrections, de l’approuver, de la rejeter ou de la suspendre, sans capturer le budget et sans activer le Feed.

## 2. Dépendances verrouillées

- P001 : comptes, espaces, MFA, sessions et capacités ;
- P003 : réservation, libération et projection Wallet ;
- P005 : marque, profil annonceur et médias ;
- P006 : campagne versionnée, audience de planification, devis figé, financement et soumission.

## 3. Inclus

- file des campagnes soumises ;
- fiche complète de revue : annonceur, marque, version, médias, audience, devis et réservation ;
- motif obligatoire pour toute demande de correction ou tout rejet ;
- correction par l’annonceur et resoumission ;
- approbation administrative ;
- rejet et libération idempotente de la réservation Wallet ;
- suspension d’une campagne approuvée ;
- historique append-only des revues et changements de statut ;
- tâche administrative minimale par soumission ;
- capacités explicites, contexte administration et MFA récente ;
- widget de campagnes à traiter dans la console fondateur ;
- commande de bootstrap pour les capacités et les campagnes déjà soumises ;
- tests SQLite et PostgreSQL.

## 4. Exclus

- SmartProfile et consentements ;
- sélection d’utilisateurs ;
- Matching ;
- Feed et livraison ;
- capture du budget ;
- crédit Wallet utilisateur ;
- reporting de performance.

## 5. États métier

```text
funded
→ submitted
→ changes_requested
→ submitted
→ approved

submitted
→ rejected

approved
→ suspended
```

Les états `approved` et `suspended` ne déclenchent aucune diffusion dans P007.

## 6. Politique financière

| Décision | Réservation Wallet | Financement |
|---|---|---|
| Soumission | conservée | `submitted` |
| Demande de correction | conservée | inchangé |
| Resoumission | conservée | `submitted` |
| Approbation | conservée | `approved` |
| Rejet | libérée | `released` |
| Suspension | conservée | `suspended` |

Toute libération utilise le contrat Wallet P003 avec une clé d’idempotence déterministe. P007 n’appelle jamais `capture`.

## 7. Données propriétaires

- `campaign_review_cases` ;
- `campaign_review_events` ;
- `campaign_status_events` ;
- `campaign_review_tasks`.

Les versions P006, devis et réservations existants restent propriétaires de leurs modules respectifs.

## 8. Capacités

- `campaign.review.view` ;
- `campaign.review.request_changes` ;
- `campaign.review.approve` ;
- `campaign.review.reject` ;
- `campaign.suspend`.

Aucune autorité n’est déduite du seul rôle ou du nom « fondateur ».

## 9. API web

- `GET /administration/campagnes` ;
- `GET /administration/campagnes/{campaign}` ;
- `POST /administration/campagnes/{campaign}/corrections` ;
- `POST /administration/campagnes/{campaign}/approuver` ;
- `POST /administration/campagnes/{campaign}/rejeter` ;
- `POST /administration/campagnes/{campaign}/suspendre`.

Les routes exigent authentification, session active, espace administration, MFA récente et capacité dédiée.

## 10. Événements

- `campaign.changes_requested` ;
- `campaign.approved` ;
- `campaign.rejected` ;
- `campaign.suspended` ;
- `campaign.review_opened` ;
- `campaign.resubmitted`.

Les écritures d’historique sont persistées avant l’émission après commit.

## 11. Critères d’acceptation

1. Une campagne soumise apparaît dans la file admin.
2. Un administrateur sans capacité reçoit `403`.
3. Une demande de correction exige un motif et conserve la réservation.
4. L’annonceur peut modifier puis resoumettre sans nouveau débit.
5. Une approbation rend la campagne `approved` et conserve le budget réservé.
6. Un rejet rend la campagne `rejected`, libère une seule fois la réservation et interdit toute future éligibilité.
7. Une suspension rend une campagne approuvée `suspended` sans capture.
8. Chaque décision conserve acteur, motif, ancienne valeur, nouvelle valeur et horodatage.
9. La console fondateur affiche le nombre de revues en attente.
10. Pint, Larastan, Pest SQLite/PostgreSQL, Prettier, ESLint, TypeScript et Vite sont verts.

Tous les critères techniques ont été validés avant fusion de la PR #10.

## 12. Rollback

- aucune capture ni écriture Ledger nouvelle n’est produite par P007 ;
- la migration P007 peut être annulée tant qu’aucun chantier P008 ne dépend des cas de revue ;
- les rejets déjà exécutés restent expliqués par l’audit Wallet et ne doivent jamais être annulés par suppression directe ;
- toute correction financière éventuelle passe par une nouvelle opération compensatoire.
