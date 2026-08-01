# WASPLEX — STUDIO ANNONCEUR

**Fichier cible recommandé :** `docs/12-studio-annonceur/00-studio-annonceur-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** espace professionnel de marque, création publicitaire, ciblage, financement, diffusion et analyse  
**Position dans le Compte universel :** espace distinct accessible depuis le sélecteur d’espaces  
**Interfaces officielles :** mobile complet + desktop complet et stratégique  
**Dépendances :** Compte universel, Organisations, Abonnements, Matching publicitaire, Feed, Live, Wallet & Grand Livre, Super moteur de valeur, Administration centrale, Notifications  
**Principe central :** tout annonceur, même non technicien, doit pouvoir créer une campagne ciblée, financée et soumise en moins de cinq minutes, tout en disposant d’un véritable studio de marque évolutif sur desktop  
**Important :** aucune campagne rémunérée ne peut entrer dans le Feed ou le Live sans budget sécurisé et approbation administrative

---

# 1. Objet du document

Ce document définit le Studio Annonceur Wasplex.

Le Studio doit permettre à un particulier, un commerçant, une PME, une grande entreprise, une agence ou une institution autorisée de :

- créer son espace annonceur ;
- renseigner son identité commerciale ;
- créer une ou plusieurs marques ;
- enregistrer sa charte graphique ;
- importer ses visuels, vidéos et textes ;
- recharger son Wallet annonceur ;
- créer une campagne en mode rapide ;
- créer une campagne en mode avancé ;
- choisir un objectif ;
- définir une audience ;
- simuler une portée ;
- définir un budget ;
- réserver ce budget ;
- soumettre une campagne ;
- corriger une campagne ;
- obtenir une approbation administrative ;
- diffuser dans le Feed ;
- programmer un Live sponsorisé ;
- suivre les résultats ;
- gérer une équipe ;
- consulter ses factures et opérations ;
- préparer l’arrivée de futurs outils de création assistée.

---

# 2. Vision produit

L’expérience cible est :

```text
Je passe dans mon espace annonceur
→ je crée ma marque
→ je recharge mon Wallet annonceur
→ je clique “Créer une campagne”
→ je choisis un objectif
→ je choisis une audience
→ j’ajoute une vidéo ou une image
→ je définis un budget
→ je vérifie
→ je soumets
→ l’administrateur approuve
→ la campagne entre dans le Feed
```

Le parcours rapide doit pouvoir être réalisé en moins de cinq minutes par un utilisateur non technicien.

---

# 3. Rôle stratégique du Studio

Le Studio Annonceur est :

- le laboratoire de la marque ;
- l’atelier de création publicitaire ;
- le centre de ciblage ;
- le centre de financement ;
- le centre de diffusion ;
- le centre de suivi ;
- le centre d’équipe ;
- le futur point d’entrée des outils créatifs Wasplex.

Il ne doit pas être réduit à un simple formulaire de campagne.

---

# 4. Profils d’annonceurs pris en charge

Le Studio doit convenir à :

## 4.1. Annonceur individuel

Exemples :

- commerçant ;
- artisan ;
- consultant ;
- promoteur ;
- utilisateur ordinaire souhaitant promouvoir une activité.

## 4.2. Petite ou moyenne entreprise

- une ou plusieurs marques ;
- équipe réduite ;
- campagnes locales ;
- paiements Mobile Money ou virement.

## 4.3. Grande entreprise

- plusieurs marques ;
- équipes ;
- validateurs ;
- campagnes multiples ;
- reporting avancé ;
- budgets importants ;
- agences externes.

## 4.4. Agence

- plusieurs clients ;
- plusieurs espaces ;
- équipes ;
- accès limités par marque ;
- facturation séparée ;
- reporting client.

## 4.5. Institution autorisée

- campagnes d’information ;
- campagnes de sensibilisation ;
- diffusion institutionnelle ;
- aucun accès aux données individuelles.

---

# 5. Doctrine responsive officielle

## 5.1. Mobile

Le mobile doit permettre toutes les opérations essentielles :

- créer une marque ;
- recharger le Wallet annonceur ;
- créer une campagne rapide ;
- ajouter un média ;
- définir une audience ;
- choisir un budget ;
- soumettre ;
- corriger ;
- suspendre ;
- suivre les résultats ;
- gérer les notifications.

Le mobile n’est pas une simple version réduite du desktop.

## 5.2. Desktop

Le desktop est aussi important que le mobile.

Il doit offrir :

- navigation latérale ;
- tableaux ;
- prévisualisation ;
- édition simultanée ;
- bibliothèque créative ;
- rapports détaillés ;
- gestion d’équipe ;
- comparaison ;
- exports ;
- travail multi-marques.

## 5.3. Tablette

La tablette doit permettre :

- gestion ;
- création ;
- validation ;
- présentation ;
- suivi sur le terrain.

---

# 6. Composition desktop recommandée

```text
┌────────────────┬──────────────────────────────┬──────────────────┐
│ Navigation     │ Espace de travail            │ Aperçu / résumé  │
│                │                              │                  │
│ Vue générale   │ Formulaire campagne          │ Aperçu mobile    │
│ Marques        │ Audience                     │ Audience estimée │
│ Bibliothèque   │ Budget                       │ Solde Wallet     │
│ Campagnes      │ Calendrier                   │ Alertes          │
│ Audiences      │                              │                  │
│ Wallet         │                              │                  │
└────────────────┴──────────────────────────────┴──────────────────┘
```

---

# 7. Composition mobile recommandée

```text
Studio Annonceur
├── Solde annonceur
├── Créer une campagne
├── Campagnes actives
├── Campagnes à corriger
├── Mes marques
├── Bibliothèque
├── Résultats
└── Équipe
```

Le parcours campagne doit utiliser des étapes simples.

---

# 8. Activation de l’espace annonceur

Un utilisateur peut activer un espace annonceur depuis son Compte universel.

Parcours :

```text
Mon Espace
→ Ajouter un espace
→ Annonceur
→ type d’annonceur
→ informations minimales
→ création
```

L’utilisateur ne crée pas un second compte.

---

# 9. Types d’espaces annonceurs

```text
individual
business
agency
institutional_advertiser
```

Chaque type peut avoir :

- exigences ;
- KYC ;
- documents ;
- plafonds ;
- méthodes de paiement ;
- permissions ;
- contrats.

---

# 10. Organisation annonceur

Une organisation possède :

- nom légal ;
- nom commercial ;
- pays ;
- numéro d’enregistrement ;
- adresse ;
- représentant ;
- statut ;
- moyens de paiement ;
- Wallet annonceur ;
- marques ;
- équipes ;
- audit.

---

# 11. Statuts d’un annonceur

```text
draft
pending_verification
verified
active
restricted
suspended
closed
```

Une restriction peut viser :

- création de campagne ;
- financement ;
- diffusion ;
- Live ;
- invitations ;
- équipe.

---

# 12. Tableau de bord du Studio

L’accueil affiche :

- solde disponible ;
- budget réservé ;
- dépenses du mois ;
- campagnes actives ;
- campagnes en attente ;
- campagnes à corriger ;
- résultats récents ;
- alertes ;
- raccourci rechargement ;
- raccourci création ;
- état de vérification ;
- équipe.

Action dominante :

> **Créer une campagne**

---

# 13. Laboratoire de marque

Le Studio permet de créer une ou plusieurs marques.

Une marque contient :

- nom ;
- logo principal ;
- logo secondaire ;
- slogan ;
- description ;
- secteur ;
- pays ;
- site ;
- réseaux sociaux ;
- coordonnées publiques ;
- produits ;
- services ;
- points de vente ;
- identité visuelle ;
- documents de vérification ;
- statut.

---

# 14. Charte graphique

La marque peut enregistrer :

```text
Logo principal
Logo secondaire
Couleurs officielles
Polices déclarées
Style visuel
Ton éditorial
Mentions obligatoires
Mentions interdites
Règles d’usage
```

Ces données seront utilisables plus tard par des outils créatifs.

---

# 15. Couleurs de marque

Chaque couleur peut contenir :

```text
name
hex
rgb
cmyk_optional
usage
priority
```

Exemples d’usage :

- principale ;
- secondaire ;
- accent ;
- fond ;
- texte.

---

# 16. Typographies de marque

L’annonceur peut déclarer :

- police principale ;
- police secondaire ;
- police de remplacement ;
- usages ;
- tailles recommandées.

Le Studio ne distribue pas automatiquement des fichiers de police sans droits appropriés.

---

# 17. Ton de communication

Valeurs possibles :

- professionnel ;
- chaleureux ;
- direct ;
- premium ;
- jeune ;
- institutionnel ;
- éducatif ;
- promotionnel.

L’annonceur peut également ajouter ses propres consignes.

---

# 18. Bibliothèque créative

Elle conserve :

- vidéos ;
- images ;
- logos ;
- textes ;
- CTA ;
- sous-titres ;
- miniatures ;
- sons autorisés ;
- documents ;
- modèles ;
- anciennes créations.

---

# 19. Métadonnées média

Chaque ressource possède :

```text
id
brand_id
type
filename
format
size
width
height
duration
language
rights_status
moderation_status
created_by
created_at
```

---

# 20. Statuts des médias

```text
uploading
processing
ready
needs_changes
rejected
approved
archived
```

---

# 21. Validation technique automatique

Le Studio doit vérifier :

- format ;
- taille ;
- durée ;
- ratio ;
- résolution ;
- son ;
- encodage ;
- lien ;
- virus ;
- média corrompu ;
- compatibilité Feed ;
- compatibilité Live.

---

# 22. Aperçu publicitaire

L’annonceur doit voir sa publicité dans une simulation fidèle :

- smartphone ;
- Feed vertical ;
- texte ;
- CTA ;
- gain utilisateur ;
- barre de progression ;
- durée ;
- marque ;
- son ;
- sous-titres.

---

# 23. Studio évolutif

L’architecture doit permettre plus tard :

- génération d’affiches ;
- création vidéo ;
- redimensionnement automatique ;
- suppression d’arrière-plan ;
- génération de sous-titres ;
- traduction ;
- voix off ;
- adaptation par pays ;
- correction de texte ;
- variantes ;
- tests A/B ;
- recommandations créatives ;
- outils assistés par intelligence artificielle.

Ces outils futurs restent séparés du cœur financier.

---

# 24. Wallet annonceur

Le Wallet annonceur est distinct du Wallet personnel.

Il contient :

- solde publicitaire disponible ;
- budget réservé ;
- budget consommé ;
- crédits promotionnels ;
- remboursements ;
- frais ;
- factures ;
- historique.

---

# 25. Comptes du Wallet annonceur

Projections recommandées :

```text
available
reserved
spent
refundable
promotion_credit
pending_deposit
```

Le Grand Livre reste source de vérité.

---

# 26. Rechargement

Parcours :

```text
Recharger
→ montant
→ moyen de paiement
→ référence
→ paiement
→ confirmation prestataire
→ Grand Livre
→ solde disponible
```

---

# 27. Moyens de financement

Possibles :

- Mobile Money ;
- virement bancaire ;
- carte bancaire future ;
- dépôt supervisé ;
- facture entreprise ;
- crédit commercial autorisé ;
- transfert depuis un compte entreprise autorisé.

---

# 28. Dépôt supervisé

Un administrateur peut approuver un dépôt manuel après vérification.

Règles :

- preuve ;
- référence ;
- motif ;
- idempotence ;
- approbation ;
- Grand Livre ;
- audit.

Aucun crédit direct du solde.

---

# 29. Transfert Wallet personnel vers annonceur

Cette fonction peut exister si activée.

Parcours :

```text
Wallet personnel
→ montant
→ confirmation
→ transfert interne
→ Wallet annonceur
```

Les compartiments restent séparés.

---

# 30. Factures et reçus

L’annonceur peut consulter :

- recharges ;
- campagnes ;
- frais ;
- remboursements ;
- avoirs ;
- opérations partenaires ;
- campagnes Live.

---

# 31. Création de campagne

Deux modes sont proposés :

```text
Campagne rapide
Campagne avancée
```

---

# 32. Campagne rapide

Objectif :

> permettre à un non-technicien de créer une campagne en moins de cinq minutes.

Étapes :

```text
1. Marque
2. Objectif
3. Contenu
4. Audience
5. Budget
6. Vérification
7. Soumission
```

---

# 33. Étape Marque

L’annonceur :

- choisit une marque ;
- crée une marque rapide ;
- vérifie le nom ;
- choisit logo et identité ;
- voit le statut de vérification.

---

# 34. Étape Objectif

Objectifs initiaux :

```text
Faire connaître ma marque
Obtenir des appels
Recevoir des messages
Visiter mon site
Promouvoir un produit
Promouvoir un événement
Obtenir des inscriptions
Inviter à un Live
```

Chaque objectif propose un CTA adapté.

---

# 35. Étape Contenu

L’annonceur peut :

- importer une vidéo ;
- importer une image ;
- choisir dans la bibliothèque ;
- ajouter un titre ;
- ajouter un texte ;
- choisir un CTA ;
- choisir une destination ;
- voir l’aperçu.

---

# 36. Étape Audience

Options simples :

```text
Audience recommandée
Lieu
Intérêt
Usage déclaré
Projet déclaré
Classe économique
Abonnements payants
Audience enregistrée
```

Le Studio explique chaque option.

---

# 37. Audience recommandée

Le système peut proposer une audience à partir :

- de la marque ;
- du secteur ;
- de l’objectif ;
- du territoire ;
- du produit ;
- des campagnes passées ;
- des catégories autorisées.

La proposition reste modifiable.

---

# 38. Exemples d’audiences

```text
Cocody + Orange + téléphones
Abidjan + formation professionnelle
Côte d’Ivoire + agriculture
Gold et Platine + projet automobile
Premium, Gold et Platine + entrepreneurs
```

---

# 39. Audience estimée

Le Studio affiche :

- taille estimée ;
- fourchette ;
- rareté ;
- coût relatif ;
- classes ;
- territoire ;
- risque de segment trop petit.

Il ne fournit jamais une liste nominative.

---

# 40. Étape Budget

Options :

- budget total ;
- budget quotidien ;
- durée ;
- date de début ;
- date de fin ;
- événements visés ;
- fréquence ;
- gain estimé par utilisateur.

---

# 41. Budget recommandé

Le Studio peut proposer :

```text
Essentiel
Recommandé
Impact fort
Personnalisé
```

Chaque proposition affiche :

- budget ;
- durée ;
- portée estimée ;
- événements estimés ;
- fréquence ;
- résultats attendus sans garantie.

---

# 42. Devis instantané

Le devis contient :

- montant ;
- taxes ou frais ;
- segment ;
- durée ;
- événements estimés ;
- classes ;
- coût du ciblage ;
- solde nécessaire ;
- date d’expiration ;
- version de règle.

---

# 43. Financement de la campagne

Flux :

```text
devis
→ solde vérifié
→ budget réservé
→ campagne financée
```

Si le solde est insuffisant :

- recharger ;
- réduire le budget ;
- enregistrer le brouillon.

---

# 44. Répartition économique

Le Studio peut masquer la répartition interne au parcours simple.

Le moteur applique :

```text
Budget publicitaire net
├── part Wasplex
└── enveloppe utilisateurs
```

Le partage officiel actuel est :

```text
50 % Wasplex
50 % utilisateurs
```

---

# 45. Répartition par classes

Poids généraux :

```text
Gratuit : 10 %
Premium : 20 %
Gold : 35 %
Platine : 35 %
```

Si seules certaines classes sont ciblées, les poids sont normalisés entre elles.

---

# 46. Gain unitaire

Avant activation :

```text
enveloppe classe
÷ quota d’événements qualifiés
= gain unitaire exact
```

Le gain est calculé, versionné et réservé avant diffusion.

---

# 47. Étape Vérification

Le Studio résume :

- marque ;
- objectif ;
- contenu ;
- audience ;
- budget ;
- durée ;
- CTA ;
- destination ;
- gain utilisateur ;
- règles ;
- solde ;
- statut technique.

---

# 48. Soumission

Après confirmation :

```text
budget réservé
→ campagne soumise
→ file de revue administrative
```

L’annonceur reçoit :

- référence ;
- délai indicatif ;
- statut ;
- bouton modifier si encore autorisé.

---

# 49. Campagne avancée

Fonctions :

- groupes de campagnes ;
- plusieurs segments ;
- plusieurs créations ;
- horaires ;
- fréquence ;
- budget par groupe ;
- objectifs multiples ;
- événements qualifiés ;
- classes ;
- territoires ;
- tests comparatifs ;
- calendrier ;
- suivi détaillé.

---

# 50. Campagnes Feed

Une campagne Feed peut utiliser :

- vidéo verticale ;
- image verticale ;
- CTA ;
- commentaire ;
- partage ;
- gain utilisateur ;
- quota ;
- fréquence ;
- ciblage.

---

# 51. Campagnes Live

Le Studio peut aussi créer :

- Live sponsorisé rémunéré ;
- segment ;
- durée ;
- blocs ;
- places ;
- budget ;
- annonce ;
- créateur ;
- programmation.

Le budget doit être payé et réservé avant programmation publique.

---

# 52. Future campagne Explorer

Réserver l’architecture pour :

- présence sponsorisée dans Explorer ;
- catégorie ;
- partenaire ;
- offre ;
- contenu public.

---

# 53. Cycle de vie de campagne

```text
draft
quoted
funding_required
funded
submitted
under_review
changes_requested
approved
scheduled
active
paused
completed
rejected
cancelled
refundable
refunded
archived
```

---

# 54. Revue administrative

L’administrateur vérifie :

- annonceur ;
- marque ;
- média ;
- texte ;
- destination ;
- produit ;
- catégorie ;
- segment ;
- budget ;
- durée ;
- format ;
- droits ;
- sécurité ;
- risque.

---

# 55. Décisions administratives

```text
approve
request_changes
reject
suspend
```

Une décision possède :

- motif ;
- détails ;
- acteur ;
- date ;
- statut ;
- éventuelles pièces.

---

# 56. Demande de correction

Le Studio affiche des remarques compréhensibles.

Exemples :

```text
Votre vidéo doit être verticale.
Le lien ne répond pas.
Le texte du bouton ne correspond pas à la destination.
Le segment est trop restreint.
La durée du média dépasse la limite.
```

L’annonceur peut :

```text
corriger
→ prévisualiser
→ resoumettre
```

---

# 57. Approbation et activation

Après approbation :

```text
CampaignApproved
→ programmation
→ Matching
→ Feed
```

La campagne devient active à la date prévue si :

- budget toujours disponible ;
- média disponible ;
- annonceur actif ;
- règles toujours valides ;
- intégrations opérationnelles.

---

# 58. Modification après approbation

Les modifications importantes créent une nouvelle version.

Exemples :

- média ;
- texte ;
- destination ;
- segment ;
- budget ;
- durée ;
- objectif.

Une nouvelle revue peut être exigée.

---

# 59. Pause

L’annonceur peut demander une pause.

Effets :

- nouvelles sélections stoppées ;
- réservations non consommées libérées selon règle ;
- événements déjà validés payés ;
- reporting conservé.

---

# 60. Annulation

Selon l’état :

- brouillon supprimable ;
- soumission annulable ;
- campagne active stoppée ;
- reliquat remboursable ;
- dépenses consommées conservées ;
- frais éventuels appliqués.

---

# 61. Solde et budget en temps réel

Le Studio affiche :

- budget initial ;
- réservé ;
- consommé ;
- disponible ;
- remboursable ;
- coût moyen ;
- événements ;
- progression.

---

# 62. Reporting simple

Pour les non-techniciens :

- personnes atteintes ;
- vues ;
- complétions ;
- interactions ;
- appels ;
- messages ;
- visites ;
- budget consommé ;
- budget restant ;
- résultat principal.

---

# 63. Reporting avancé

Pour les professionnels :

- impressions réelles ;
- livraisons ;
- attention qualifiée ;
- abandons ;
- fréquence ;
- portée ;
- coût ;
- CTA ;
- territoire agrégé ;
- classe agrégée ;
- créatif ;
- période ;
- appareil agrégé ;
- heure ;
- comparaison.

---

# 64. Confidentialité du reporting

L’annonceur ne reçoit jamais :

- noms ;
- numéros ;
- emails ;
- Wallet ;
- KYC ;
- dossier Santé ;
- Alertes ;
- Fonds ;
- historique individuel ;
- position précise inutile.

---

# 65. “Pourquoi cette audience ?”

Le Studio explique les choix de ciblage.

Exemple :

```text
Cette audience combine :
- utilisateurs à Cocody ;
- utilisateurs ayant déclaré Orange ;
- personnes intéressées par les téléphones ;
- classes Gold et Platine.
```

Il ne révèle pas les profils individuels.

---

# 66. Audiences enregistrées

L’annonceur peut enregistrer :

- nom ;
- description ;
- critères ;
- taille estimée ;
- dernière utilisation ;
- version.

Une audience est recalculée à chaque nouvelle campagne.

---

# 67. Modèles de campagne

L’annonceur peut sauvegarder :

- objectif ;
- audience ;
- budget ;
- durée ;
- CTA ;
- marque ;
- type de média.

Les modèles n’incluent pas automatiquement un budget réservé.

---

# 68. Duplication

Une campagne peut être dupliquée.

La copie devient un nouveau brouillon.

Elle doit être :

- revalidée ;
- refinancée ;
- reprogrammée.

---

# 69. Gestion d’équipe

Rôles possibles :

```text
owner
administrator
marketing_manager
campaign_creator
creative_editor
finance_manager
analyst
approver
agency_member
read_only
```

Les droits réels utilisent des capacités.

---

# 70. Capacités annonceur

Exemples :

```text
advertiser.brand.view
advertiser.brand.manage
advertiser.media.upload
advertiser.campaign.create
advertiser.campaign.submit
advertiser.campaign.pause
advertiser.campaign.cancel
advertiser.wallet.view
advertiser.wallet.fund
advertiser.billing.view
advertiser.reporting.view
advertiser.team.manage
advertiser.approval.execute
```

---

# 71. Validation interne entreprise

Une grande entreprise peut exiger :

```text
créateur
→ responsable marketing
→ finance
→ soumission Wasplex
```

Le workflow est configurable.

---

# 72. Agences

Une agence peut :

- gérer plusieurs organisations clientes ;
- changer de client ;
- gérer plusieurs marques ;
- limiter les accès ;
- produire des rapports ;
- soumettre pour validation client ;
- utiliser un Wallet distinct par client.

Aucune confusion financière entre clients.

---

# 73. Notifications

Notifications :

- dépôt reçu ;
- solde faible ;
- campagne soumise ;
- correction demandée ;
- campagne approuvée ;
- campagne active ;
- budget faible ;
- campagne terminée ;
- remboursement ;
- anomalie ;
- invitation d’équipe ;
- Live programmé.

---

# 74. Centre d’aide

Le Studio doit proposer :

- guides ;
- exemples ;
- info-bulles ;
- assistant pas à pas ;
- erreurs claires ;
- FAQ ;
- support ;
- modèles ;
- recommandations.

---

# 75. Mode débutant

Règles :

- vocabulaire simple ;
- peu de champs à la fois ;
- recommandations ;
- sauvegarde automatique ;
- aperçu ;
- erreurs immédiates ;
- barre d’avancement ;
- aucune exposition des concepts comptables complexes.

---

# 76. Mode expert

Règles :

- tableaux ;
- filtres ;
- actions groupées ;
- segments ;
- horaires ;
- variantes ;
- exports ;
- API future ;
- permissions avancées.

---

# 77. Recherche globale du Studio

Rechercher :

- marque ;
- campagne ;
- média ;
- facture ;
- transaction ;
- membre ;
- audience ;
- Live.

---

# 78. Administration du Studio

Le back-office Wasplex peut :

- vérifier les annonceurs ;
- vérifier les marques ;
- modérer les médias ;
- approuver les campagnes ;
- demander des corrections ;
- suspendre ;
- rembourser ;
- consulter les budgets ;
- voir les anomalies ;
- gérer les catégories ;
- gérer les seuils ;
- gérer les modèles.

---

# 79. Sécurité

- MFA pour fonctions sensibles ;
- comptes nominatifs ;
- sessions ;
- capacités ;
- audit ;
- liens signés ;
- validation des fichiers ;
- anti-replay ;
- idempotence ;
- séparation des Wallets ;
- aucune modification directe du solde ;
- contrôle des destinations.

---

# 80. Antifraude

Signaux :

- dépôts falsifiés ;
- annonceurs liés ;
- campagnes dupliquées anormalement ;
- destination frauduleuse ;
- média interdit ;
- faux trafic ;
- collusion ;
- budget incohérent ;
- comportements inhabituels ;
- multi-comptes ;
- rechargements suspects.

Décisions :

```text
allow
monitor
hold
review
deny
```

---

# 81. Performance

- chargement progressif ;
- CDN média ;
- traitements asynchrones ;
- aperçus ;
- pagination ;
- index ;
- cache de taxonomies ;
- autosave ;
- upload résumable ;
- compression ;
- tableaux agrégés.

---

# 82. Accessibilité

- contraste ;
- clavier ;
- lecteur d’écran ;
- labels ;
- étapes claires ;
- messages d’erreur ;
- aperçu accessible ;
- sous-titres ;
- réduction des animations.

---

# 83. Modèle de données

Entités recommandées :

```text
advertiser_spaces
advertiser_organizations
advertiser_profiles
advertiser_wallets
advertiser_wallet_deposits
advertiser_wallet_projections

brands
brand_versions
brand_assets
brand_colors
brand_typographies
brand_guidelines

creative_assets
creative_asset_versions
creative_processing_jobs
creative_moderation_cases

campaigns
campaign_versions
campaign_objectives
campaign_creatives
campaign_audiences
campaign_audience_versions
campaign_quotes
campaign_fundings
campaign_budget_reservations
campaign_review_cases
campaign_review_events
campaign_schedules
campaign_status_events

advertiser_team_members
advertiser_capability_grants
advertiser_internal_approvals
advertiser_reports
advertiser_exports
advertiser_audit_events
```

---

# 84. Champs — Brand

```text
id
advertiser_space_id
name
legal_name
sector
description
status
country_code
website
verified_at
created_at
```

---

# 85. Champs — Campaign

```text
id
advertiser_space_id
brand_id
type
objective_code
status
currency
budget_amount
scheduled_start
scheduled_end
created_by
created_at
```

---

# 86. Champs — Campaign Version

```text
id
campaign_id
version_number
creative_configuration
audience_configuration
budget_configuration
cta_configuration
rule_version_id
status
created_at
```

---

# 87. Champs — Campaign Quote

```text
id
campaign_version_id
currency
gross_amount
net_distributable_amount
estimated_events
estimated_reach_min
estimated_reach_max
expires_at
rule_version_id
status
```

---

# 88. API Studio

```text
GET    /api/advertiser/dashboard
GET    /api/advertiser/profile
PATCH  /api/advertiser/profile

GET    /api/advertiser/brands
POST   /api/advertiser/brands
GET    /api/advertiser/brands/{id}
PATCH  /api/advertiser/brands/{id}

GET    /api/advertiser/assets
POST   /api/advertiser/assets
GET    /api/advertiser/assets/{id}
DELETE /api/advertiser/assets/{id}
```

---

# 89. API Wallet annonceur

```text
GET    /api/advertiser/wallet
GET    /api/advertiser/wallet/transactions
POST   /api/advertiser/wallet/deposits
GET    /api/advertiser/wallet/deposits/{id}
POST   /api/advertiser/wallet/transfers-from-personal
GET    /api/advertiser/billing
```

---

# 90. API campagnes

```text
GET    /api/advertiser/campaigns
POST   /api/advertiser/campaigns
GET    /api/advertiser/campaigns/{id}
PATCH  /api/advertiser/campaigns/{id}

POST   /api/advertiser/campaigns/{id}/estimate-audience
POST   /api/advertiser/campaigns/{id}/quote
POST   /api/advertiser/campaigns/{id}/fund
POST   /api/advertiser/campaigns/{id}/submit
POST   /api/advertiser/campaigns/{id}/resubmit
POST   /api/advertiser/campaigns/{id}/pause
POST   /api/advertiser/campaigns/{id}/cancel
POST   /api/advertiser/campaigns/{id}/duplicate

GET    /api/advertiser/campaigns/{id}/report
GET    /api/advertiser/campaigns/{id}/budget
```

---

# 91. API audiences

```text
GET    /api/advertiser/audiences
POST   /api/advertiser/audiences
GET    /api/advertiser/audiences/{id}
PATCH  /api/advertiser/audiences/{id}
POST   /api/advertiser/audiences/{id}/estimate
```

---

# 92. API équipe

```text
GET    /api/advertiser/team
POST   /api/advertiser/team/invitations
PATCH  /api/advertiser/team/{member}
DELETE /api/advertiser/team/{member}
POST   /api/advertiser/team/{member}/capabilities
```

---

# 93. API administration

```text
GET    /api/admin/advertisers
GET    /api/admin/advertisers/{id}
POST   /api/admin/advertisers/{id}/verify
POST   /api/admin/advertisers/{id}/restrict
POST   /api/admin/advertisers/{id}/restore

GET    /api/admin/brands
POST   /api/admin/brands/{id}/verify
POST   /api/admin/brands/{id}/reject

GET    /api/admin/campaign-reviews
GET    /api/admin/campaign-reviews/{id}
POST   /api/admin/campaign-reviews/{id}/approve
POST   /api/admin/campaign-reviews/{id}/request-changes
POST   /api/admin/campaign-reviews/{id}/reject
POST   /api/admin/campaigns/{id}/suspend
```

---

# 94. Événements métier

```text
AdvertiserSpaceCreated
AdvertiserVerified
AdvertiserRestricted

BrandCreated
BrandUpdated
BrandVerified
BrandRejected

AdvertiserWalletDepositCreated
AdvertiserWalletDepositConfirmed
AdvertiserWalletCredited

CampaignCreated
CampaignQuoted
CampaignFunded
CampaignSubmitted
CampaignChangesRequested
CampaignApproved
CampaignRejected
CampaignScheduled
CampaignActivated
CampaignPaused
CampaignCompleted
CampaignCancelled
CampaignRefunded

CreativeAssetUploaded
CreativeAssetProcessed
CreativeAssetApproved
CreativeAssetRejected

AdvertiserTeamMemberInvited
AdvertiserCapabilityGranted
AdvertiserCapabilityRevoked
```

---

# 95. Intégration avec le Super moteur

Événements :

```text
ADVERTISER_WALLET_DEPOSIT
CAMPAIGN_BUDGET_RESERVATION
CAMPAIGN_REWARD_RESERVATION
QUALIFIED_ATTENTION
CAMPAIGN_REFUND
LIVE_CAMPAIGN_FUNDING
```

---

# 96. Intégration avec le Grand Livre

Comptes recommandés :

```text
advertiser.wallet.available
advertiser.wallet.reserved
advertiser.deposit.pending
campaign.budget.reserved
campaign.user.envelope
campaign.wasplex.revenue
campaign.refundable
advertiser.promotion.credit
```

---

# 97. Tests fonctionnels

- activation espace annonceur ;
- création marque ;
- charte ;
- upload ;
- rechargement ;
- campagne rapide ;
- campagne avancée ;
- audience ;
- devis ;
- financement ;
- soumission ;
- correction ;
- approbation ;
- activation ;
- pause ;
- reporting ;
- équipe.

---

# 98. Tests campagne rapide

- utilisateur non technicien ;
- création en moins de cinq minutes ;
- mobile ;
- desktop ;
- autosave ;
- erreur média ;
- segment trop petit ;
- solde insuffisant ;
- correction ;
- aperçu.

---

# 99. Tests économiques

- Wallet séparé ;
- dépôt ;
- budget réservé ;
- partage 50/50 ;
- classes ;
- gain unitaire ;
- double réservation impossible ;
- pause ;
- reliquat ;
- remboursement ;
- Grand Livre ;
- aucun solde direct.

---

# 100. Tests de revue

- approuver ;
- demander correction ;
- rejeter ;
- resoumettre ;
- changement après approbation ;
- suspension ;
- audit ;
- notification.

---

# 101. Tests d’équipe

- invitation ;
- capacité ;
- expiration ;
- agence ;
- client ;
- validation interne ;
- finance ;
- lecture seule ;
- aucune fuite entre marques.

---

# 102. Tests de confidentialité

- aucun nom de spectateur ;
- aucun téléphone ;
- aucun email ;
- aucune donnée Santé ;
- aucune donnée Fonds ;
- aucune donnée Alertes ;
- résultats agrégés ;
- segment minimal.

---

# 103. Tests responsive

## Mobile

- 320 px ;
- 360 px ;
- 390 px ;
- création complète ;
- upload ;
- Wallet ;
- correction ;
- résultats.

## Desktop

- 1280 px ;
- 1440 px ;
- navigation ;
- formulaire + aperçu ;
- tableaux ;
- bibliothèque ;
- rapports ;
- équipe.

## Tablette

- 768 px ;
- 1024 px ;
- création ;
- gestion ;
- validation.

---

# 104. Captures obligatoires

1. accueil mobile ;
2. accueil desktop ;
3. création de marque ;
4. charte graphique ;
5. bibliothèque ;
6. Wallet annonceur ;
7. rechargement ;
8. campagne rapide étape 1 ;
9. audience ;
10. budget ;
11. aperçu ;
12. devis ;
13. soumission ;
14. correction demandée ;
15. campagne approuvée ;
16. campagne active ;
17. reporting simple ;
18. reporting avancé ;
19. équipe ;
20. Live sponsorisé.

---

# 105. Critères d’acceptation

Le Studio est accepté lorsque :

1. un utilisateur peut activer un espace annonceur sans nouveau compte ;
2. le mobile est complet ;
3. le desktop est complet et stratégique ;
4. une marque peut être créée ;
5. une charte graphique peut être enregistrée ;
6. les médias sont conservés dans une bibliothèque ;
7. le Wallet annonceur est séparé ;
8. un rechargement peut être confirmé ;
9. une campagne rapide peut être créée en moins de cinq minutes ;
10. une campagne avancée existe ;
11. une audience peut être estimée ;
12. le budget peut être devisé ;
13. le budget est réservé avant soumission ;
14. l’administration peut demander une correction ;
15. la campagne approuvée entre dans le Feed ;
16. le reporting est agrégé ;
17. aucune identité utilisateur n’est remise ;
18. les équipes et capacités existent ;
19. les agences peuvent gérer plusieurs clients sans mélanger les fonds ;
20. les tests critiques passent.

---

# 106. Ordre d’implémentation

## Phase 1 — Espace annonceur

- activation ;
- organisation ;
- navigation ;
- responsive ;
- permissions.

## Phase 2 — Marques

- profil ;
- logo ;
- couleurs ;
- charte ;
- vérification.

## Phase 3 — Bibliothèque

- upload ;
- traitement ;
- aperçu ;
- validation technique.

## Phase 4 — Wallet annonceur

- solde ;
- dépôts ;
- réservations ;
- factures ;
- audit.

## Phase 5 — Campagne rapide

- étapes ;
- audience ;
- budget ;
- aperçu ;
- autosave.

## Phase 6 — Revue administrative

- soumission ;
- file ;
- correction ;
- approbation ;
- suspension.

## Phase 7 — Feed

- activation ;
- Matching ;
- valeur ;
- reporting.

## Phase 8 — Campagne avancée

- segments ;
- variantes ;
- horaires ;
- équipes ;
- exports.

## Phase 9 — Live sponsorisé

- devis ;
- financement ;
- programmation ;
- reporting.

## Phase 10 — Stabilisation

- sécurité ;
- responsive ;
- accessibilité ;
- performance ;
- tests ;
- captures.

---

# 107. Première verticale à livrer

```text
Utilisateur active son espace annonceur
→ crée la marque GamaDeals
→ ajoute logo, couleurs et slogan
→ recharge 100 000 FCFA
→ choisit “Créer une campagne”
→ sélectionne Orange, Cocody, Gold et Platine
→ importe une vidéo verticale
→ voit le devis
→ confirme
→ budget réservé
→ soumet
→ administrateur approuve
→ campagne active dans le Feed
→ premier événement qualifié
→ reporting mis à jour
```

Cette verticale doit fonctionner :

- sur mobile ;
- sur desktop ;
- avec un utilisateur non technicien ;
- avec un vrai budget réservé ;
- avec approbation administrative ;
- avec diffusion Feed réelle.

---

# 108. Directive pour Claude Code

1. lire Compte universel, Matching, Modèle publicitaire, Feed, Wallet, Super moteur, Live et Administration ;
2. auditer le nouveau dépôt ;
3. créer un espace annonceur distinct ;
4. créer une interface mobile complète ;
5. créer une interface desktop complète ;
6. séparer Wallet personnel et Wallet annonceur ;
7. construire la marque et la bibliothèque avant le générateur avancé ;
8. livrer d’abord la campagne rapide ;
9. bloquer toute diffusion sans budget réservé ;
10. connecter l’approbation administrative au Feed ;
11. ne jamais exposer les identités des utilisateurs ;
12. fournir migrations, API, tests et captures ;
13. ne pas coder les futurs outils créatifs comme dépendance obligatoire de la V1.

---

# 109. Décision finale

Le Studio Annonceur Wasplex doit être à la fois :

```text
simple pour un débutant
complet pour une entreprise
rapide sur mobile
puissant sur desktop
sécurisé financièrement
évolutif créativement
```

> **L’annonceur doit pouvoir passer de sa marque à une campagne ciblée et financée en moins de cinq minutes, puis disposer sur desktop d’un véritable studio de création, de gestion et d’analyse capable d’évoluer avec Wasplex.**
