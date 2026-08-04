# P007 — RAPPORT DE LIVRAISON

**Chantier :** Revue administrative et activation de campagne  
**Statut :** `deployed`  
**Branche :** `codex/p007-campaign-admin-review`  
**Pull Request :** `#10`  
**Commit de base :** `63d355409b30cac77340f59243322d611e59e18e`  
**Commit technique validé :** `e1c34522d5f035e8ed30a4ffc3d95b49c483f274`  
**Commit de fusion :** `b66f97c1aa60d1f1c042a4d13fb5fdc62a36978e`  
**Commit déployé :** `7a7d42e55de333a65e313756044a4faef91aa202`  
**Date d’acceptation et de fusion :** 4 août 2026  
**Date de déploiement en production :** 4 août 2026  
**Autorité d’acceptation et de déploiement :** fondateur Wasplex

## 1. Résultat livré

P007 ferme la phase « Annonceur et campagne » en ajoutant une décision administrative explicite entre la soumission P006 et le futur Matching P008.

Le parcours obtenu est :

```text
Campagne financée
→ soumission
→ dossier de revue
→ correction et resoumission, approbation ou rejet
→ suspension possible après approbation
```

Une approbation rend la campagne administrativement éligible au futur Matching. Elle ne déclenche ni sélection d’utilisateur, ni Feed, ni capture du budget.

## 2. Domaine et données

Deux migrations P007 ont été ajoutées :

1. création des données de revue ;
2. extension réversible des contraintes PostgreSQL de statut P006.

Tables propriétaires :

- `campaign_review_cases` ;
- `campaign_review_tasks` ;
- `campaign_review_events` ;
- `campaign_status_events`.

La contrainte PostgreSQL `campaign_status_allowed` accepte désormais :

```text
draft
quoted
funded
submitted
changes_requested
approved
rejected
suspended
cancelled
```

Une contrainte explicite protège également les statuts de financement P007.

## 3. Workflow administratif

### File de revue

La console administration fournit :

- file des campagnes en attente ;
- filtres approuvées, rejetées, suspendues et historique ;
- compteur de file active ;
- fiche détaillée par campagne.

### Fiche de décision

L’administrateur voit :

- annonceur et marque ;
- version active ;
- texte, CTA et destination ;
- images et vidéos ;
- territoire, rayon et classes ;
- estimation de planification ;
- devis figé et partage 50/50 ;
- état de la réservation Wallet ;
- dossiers de revue et historique des statuts.

### Décisions

- **Demande de correction :** motif obligatoire, réservation conservée.
- **Approbation :** réservation conservée, campagne `approved`.
- **Rejet :** motif obligatoire, libération idempotente, campagne `rejected`.
- **Suspension :** motif obligatoire, réservation conservée, campagne `suspended`.

## 4. Parcours annonceur corrigé

Une demande de correction ouvre un écran dédié dans le Studio :

- affichage du motif administratif ;
- modification de la marque, du message, du média, de la destination, de l’audience et du calendrier ;
- budget financé verrouillé ;
- création d’une nouvelle version immuable ;
- bouton unique « Enregistrer et resoumettre » ;
- nouveau dossier de revue sans second financement.

L’assistant P006 n’est pas détourné pour ce parcours, ce qui évite toute autosauvegarde ou resoumission prématurée.

## 5. Sécurité et capacités

Capacités P007 :

- `campaign.review.view` ;
- `campaign.review.request_changes` ;
- `campaign.review.approve` ;
- `campaign.review.reject` ;
- `campaign.suspend`.

Toutes les routes administratives exigent :

```text
authentification
+ session d’identité active
+ espace administration
+ MFA récente
+ capacité explicite
```

La politique optionnelle `CAMPAIGN_REVIEW_REQUIRE_DISTINCT_DECIDER` permet d’interdire au demandeur de décider sa propre campagne.

## 6. Politique financière prouvée

| Transition | Disponible Wallet | Réservé Wallet | Capture |
|---|---:|---:|---|
| Soumission | inchangé | conservé | non |
| Correction | inchangé | conservé | non |
| Resoumission | inchangé | conservé | non |
| Approbation | inchangé | conservé | non |
| Rejet | restauré | libéré | non |
| Suspension | inchangé | conservé | non |

Le rejet utilise une clé d’idempotence liée au dossier de revue. Une répétition de la décision ne libère jamais deux fois la même valeur.

## 7. Bootstrap

Commande ajoutée :

```bash
php8.4 artisan campaign-review:bootstrap
```

Elle :

- attribue les capacités P007 aux espaces administration existants ;
- crée les dossiers manquants pour les campagnes déjà soumises ;
- peut être rejouée sans dupliquer les dossiers en attente.

## 8. Interfaces livrées

- layout administration responsive ;
- file de revue ;
- fiche de décision ;
- widget P007 du dashboard fondateur ;
- états administratifs dans la liste annonceur ;
- écran annonceur de correction/resoumission.

La recette fonctionnelle approfondie sera poursuivie avec les campagnes réelles et les futurs chantiers P008 et P009.

## 9. Tests P007

Six scénarios métier dédiés couvrent :

1. correction, réservation conservée et resoumission ;
2. approbation sans capture ;
3. rejet, libération et idempotence ;
4. suspension sans libération ;
5. séparation optionnelle demandeur/décideur ;
6. bootstrap des capacités et reprise d’une campagne soumise.

Les tests P006 ont également été adaptés pour vérifier l’ouverture automatique d’un dossier à la soumission.

## 10. Validation CI finale

**Workflow :** `ci`  
**Run :** `30901618819`  
**Job :** `91967007354`  
**Commit validé :** `e1c34522d5f035e8ed30a4ffc3d95b49c483f274`  
**Conclusion :** `success`

Validations réussies :

- PHP 8.4 ;
- Pint ;
- Larastan niveau 8 ;
- Pest SQLite ;
- Pest PostgreSQL 17 ;
- contraintes et rollback PostgreSQL ;
- Prettier ;
- ESLint ;
- TypeScript/Vue ;
- build Vite.

Le passage PostgreSQL a révélé puis fait corriger la contrainte P006 qui refusait initialement les nouveaux états P007. La correction a été apportée par une migration séparée, sans réécriture de la migration déjà déployée.

## 11. Rollback

Ordre de rollback :

1. retirer la contrainte étendue P007 ;
2. normaliser les statuts P007 vers les états P006 compatibles ;
3. supprimer les tables de revue ;
4. conserver les opérations Wallet déjà auditées.

Aucune écriture Ledger nouvelle et aucune capture ne sont générées par P007. Un rejet déjà exécuté ne doit jamais être « annulé » par modification directe ; toute correction financière passe par les contrats Wallet/Ledger.

## 12. Frontières confirmées

P007 ne contient pas :

- SmartProfile ;
- consentement publicitaire ;
- Matching ;
- sélection d’utilisateurs ;
- Feed ;
- diffusion ;
- preuve d’attention ;
- capture du budget ;
- crédit utilisateur.

Ces responsabilités restent respectivement à P008, P009, P010 et P011.

## 13. Acceptation et fusion

Le fondateur Wasplex a explicitement autorisé l’acceptation et la fusion de P007 le 4 août 2026.

GitHub interdisant l’auto-approbation formelle d’une Pull Request créée par le même compte, la décision fondatrice a été enregistrée comme revue de type commentaire avant la fusion.

La PR #10 a ensuite été fusionnée sur `main` au commit :

```text
b66f97c1aa60d1f1c042a4d13fb5fdc62a36978e
```

## 14. Déploiement en production

Le fondateur Wasplex a autorisé le déploiement de P007 sur le VPS de production le 4 août 2026.

Le serveur a récupéré le `main` au commit :

```text
7a7d42e55de333a65e313756044a4faef91aa202
```

Résultats constatés :

```text
Build Vite                         597 modules — succès
Migration revue administrative    Ran
Migration statuts PostgreSQL      Ran
Bootstrap administration          1 espace initialisé
Dossiers historiques repris       0
Routes administratives            6
Maintenance                       OFF
Environnement                     production
Debug                             OFF
Base de données                   PostgreSQL
Cache et sessions                 Redis
Santé HTTP                        200
Connexion HTTP                    200
Administration invitée            302 vers /connexion
```

Les variables suivantes ont été ajoutées à la configuration de production :

```text
CAMPAIGN_REVIEW_REQUIRE_DISTINCT_DECIDER=false
CAMPAIGN_REVIEW_DEFAULT_DUE_HOURS=24
```

Le déploiement a été réalisé sans capture de budget, sans modification directe du Ledger et sans reprise de dossier de campagne déjà soumise.

## 15. Conclusion

P007 est accepté, fusionné et déployé en production. La phase « Annonceur et campagne » est techniquement fermée. La prochaine dépendance fonctionnelle est P008 — SmartProfile, consentements et Matching.
