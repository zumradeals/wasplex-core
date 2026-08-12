# P014-A — Fonds : socle grand public

**Branche :** `feat/p014-a-fonds-socle`  
**Base :** `main` après raccordement Alertes/Santé/P019 et documentation business  
**Source produit :** `docs/01-module-fonds-wasplex.md`  
**Dépendances :** P003 Wallet annonceur/paiements, P004 plans/classes, P011 valeur/temps réel, P012 reporting, P019 espaces professionnels et institutionnels  
**Statut :** cadrage validé — prêt à implémenter

---

## 1. Objectif

Livrer la première verticale réellement utilisable du module **Fonds** sans construire encore le moteur collectif complet.

P014-A doit permettre à un utilisateur grand public de :

1. comprendre Fonds en quelques secondes ;
2. voir les programmes disponibles ;
3. vérifier son éligibilité ;
4. adhérer à un programme ;
5. accepter un mandat clair de contributions automatiques plafonnées ;
6. choisir son plafond personnel dans les bornes du programme ;
7. consulter l'état de son adhésion ;
8. créer un vœu ;
9. sauvegarder un brouillon ;
10. soumettre le vœu ;
11. suivre la revue administrative.

P014-A doit aussi permettre à l'administration de configurer les programmes et catégories puis de revoir les premiers vœux.

Le moteur de collecte automatique, les débits collectifs réels, les paiements partenaires, la réserve et les litiges restent hors P014-A.

---

## 2. Décisions fondatrices validées

### 2.1. Accès strictement réservé aux abonnés payants

Fonds est réservé aux utilisateurs disposant d'un **abonnement Wasplex payant actif et éligible**.

Un compte gratuit peut :

- découvrir Fonds ;
- consulter une présentation publique des programmes si autorisé ;
- voir pourquoi il n'est pas éligible ;
- accéder au parcours de mise à niveau d'abonnement.

Il ne peut pas :

- adhérer ;
- accepter un mandat Fonds actif ;
- créer ou soumettre un vœu ;
- participer à une nouvelle collecte future.

L'éligibilité des plans Wasplex est configurable.

### 2.2. Programmes multiples et configurables

Fonds supporte plusieurs programmes simultanés.

Les noms comme Silver, Gold ou Platinum ne sont jamais codés en dur.

Chaque programme peut être :

```text
Brouillon
Actif
Désactivé
```

Un programme désactivé :

- n'accepte plus de nouvelle adhésion ;
- reste visible dans l'historique ;
- ne casse jamais les engagements déjà constitués ;
- conserve ses versions et ses mandats historiques.

### 2.3. Versionnement obligatoire

Toute modification d'une règle économique crée une nouvelle version du programme.

Un mandat accepté référence toujours la version exacte présentée à l'utilisateur.

Les changements de frais, plafonds, durée, ancienneté minimale, catégories ou règles de contribution ne réécrivent jamais silencieusement un ancien mandat.

### 2.4. Mandat automatique sans confirmation par vœu

L'adhésion Fonds comporte un **mandat explicite de contributions automatiques plafonnées**.

Une fois le mandat actif :

- l'utilisateur est informé avant une future collecte selon le délai configuré ;
- aucune confirmation individuelle n'est redemandée pour chaque vœu ;
- aucune contribution ne peut dépasser les limites acceptées ;
- le moteur futur devra respecter idempotence, plafonds et preuve du mandat.

Le mandat n'est jamais implicite.

### 2.5. Plafond personnel

Le programme définit :

- un minimum obligatoire ;
- un maximum autorisé.

L'utilisateur choisit son plafond personnel à l'intérieur de cet intervalle.

```text
minimum_programme <= plafond_personnel <= maximum_programme
```

L'interface doit traduire ce choix en langage humain avant confirmation.

### 2.6. Expiration de l'abonnement : grâce de 7 jours

Quand l'abonnement Wasplex payant expire :

```text
active -> grace_period -> suspended
```

La période de grâce dure **7 jours** par défaut.

Pendant `grace_period` :

- aucun nouveau vœu ;
- aucun nouvel engagement Fonds ;
- aucune inclusion dans une nouvelle collecte ;
- historique conservé ;
- soldes et apports conservés ;
- renouvellement autorisé ;
- engagements déjà constitués avant expiration conservés.

Si l'abonnement est renouvelé pendant la grâce :

```text
grace_period -> active
```

Sans perte d'ancienneté.

Après 7 jours sans renouvellement :

```text
grace_period -> suspended
```

La suspension ne supprime ni le mandat historique ni les obligations déjà engagées.

### 2.7. Réactivation

Une réactivation exige au minimum :

- abonnement payant actif et éligible ;
- mandat encore valide ou nouvelle acceptation ;
- programme toujours disponible pour réactivation ;
- aucune suspension fraude ;
- situation Fonds compatible avec les règles de régularisation.

Aucune migration automatique vers un autre programme n'est autorisée.

---

## 3. Doctrine UX grand public

Fonds est un module financier et solidaire complexe, mais l'utilisateur ne doit jamais avoir l'impression d'utiliser un logiciel comptable.

### 3.1. Principe d'interface

> **Montrer d'abord ce que l'utilisateur peut faire et ce que cela implique ; montrer la mécanique technique seulement lorsqu'elle aide à décider.**

Chaque écran doit répondre rapidement à quatre questions :

1. Où suis-je ?
2. Quel est mon état ?
3. Que puis-je faire maintenant ?
4. Qu'est-ce que cela change pour moi ?

### 3.2. Famille visuelle Wasplex

Fonds hérite strictement du socle `docs/00-identite-visuelle-wasplex.md` :

- fond bleu nuit / surfaces profondes ;
- bleu technologique et cyan pour navigation, information et progression ;
- orange/or réservé à la valeur, aux montants et aux actions financières importantes ;
- mêmes rayons, espacements, cartes, ombres et typographie que Wallet et Mon Espace ;
- mêmes composants de boutons, alertes, feuilles modales, champs et loaders ;
- aucune palette « associative » ou « bancaire » parallèle ;
- aucune nouvelle identité visuelle propre à Fonds.

Le module doit être identifiable comme **Wasplex**, pas comme une application dans l'application.

### 3.3. Ton grand public

Éviter en premier niveau les termes :

- snapshot ;
- idempotence ;
- passif ;
- exposition ;
- instrument ;
- débitabilité ;
- version contractuelle.

Préférer :

- « Ton programme » ;
- « Ton plafond » ;
- « Ce que tu peux contribuer au maximum » ;
- « Ton adhésion » ;
- « Ton mandat » ;
- « Ton vœu » ;
- « Ce qu'il te reste à faire » ;
- « En attente de vérification ».

Les termes juridiques complets restent accessibles dans les détails et conditions.

### 3.4. Progressive disclosure

Les écrans montrent une information simple puis permettent d'ouvrir les détails.

Exemple programme :

```text
Gold
Jusqu'à 2 vœux actifs
Plafond de contribution : 500 F / collecte
Apport minimum : 20 %
[Voir les détails]
[Choisir ce programme]
```

Pas de mur de 18 paramètres sur la première carte.

### 3.5. Montants

Tous les montants affichent :

- séparateur de milliers ;
- devise ;
- libellé explicite ;
- jamais de chiffre sans contexte ;
- jamais de couleur seule pour porter le sens.

### 3.6. Accessibilité et prise en main

Obligatoire :

- boutons tactiles confortables ;
- contraste élevé ;
- textes essentiels >= taille standard de l'application ;
- pas d'action financière cachée derrière un geste ambigu ;
- étapes courtes ;
- retour arrière sans perte du brouillon ;
- erreurs placées près du champ concerné ;
- confirmation récapitulative avant adhésion et avant soumission d'un vœu ;
- `prefers-reduced-motion` respecté ;
- aucune animation bloquante.

---

## 4. Écran d'accueil Fonds P014-A

### 4.1. Non adhérent éligible

Première hauteur mobile :

- titre « Fonds » ;
- phrase simple de valeur ;
- statut « Tu peux adhérer » ;
- carte des programmes actifs ;
- CTA principal « Découvrir les programmes » ou « Choisir mon programme ».

Puis :

- « Comment ça marche » en 3 étapes ;
- exemple pédagogique non contractuel ;
- règles essentielles ;
- FAQ courte.

### 4.2. Non éligible

Ne pas afficher une erreur sèche.

Afficher :

- raison exacte ;
- abonnement actuel ;
- condition manquante ;
- CTA vers l'abonnement payant éligible.

### 4.3. Adhérent actif

Première hauteur :

- programme ;
- statut ;
- plafond personnel ;
- état du mandat ;
- bouton principal « Déclarer un vœu » ;
- nombre de vœux disponibles.

Ensuite :

- mes vœux ;
- prochaines actions ;
- règles de mon programme ;
- paramètres Fonds.

### 4.4. Grâce

Bannière claire mais non alarmiste :

> « Ton abonnement Wasplex a expiré. Renouvelle-le avant le [date] pour garder ton adhésion Fonds active. Pendant ce délai, aucun nouveau vœu ni nouvel engagement ne peut être créé. »

CTA : `Renouveler mon abonnement`.

### 4.5. Suspendu

Afficher :

- motif ;
- éléments conservés ;
- éventuelles actions nécessaires ;
- CTA de réactivation si autorisé.

---

## 5. Comparaison des programmes

L'administration peut créer autant de programmes que nécessaire.

La comparaison utilisateur doit rester simple.

### Première lecture

Chaque carte présente seulement :

- nom ;
- courte promesse descriptive ;
- prix d'adhésion ;
- durée ;
- nombre de vœux ;
- valeur maximale d'un vœu ;
- apport minimal ;
- plage de plafond personnel ;
- CTA.

### Détails

Un panneau secondaire présente :

- ancienneté minimale ;
- catégories ;
- plafonds par période ;
- délai d'information ;
- frais Wasplex ;
- règles de suspension ;
- conditions de renouvellement.

Aucune comparaison ne doit présenter un programme comme garantissant la réalisation d'un vœu.

---

## 6. Parcours d'adhésion

Parcours recommandé :

```text
Programme
-> Mon plafond
-> Comment fonctionnent les contributions
-> Mandat et règles essentielles
-> Récapitulatif
-> Confirmation
```

Maximum souhaité : 5 écrans courts, avec indicateur de progression.

### Récapitulatif obligatoire

Avant confirmation :

- programme ;
- prix ;
- durée ;
- plafond choisi ;
- minimum/maximum du programme ;
- délai d'information avant contribution ;
- règle « pas de confirmation à chaque vœu » clairement visible ;
- date/version des conditions ;
- lien vers conditions complètes.

L'action finale ne doit pas être nommée seulement « Continuer ».

Préférer :

> **« J'accepte le mandat et j'adhère »**

---

## 7. Vœux P014-A

### 7.1. Périmètre

P014-A couvre :

- catégories administrables ;
- création ;
- brouillon ;
- modification du brouillon ;
- justificatifs de base ;
- budget estimatif ;
- apport envisagé ;
- soumission ;
- demande d'informations complémentaires ;
- validation ou rejet administratif de première intention ;
- chronologie simple.

### 7.2. Parcours grand public

Ne pas reproduire les dix étapes techniques de la spécification sous forme de dix pages obligatoires.

Regrouper en 4 étapes :

```text
1. Mon besoin
2. Montant et apport
3. Justificatifs
4. Vérifier et envoyer
```

Le formulaire adapte les champs à la catégorie.

### 7.3. Catégories

Les catégories viennent de l'administration.

Chaque catégorie peut définir :

- nom ;
- icône ;
- description ;
- statut ;
- ordre ;
- justificatifs demandés ;
- budget minimum/maximum éventuel ;
- programmes compatibles.

Aucune liste de catégories ne doit être figée dans Vue ou PHP.

### 7.4. États P014-A

Le sous-ensemble initial est :

```text
draft
submitted
under_review
information_required
approved_for_next_stage
rejected
cancelled
expired
```

Les états de collecte, financement, commande et livraison arrivent dans les sous-chantiers suivants.

Chaque changement d'état doit être audité.

---

## 8. Administration P014-A

### 8.1. Programmes

L'admin peut :

- créer ;
- modifier par nouvelle version ;
- prévisualiser ;
- activer ;
- désactiver ;
- ordonner ;
- configurer les plans d'abonnement éligibles ;
- configurer les plafonds ;
- configurer durée, prix, délai de grâce et délai d'information ;
- définir les catégories compatibles.

Une version déjà utilisée par un mandat ne peut pas être réécrite.

### 8.2. Catégories

L'admin peut :

- créer ;
- activer/désactiver ;
- ordonner ;
- définir les justificatifs ;
- définir les programmes compatibles.

### 8.3. Vœux

File de revue claire :

- identité du dossier ;
- catégorie ;
- montant ;
- programme ;
- date ;
- complétude ;
- risques/alertes ;
- action suivante.

Actions P014-A :

- ouvrir ;
- demander des informations ;
- approuver pour l'étape suivante ;
- rejeter avec motif ;
- suspendre si investigation nécessaire.

Les décisions sensibles exigent capacités dédiées et MFA récent selon les conventions admin existantes.

---

## 9. Raccordement P019

P019 n'est pas encore nécessaire pour l'adhésion citoyenne elle-même.

P014-A doit cependant préparer les références nécessaires afin que P014-C puisse brancher :

- prestataires ;
- fournisseurs ;
- établissements médicaux ;
- établissements éducatifs ;
- partenaires financiers ;
- logistique ;
- contrôleurs ;
- ONG/associations ;
- collectivités.

Principe :

> Fonds reste propriétaire des vœux, devis, commandes et réalisations ; P019 fournit l'identité professionnelle vérifiée, les rôles, capacités, territoires et points de service.

Aucune donnée Fonds sensible n'est copiée dans Identity/P019.

---

## 10. Modèle de données P014-A proposé

Tables initiales :

```text
fund_programs
fund_program_versions
fund_program_subscription_plans
fund_wish_categories
fund_program_categories
fund_memberships
fund_mandates
fund_member_limits
fund_wishes
fund_wish_documents
fund_wish_reviews
```

### Principes

- ULID conformément aux conventions actuelles ;
- montants stockés en entier dans l'unité mineure disponible, FCFA sans décimales ;
- devise explicite ;
- règles de programme versionnées ;
- conditions/mandats versionnés ;
- documents sensibles protégés ;
- aucun solde collectif fictif ;
- aucun débit collectif réel dans P014-A.

Le Solde Fonds financier réel doit réutiliser le Grand Livre existant et sera raccordé dans P014-B ; P014-A peut exposer l'état « non initialisé » plutôt que créer une comptabilité parallèle.

---

## 11. Capacités proposées

### Utilisateur

Les actions self-service restent contrôlées par ownership plutôt que par rôles administratifs génériques.

### Administration

```text
admin.funds.view
admin.funds.programs.manage
admin.funds.categories.manage
admin.funds.wishes.review
admin.funds.wishes.decide
```

P014-A ne crée pas encore :

```text
fund.collection.execute
fund.reserve.allocate
fund.partner.payment.execute
```

---

## 12. API P014-A proposées

### Utilisateur

```text
GET    /api/funds
GET    /api/funds/programs
GET    /api/funds/membership
POST   /api/funds/membership
POST   /api/funds/membership/revoke
GET    /api/funds/wish-categories
GET    /api/funds/wishes
POST   /api/funds/wishes
GET    /api/funds/wishes/{wish}
PATCH  /api/funds/wishes/{wish}
POST   /api/funds/wishes/{wish}/submit
POST   /api/funds/wishes/{wish}/cancel
```

### Administration

```text
GET    /api/admin/funds/programs
POST   /api/admin/funds/programs
POST   /api/admin/funds/programs/{program}/versions
POST   /api/admin/funds/programs/{program}/activate
POST   /api/admin/funds/programs/{program}/disable
GET    /api/admin/funds/categories
POST   /api/admin/funds/categories
PATCH  /api/admin/funds/categories/{category}
GET    /api/admin/funds/wishes
GET    /api/admin/funds/wishes/{wish}
POST   /api/admin/funds/wishes/{wish}/request-information
POST   /api/admin/funds/wishes/{wish}/approve-next-stage
POST   /api/admin/funds/wishes/{wish}/reject
```

Les chemins peuvent être ajustés aux conventions réelles du module lors de l'implémentation.

---

## 13. Événements métier P014-A

```text
FundProgramCreated
FundProgramVersionPublished
FundProgramActivated
FundProgramDisabled
FundMembershipStarted
FundMembershipEnteredGracePeriod
FundMembershipSuspended
FundMembershipReactivated
FundMandateAccepted
FundMandateRevoked
FundWishCreated
FundWishSubmitted
FundWishInformationRequested
FundWishApprovedForNextStage
FundWishRejected
FundWishCancelled
```

Ces événements alimentent audit et notifications sans créer de transaction financière fictive.

---

## 14. Tests obligatoires

### Programmes

- brouillon non adhérable ;
- actif adhérable ;
- désactivé non adhérable ;
- ancienne version immuable après mandat ;
- modification => nouvelle version ;
- plan gratuit non éligible ;
- plan payant éligible configurable.

### Adhésion

- utilisateur gratuit refusé ;
- utilisateur payant accepté ;
- plafond sous minimum refusé ;
- plafond au-dessus du maximum refusé ;
- mandat référence la bonne version ;
- révocation bloque les nouveaux engagements ;
- aucune confirmation par vœu n'est créée artificiellement.

### Grâce

- expiration abonnement => `grace_period` ;
- nouveaux vœux bloqués immédiatement ;
- renouvellement avant 7 jours => `active` ;
- ancienneté conservée ;
- après délai => `suspended` ;
- historique conservé ;
- engagements antérieurs non supprimés.

### Vœux

- catégorie désactivée refusée ;
- brouillon privé ;
- propriétaire seul peut modifier ;
- soumis non modifiable librement ;
- revue admin auditée ;
- rejet avec motif ;
- aucun vœu ne déclenche de débit dans P014-A.

### UI

- mobile 360/390 px ;
- desktop ;
- non éligible ;
- éligible non adhérent ;
- adhérent ;
- grâce ;
- suspendu ;
- programme unique ;
- plusieurs programmes ;
- longues traductions/valeurs ;
- erreurs réseau ;
- chargement ;
- absence de catégories ;
- navigation clavier et contraste.

---

## 15. Hors périmètre P014-A

- collecte automatique réelle ;
- snapshot de collecte ;
- contribution à régulariser réelle ;
- frais Wasplex encaissés ;
- Solde Fonds comptable complet ;
- apport personnel Ledger ;
- réserve ;
- P019 partenaire opérationnel ;
- devis partenaire ;
- commande ;
- paiement par étapes ;
- livraison ;
- garantie ;
- litige ;
- remboursement ;
- indice de réciprocité calculé.

Ces fonctions sont prévues dans P014-B à P014-E.

---

## 16. Découpage après P014-A

### P014-B — Apport personnel & Grand Livre

- Solde Fonds réel ;
- compartiments d'apport ;
- versements ;
- échéanciers ;
- restitution ;
- écritures Ledger.

### P014-C — Partenaires P019 & devis

- organisation vérifiée ;
- capacités ;
- demandes de devis ;
- réponse ;
- comparaison ;
- sélection.

### P014-D — Collecte automatique

- instantané ;
- participants débitables ;
- calcul ;
- plafonds ;
- débits ;
- frais ;
- idempotence ;
- régularisation.

### P014-E — Réalisation

- commandes ;
- paiements par étapes ;
- preuves ;
- réserve ;
- livraison ;
- litiges ;
- clôture.

---

## 17. Critères de sortie P014-A

P014-A est prêt à fusionner quand :

1. les programmes sont multiples, configurables, activables et désactivables ;
2. les versions utilisées par des mandats sont immuables ;
3. seuls les abonnés payants éligibles peuvent adhérer ;
4. le mandat automatique plafonné est explicite ;
5. le plafond personnel respecte les bornes du programme ;
6. la grâce de 7 jours fonctionne sans créer de nouveaux engagements ;
7. les vœux ont un vrai cycle brouillon -> soumission -> revue ;
8. aucune opération financière collective fictive n'existe ;
9. l'administration peut configurer programmes et catégories ;
10. l'administration peut revoir les vœux avec audit ;
11. le mobile est prioritaire et immédiatement compréhensible ;
12. Fonds respecte la famille visuelle Wasplex ;
13. aucun écran ne promet la réalisation automatique d'un vœu ;
14. les tests backend, frontend, types, build et formatage sont verts.

---

## 18. Règle design de clôture

Une fonctionnalité P014-A n'est pas considérée terminée uniquement parce que l'API fonctionne.

Elle doit également être :

- compréhensible sans explication orale ;
- cohérente avec Wallet, Feed et Mon Espace ;
- agréable à utiliser sur téléphone ;
- rassurante pour une opération impliquant de l'argent ;
- claire sur ce qui est automatique ;
- claire sur ce qui est plafonné ;
- claire sur ce qui n'est pas garanti.

> **La complexité reste dans le moteur. La simplicité appartient à l'utilisateur.**
