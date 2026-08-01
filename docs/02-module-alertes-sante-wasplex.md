# WASPLEX — MODULE ALERTES & SANTÉ

**Fichier cible dans le nouveau dépôt :** `docs/03-alertes-sante/00-module-alertes-sante-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique prête au codage  
**Priorité :** Module principal de la navigation utilisateur  
**Navigation officielle :** Feed — Fonds — Wallet — Alertes — Mon Espace  
**Position de Santé :** Santé est accessible à l’intérieur d’Alertes, pas comme sixième onglet principal  
**Plateformes :** Mobile-first pour les citoyens, desktop-first pour les institutions et l’administration  
**Principe :** Un dossier source unique, plusieurs projections contrôlées, des états prouvés et aucune confusion entre communauté, institutions, publicité et santé

---

# 1. OBJET DU DOCUMENT

Ce document définit la nouvelle construction du module **Alertes & Santé** de Wasplex.

Il rassemble et transforme en spécification directement codable :

- les alertes communautaires ;
- les déclarations de sécurité ;
- les SOS ;
- les personnes disparues ;
- les objets et documents perdus ou retrouvés ;
- les véhicules volés ou retrouvés ;
- le routage vers la police, la gendarmerie, les secours et les institutions ;
- la contribution de la communauté Wasplex ;
- la diffusion dans le Feed ;
- la visibilité renforcée payante ;
- l’interopérabilité entre commissariats et brigades ;
- les restitutions protégées ;
- la capsule médicale d’urgence ;
- la première fondation de Wasplex Santé ;
- les portails institutionnels ;
- le pilotage administratif ;
- les API, événements, données, capacités et tests.

Ce document ne demande pas la création d’une nouvelle Constitution, d’un nouvel amendement général ni d’une série de textes bloquants.

La méthode est :

> **écran → parcours → état → donnée → capacité → preuve → code → test**

---

# 2. VISION PRODUIT

Wasplex Alertes & Santé doit relier quatre forces :

```text
Citoyen
→ crée un signalement, suit son dossier et contrôle ses informations

Communauté Wasplex
→ voit, partage, suit et transmet des pistes structurées

Institutions
→ reçoivent, qualifient, prennent en charge, coordonnent et clôturent

Wasplex
→ orchestre le routage, les projections, la preuve, la diffusion et l’interopérabilité
```

La formulation fondatrice est :

> **Wasplex permet à une déclaration citoyenne de devenir simultanément un dossier institutionnel exploitable et, lorsque cela est sûr, une mobilisation communautaire contrôlée, sans exposer la vie privée de la personne.**

---

# 3. NAVIGATION ET POSITION DE SANTÉ

## 3.1. Barre principale

La barre principale reste :

1. Feed
2. Fonds
3. Wallet
4. Alertes
5. Mon Espace

Le Wallet reste au centre.

## 3.2. Navigation interne d’Alertes

Structure cible :

```text
Alertes
├── Pour vous
├── Communauté
├── SOS
├── Santé
└── Mes déclarations
```

### Pour vous

- alertes proches ;
- alertes suivies ;
- avis officiels ;
- conseils utiles ;
- informations institutionnelles ;
- alertes récemment mises à jour.

### Communauté

- objets ;
- documents ;
- véhicules ;
- personnes ;
- animaux si la catégorie est activée ;
- perdu ;
- trouvé ;
- volé ;
- retrouvé.

### SOS

- urgence médicale ;
- accident ;
- incendie ;
- agression ;
- braquage ;
- danger immédiat ;
- autre urgence configurée.

### Santé

- capsule médicale d’urgence ;
- identité patient ;
- consentements ;
- représentants ;
- accès à mon dossier ;
- futur dossier médical ;
- futurs services Santé.

### Mes déclarations

- brouillons ;
- déclarations soumises ;
- dossiers en revue ;
- dossiers transmis ;
- dossiers pris en charge ;
- correspondances ;
- restitutions ;
- dossiers résolus ;
- historique.

---

# 4. MÉMOIRE PRODUIT CONSERVÉE

Les anciens écrans Wasplex ont montré plusieurs idées à conserver :

- alertes comme destination principale ;
- catégories visuelles claires ;
- séparation entre annonces actives et résolues ;
- filtres ;
- cartes de proximité ;
- bouton de déclaration rapide ;
- distinction perdu/trouvé ;
- personnes disparues ou retrouvées ;
- véhicules volés ou retrouvés ;
- visibilité de la récompense éventuelle ;
- intégration au Feed ;
- petit rail vertical discret ;
- cercles d’alertes en haut du Feed ;
- insertion plein écran entre plusieurs publicités ;
- design mobile sombre.

À améliorer :

- aucune donnée sensible ne doit être publiée automatiquement ;
- les alertes vitales ne doivent jamais dépendre d’un paiement ;
- les états doivent correspondre à une preuve réelle ;
- la police et la gendarmerie doivent disposer de vrais portails ;
- les pistes communautaires doivent être structurées ;
- la diffusion dans le Feed doit rester distincte de la publicité ;
- Santé doit être intégrée dans l’expérience mais séparée techniquement ;
- le citoyen ne doit pas saisir deux fois la même déclaration.

À abandonner :

- fausse urgence ;
- fausse transmission ;
- fausse prise en charge ;
- coordonnées privées exposées ;
- diffusion payante donnant artificiellement une autorité ;
- compte collectif de commissariat ;
- recherche libre dans toute la base Wasplex ;
- mélange Santé, publicité, Wallet et sécurité dans une fiche universelle.

---

# 5. FRONTIÈRE ENTRE ALERTES ET SANTÉ

Le produit présente un univers cohérent.

Le code conserve deux domaines séparés.

```text
Alerts
├── déclarations
├── SOS
├── projections
├── routage
├── correspondances
├── restitutions
└── diffusion

Health
├── patients
├── représentants
├── consentements
├── professionnels
├── établissements
├── capsule médicale
├── accès
└── futur dossier longitudinal
```

Règles :

- Alertes ne lit jamais directement les tables Santé ;
- Santé ne lit jamais directement les tables Alertes ;
- Alertes demande une capsule d’urgence via un contrat ;
- Santé renvoie uniquement une projection médicale minimale autorisée ;
- Advertising n’accède jamais aux données Alertes ou Santé ;
- le Wallet ne contient aucune donnée médicale ;
- les institutions ne reçoivent que les champs nécessaires.

---

# 6. DOSSIER SOURCE UNIQUE

Chaque déclaration crée un seul dossier source.

Ce dossier peut produire plusieurs projections :

```text
Dossier source confidentiel
├── Projection citoyen
├── Projection communautaire
├── Projection institutionnelle
├── Projection Feed
└── Projection Santé d’urgence
```

## 6.1. Dossier source

Peut contenir :

- auteur ;
- personne concernée ;
- catégorie ;
- description détaillée ;
- position exacte ;
- pièces ;
- preuves ;
- coordonnées ;
- témoins ;
- relation familiale ;
- référence officielle ;
- niveau de vérification ;
- historique ;
- institutions associées.

## 6.2. Projection communautaire

Ne contient que les informations nécessaires au public.

## 6.3. Projection institutionnelle

Contient les informations nécessaires à l’institution selon :

- catégorie ;
- territoire ;
- finalité ;
- capacité ;
- niveau de prise en charge ;
- durée d’accès.

## 6.4. Projection Feed

Version courte, sûre, visuelle et adaptée au Feed.

## 6.5. Projection Santé

Capsule médicale minimale, temporaire et auditée.

---

# 7. CLASSIFICATION DES PRIORITÉS

Le système sépare :

```text
priorité_de_protection
niveau_de_diffusion
```

Le paiement ne modifie jamais la priorité de protection.

## P0 — Vitale immédiate

Exemples :

- urgence médicale grave ;
- incendie actif ;
- accident grave ;
- danger imminent ;
- braquage en cours ;
- catastrophe ;
- disparition critique d’un mineur.

Droits de diffusion :

- priorité absolue ;
- insertion immédiate possible ;
- notification territoriale possible ;
- première position dans les surfaces ;
- aucune dépendance au paiement.

## P1 — Protection publique prioritaire

Exemples :

- personne disparue validée ;
- véhicule officiellement recherché ;
- avis de police ;
- information de sécurité publique ;
- appel institutionnel urgent au sang ;
- alerte météorologique officielle.

## P2 — Sensible contrôlée

Exemples :

- mineur ;
- personne vulnérable ;
- conflit de garde ;
- document d’identité ;
- véhicule volé ;
- dossier médical nominatif ;
- dossier nécessitant une confidentialité renforcée.

P2 peut être très important sans être largement public.

## P3 — Communautaire vérifiée

Exemples :

- objet perdu ;
- objet trouvé ;
- document perdu ;
- animal perdu ;
- bien matériel.

## P4 — Communautaire avec visibilité renforcée

P4 est une alerte P3 vérifiée ayant acheté davantage de diffusion.

P4 ne dépasse jamais P0, P1 ou P2.

---

# 8. ALERTES DANS LE FEED

Alertes possède quatre surfaces dans l’écosystème utilisateur.

## 8.1. Onglet principal Alertes

Expérience complète :

- création ;
- recherche ;
- suivi ;
- statut ;
- contribution communautaire ;
- correspondance ;
- restitution ;
- historique.

## 8.2. Cercles Alertes en haut du Feed

Lorsque l’utilisateur touche Alertes en haut du Feed, afficher une rangée de cercles :

```text
[Proche] [Objets] [Véhicules] [Personnes] [Santé] [Officiel]
```

Chaque cercle peut afficher :

- icône ;
- miniature sûre ;
- bordure de catégorie ;
- badge nouveau ;
- badge officiel ;
- priorité ;
- zone approximative.

Comportement :

- ouverture rapide ;
- navigation verticale ;
- bouton suivre ;
- bouton partager ;
- bouton signaler ;
- accès au dossier complet.

Les alertes vues peuvent avoir une bordure différente.

Les alertes résolues ou expirées disparaissent.

## 8.3. Rail vertical discret

Le Feed conserve un petit rail vertical sur le côté droit.

Il présente des alertes courtes sans gêner la publicité.

Contenu possible :

- pictogramme ;
- miniature ;
- badge ;
- couleur ;
- pulse léger ;
- priorité ;
- expiration.

Au toucher :

- fiche compacte ;
- option ouvrir dans Alertes.

Le rail :

- ne bloque pas le scroll ;
- ne masque pas les actions sociales ;
- ne crédite aucun WP ;
- ne modifie pas la progression publicitaire ;
- n’affiche aucune donnée privée.

## 8.4. Insertion plein écran

Après une cadence configurable de publicités, Wasplex peut insérer :

- alerte communautaire validée ;
- avis officiel ;
- conseil de sécurité ;
- information Santé ;
- appel institutionnel ;
- conseil utile ;
- information publique.

Exemples de cadence :

```text
5 publicités
→ 1 contenu utile
→ 5 à 10 publicités
→ prochain contenu utile
```

La cadence est configurée depuis l’administration.

Une priorité P0 peut interrompre la cadence.

---

# 9. TYPES DE CONTENU DU FEED

Le Feed doit distinguer :

```text
advertisement
community_alert
official_notice
health_notice
safety_advice
live_content
```

Le module Live sera spécifié séparément.

Une alerte ou information institutionnelle :

- ne consomme pas le budget annonceur ;
- ne produit aucun événement publicitaire qualifié ;
- ne déclenche pas la barre de gain ;
- ne crédite aucun WP ;
- ne réduit pas le quota publicitaire ;
- ne compte pas comme publicité vue.

Lorsqu’une publicité est interrompue par une alerte :

- sa session est interrompue proprement ;
- aucun gain n’est attribué ;
- aucun comportement frauduleux n’est automatiquement déduit.

---

# 10. FILES DE DIFFUSION SÉPARÉES

Le moteur possède au minimum :

```text
File Protection vitale
File Information institutionnelle
File Communautaire standard
File Visibilité renforcée
```

Une visibilité payante utilise seulement la dernière file.

Elle ne peut pas consommer les emplacements réservés aux urgences.

Si une urgence prend la place d’une diffusion payante :

- la diffusion payante est suspendue ;
- son crédit ou temps non exécuté est conservé ;
- elle reprend ensuite ;
- l’auteur ne paie pas pour une impression non servie.

---

# 11. VISIBILITÉ RENFORCÉE

## 11.1. Nom produit

Ne pas utiliser « Alerte Premium » comme statut de vérité.

Utiliser :

> **Visibilité renforcée**

ou :

> **Diffusion renforcée**

## 11.2. Ce que le paiement peut acheter

- durée ;
- rayon ;
- présence dans les cercles ;
- présence dans le rail ;
- fréquence contrôlée ;
- éligibilité à l’insertion plein écran.

## 11.3. Ce qu’il ne peut pas acheter

- urgence ;
- validation ;
- statut officiel ;
- priorité institutionnelle ;
- publication d’un dossier sensible ;
- accès aux institutions ;
- diffusion nationale critique ;
- dépassement d’une alerte vitale.

## 11.4. Catégories autorisées

- objet ;
- document ;
- animal ;
- bien matériel ;
- véhicule après validation ;
- autre catégorie communautaire autorisée.

## 11.5. Catégories interdites à l’achat direct

- enfant disparu ;
- personne vulnérable ;
- SOS ;
- urgence médicale ;
- incendie ;
- braquage en cours ;
- appel au sang ;
- communiqué officiel ;
- conflit de garde ;
- information sanitaire nominative ;
- alerte nationale.

## 11.6. Parcours

```text
Alerte validée
→ choix de visibilité
→ prix
→ territoire
→ durée
→ paiement
→ activation
→ mesure
→ expiration
```

Le paiement peut provenir du Wallet.

## 11.7. Forfaits recommandés

- Local 24 h ;
- Local renforcé 72 h ;
- Régional ;
- Extension de durée ;
- Extension de rayon.

Les tarifs sont configurables.

---

# 12. DÉCLARATION CITOYENNE

## 12.1. Principe

Le citoyen crée une déclaration Wasplex.

Cette déclaration peut ensuite devenir :

- une déclaration communautaire ;
- une transmission institutionnelle ;
- une pré-plainte ;
- un dossier officiel lié ;
- un SOS ;
- une alerte publique ;
- une demande de correspondance.

## 12.2. Déclaration gratuite

Sont toujours gratuits :

- SOS ;
- braquage ;
- agression ;
- disparition ;
- accident ;
- incendie ;
- déclaration sécuritaire ;
- transmission de base ;
- suivi de base.

La visibilité renforcée reste facultative pour les catégories admises.

## 12.3. Distinction juridique

Le système distingue :

```text
Déclaration citoyenne Wasplex
```

de :

```text
Déclaration officiellement enregistrée par une institution
```

Sans accord officiel du gouvernement, Wasplex ne doit pas afficher qu’une plainte officielle est enregistrée.

---

# 13. PARCOURS — ENFANT DISPARU

## 13.1. Création

Koffi renseigne :

- identité de l’enfant ;
- photo ;
- âge ;
- relation ;
- dernière zone ;
- heure ;
- vêtements ;
- circonstances ;
- coordonnées ;
- éléments de vérification.

## 13.2. Qualification

Le système classe :

```text
Personne disparue
Mineur
P2 ou P0 selon les circonstances
Revue renforcée
Routage police/gendarmerie
Publication publique sous contrôle
```

## 13.3. Circuit institutionnel

- recherche des institutions compétentes ;
- création d’une projection institutionnelle ;
- transmission ;
- accusé ;
- prise en charge ;
- mise à jour ;
- transfert ;
- clôture.

## 13.4. Circuit communautaire

La communauté voit seulement :

- prénom public autorisé ;
- âge ;
- photo autorisée ;
- zone approximative ;
- heure ;
- vêtements ;
- source vérifiée ;
- statut.

## 13.5. Contribution communautaire

Actions :

- j’ai vu une personne correspondante ;
- j’ai une information ;
- partager ;
- suivre ;
- signaler un abus.

Les informations sensibles ne sont jamais publiées dans les commentaires.

---

# 14. PARCOURS — BRAQUAGE

## 14.1. Braquage en cours

Parcours SOS court :

- catégorie ;
- position ;
- description minimale ;
- rappel facultatif ;
- langue ;
- envoi.

Wasplex :

- affiche les numéros officiels configurés ;
- enregistre ;
- transmet ;
- affiche le statut réel ;
- ne publie pas automatiquement la position exacte ;
- peut créer une alerte territoriale institutionnelle.

## 14.2. Braquage terminé

Dossier plus détaillé :

- lieu ;
- heure ;
- objets ;
- véhicule ;
- description ;
- preuve ;
- référence de plainte éventuelle.

Peut produire :

- appel à témoins ;
- véhicule recherché ;
- objet recherché ;
- projection communautaire limitée.

---

# 15. SOS SANS COMPTE

Un SOS peut être créé sans authentification.

Données minimales :

- catégorie ;
- position si autorisée ;
- description ;
- numéro facultatif ;
- langue ;
- consentement minimal ;
- idempotency key.

Règles :

- rate limiting ;
- validation serveur ;
- statut non vérifié ;
- aucune publication automatique ;
- aucun profil publicitaire ;
- aucun Wallet ;
- aucun compte commercial créé ;
- numéros officiels affichés.

---

# 16. CONTRIBUTION POPULAIRE

La communauté ne remplace pas l’institution.

Elle peut :

- voir ;
- partager ;
- suivre ;
- signaler une correspondance ;
- fournir une piste ;
- confirmer une observation ;
- participer à une restitution.

## 16.1. Rapport de correspondance

Contenu :

- lieu ;
- date ;
- heure ;
- direction ;
- description ;
- photo éventuelle ;
- certitude ;
- rappel facultatif.

## 16.2. Traitement

```text
Rapport reçu
→ filtrage Wasplex
→ transmission à l’institution
→ piste en vérification
→ confirmée | écartée | transférée
```

La personne qui signale ne connaît pas les réponses secrètes attendues.

---

# 17. PORTAIL POLICE ET GENDARMERIE

## 17.1. Principe

Le portail permet à plusieurs commissariats, brigades et directions de coopérer sur un même dossier.

Modèle :

> **un propriétaire du dossier, plusieurs participants institutionnels**

## 17.2. Tableau de bord

Sections :

- urgences nouvelles ;
- déclarations citoyennes à qualifier ;
- dossiers reçus ;
- dossiers acceptés ;
- en traitement ;
- pistes communautaires ;
- correspondances ;
- transferts ;
- dossiers sans réponse ;
- dossiers résolus ;
- alertes territoriales ;
- avis officiels.

## 17.3. Fiche dossier

Afficher selon autorisation :

- référence Wasplex ;
- référence officielle ;
- institution source ;
- institution responsable ;
- catégorie ;
- priorité ;
- territoire ;
- déclarant ;
- relation ;
- projection publique ;
- vues ;
- partages ;
- pistes ;
- chronologie ;
- institutions participantes ;
- actions disponibles.

## 17.4. Actions

- recevoir ;
- accuser réception ;
- accepter ;
- refuser ;
- demander un complément ;
- transférer ;
- signaler une correspondance ;
- publier un avis officiel ;
- changer l’état ;
- clôturer ;
- retirer une projection.

---

# 18. INTEROPÉRABILITÉ ENTRE COMMISSARIATS

## 18.1. Exemple

Un véhicule est déclaré au commissariat A.

Le dossier contient :

```text
Référence Wasplex
Référence commissariat A
Institution propriétaire
Catégorie
Territoire
Statut
```

Wasplex crée une projection interinstitutionnelle.

Le commissariat B peut :

- recevoir ;
- accuser ;
- consulter la projection ;
- signaler une correspondance ;
- demander un accès complémentaire ;
- effectuer un contrôle ;
- confirmer une récupération.

## 18.2. Propriété

Le commissariat B ne réécrit pas les événements du commissariat A.

Il ajoute ses propres événements.

## 18.3. Transfert

```text
A demande un transfert
→ B accepte
→ supervision si nécessaire
→ B devient responsable
→ A conserve l’historique
```

---

# 19. NIVEAUX DE PARTAGE INSTITUTIONNEL

## 19.1. Local

- institution source ;
- unités voisines ;
- responsables locaux.

## 19.2. Régional

- commissariats ;
- brigades ;
- direction régionale ;
- unités spécialisées.

## 19.3. National

Activation réservée à une autorité habilitée.

Exigences :

- capacité critique ;
- MFA ;
- justification ;
- double validation possible ;
- territoire ;
- expiration.

## 19.4. Transfrontalier

Modèle :

```text
Institution locale
→ autorité nationale
→ passerelle nationale
→ autorité du pays destinataire
→ institutions locales autorisées
```

Aucun accès direct à toute la base d’un autre pays.

---

# 20. ESPACE SOUVERAIN PAR PAYS

Chaque pays possède un espace logique séparé :

```text
Wasplex Protection Côte d’Ivoire
Wasplex Protection Burkina Faso
Wasplex Protection Mali
Wasplex Protection Sénégal
...
```

Chaque espace configure :

- institutions ;
- territoires ;
- hiérarchie ;
- capacités ;
- catégories ;
- règles de partage ;
- conservation ;
- autorités nationales ;
- langue ;
- numéros officiels ;
- intégrations.

La plateforme peut être commune, mais les frontières de données sont strictes.

---

# 21. HIÉRARCHIE INSTITUTIONNELLE

Exemple configurable :

```text
Ministère / Commandement national
├── Direction nationale
├── Directions régionales
├── Commissariats
├── Districts
├── Brigades de gendarmerie
├── Unités spécialisées
└── Centres de coordination
```

Chaque pays conserve son propre modèle.

---

# 22. IDENTITÉ DES UTILISATEURS INSTITUTIONNELS

Aucun compte collectif :

```text
police_abidjan
gendarmerie
commissariat1
urgence1
```

Chaque utilisateur possède :

- identité ;
- organisation ;
- unité ;
- fonction ;
- territoire ;
- capacités ;
- durée ;
- appareil ;
- MFA ;
- historique.

Les comptes techniques API sont séparés des comptes humains.

---

# 23. RECHERCHE INSTITUTIONNELLE

La recherche peut porter sur :

- référence ;
- plaque ;
- document ;
- catégorie ;
- territoire ;
- période ;
- caractéristiques ;
- identité autorisée.

Elle exige :

- capacité ;
- motif ;
- territoire ;
- durée ;
- journal.

Sont interdites :

- recherche exploratoire ;
- extraction massive ;
- affichage de tous les citoyens ;
- affichage de toutes les personnes d’une zone ;
- recherche sans finalité.

---

# 24. MODE PORTAIL ET MODE INTÉGRATION

## 24.1. Mode portail

Pour les institutions sans système compatible :

- navigateur ;
- PWA ;
- interface légère ;
- réseau faible ;
- brouillons ;
- notifications ;
- synchronisation.

## 24.2. Mode API

Pour les gouvernements équipés :

```text
Système national
↔ Passerelle sécurisée Wasplex
↔ Réseau Alertes
```

Événements :

```text
case.created
case.transmitted
case.received
case.accepted
case.transferred
match.reported
case.processing
case.resolved
```

Chaque message contient :

- identifiant ;
- source ;
- destination ;
- catégorie ;
- territoire ;
- date ;
- version ;
- signature ;
- idempotency key ;
- accusé ;
- résultat.

---

# 25. ÉTATS INSTITUTIONNELS

États principaux :

```text
created
transmitted
received
accepted
processing
resolved
```

États latéraux :

```text
rejected
no_response
expired
cancelled
transferred
impossible
closed_unresolved
```

Règles :

- `transmitted` ne signifie pas `received` ;
- `received` ne signifie pas `accepted` ;
- `accepted` crée une responsabilité ;
- `processing` exige une action déclarée ;
- `resolved` exige une preuve.

---

# 26. VÉRITÉ AFFICHÉE AU CITOYEN

Exemples :

```text
Déclaration enregistrée
```

```text
Transmission institutionnelle non confirmée
```

```text
Réception confirmée par le commissariat
```

```text
Prise en charge acceptée
```

```text
Traitement déclaré en cours
```

Ne jamais afficher :

- secours en route sans preuve ;
- police prévenue sans transmission ;
- plainte officielle sans référence ;
- résolu sans clôture.

---

# 27. PROJECTION PUBLIQUE

Le service de publication est configuré par catégorie.

La politique définit :

- champs autorisés ;
- zone ;
- durée ;
- anonymisation ;
- revue ;
- autorité ;
- retrait ;
- expiration.

Ne jamais publier automatiquement :

- position exacte ;
- téléphone ;
- adresse ;
- pièce d’identité originale ;
- données médicales ;
- témoins ;
- preuves de propriété ;
- informations familiales privées ;
- réponses secrètes.

---

# 28. RESTITUTION PROTÉGÉE

Pour les objets, documents ou biens retrouvés :

```text
Correspondance proposée
→ vérification
→ lieu neutre
→ code unique
→ remise
→ confirmation du remettant
→ confirmation du bénéficiaire
→ clôture
```

Le code :

- est à usage unique ;
- expire ;
- est stocké sous forme de condensat ;
- ne peut pas être rejoué.

Un témoin facultatif peut participer sans recevoir de capacité générale.

---

# 29. RÉCOMPENSE VOLONTAIRE

Une récompense peut être proposée pour certaines alertes matérielles.

Règles :

- optionnelle ;
- financée à l’avance ;
- réservée dans le Wallet ;
- libérée après restitution confirmée ;
- bloquée en cas de litige ;
- jamais liée à un SOS vital ;
- jamais utilisée pour payer une action institutionnelle ;
- frais Wasplex configurable.

Cette fonctionnalité peut être désactivée au lancement.

---

# 30. MODÈLE ÉCONOMIQUE INSTITUTIONNEL

## 30.1. Gratuit pour le citoyen

- déclaration ;
- SOS ;
- transmission ;
- suivi ;
- contribution communautaire.

## 30.2. Pas de commission par dossier

Interdit :

- paiement à un commissariat par dossier ;
- paiement à un agent par acceptation ;
- prime Wasplex individuelle ;
- rémunération secrète d’une prise en charge.

## 30.3. Contrat institutionnel

Le client peut être :

- ministère ;
- direction générale ;
- commandement ;
- collectivité ;
- programme public ;
- bailleur ;
- organisation internationale ;
- partenaire télécom.

Le contrat peut couvrir :

- déploiement ;
- licence ;
- institutions actives ;
- support ;
- hébergement ;
- sécurité ;
- intégration ;
- formation ;
- équipements ;
- connectivité ;
- supervision ;
- statistiques.

## 30.4. Fonds d’infrastructure

Une fraction de revenus non vitaux peut alimenter un fonds d’infrastructure :

- visibilité renforcée ;
- partenariats ;
- abonnements ;
- licences ;
- services professionnels.

Ce fonds peut financer officiellement :

- appareils ;
- connectivité ;
- formation ;
- maintenance ;
- déploiement.

---

# 31. WASPLEX SANTÉ — PREMIÈRE VERSION

Le premier codage Santé ne construit pas tout le carnet médical.

Il construit :

- identité patient ;
- représentants ;
- établissements ;
- professionnels ;
- consentements ;
- capsule médicale d’urgence ;
- historique des accès ;
- passerelle SOS.

Le dossier longitudinal vient ensuite.

---

# 32. IDENTITÉ PATIENT

Un patient peut exister sans compte de connexion :

- nouveau-né ;
- mineur ;
- personne inconsciente ;
- personne créée par un établissement habilité.

Créer un sujet de soins protégé, pas un profil publicitaire.

Données :

- référence Identity ;
- statut ;
- territoire ;
- origine ;
- niveau de vérification ;
- fusion ou doublon ;
- aucune donnée publicitaire.

---

# 33. REPRÉSENTANTS

Le système modélise :

- représentant légal ;
- nature ;
- preuve ;
- début ;
- fin ;
- périmètre ;
- suspension ;
- contestation ;
- passage à la majorité.

Une relation familiale déclarée ne suffit pas.

---

# 34. PROFESSIONNELS ET ÉTABLISSEMENTS

Chaque professionnel est lié à :

- identité ;
- organisation ;
- profession ;
- spécialité ;
- habilitation ;
- territoire ;
- validité ;
- état.

Chaque établissement est une institution affiliée vérifiée.

Aucun professionnel ne parcourt librement tous les patients.

---

# 35. CAPSULE MÉDICALE D’URGENCE

La capsule contient uniquement :

- identité utile ;
- photo autorisée ;
- groupe sanguin vérifié ;
- allergies critiques ;
- pathologies critiques utiles ;
- traitements vitaux ;
- instructions urgentes ;
- contact d’urgence ;
- établissement ou médecin de référence ;
- provenance ;
- date de vérification.

Niveaux de provenance :

```text
Déclaré par l’utilisateur
Enregistré par un professionnel
Vérifié par un laboratoire
Vérifié par une institution
Périmé
```

Une déclaration utilisateur n’est pas un fait médical certifié.

---

# 36. BRIS DE GLACE MÉDICAL

Le bris de glace est un accès exceptionnel.

Conditions :

- professionnel habilité ;
- institution active ;
- MFA récente ;
- SOS identifié ;
- justification ;
- durée courte ;
- accès limité ;
- audit ;
- revue ;
- information du patient lorsque possible.

Il ne permet pas :

- dossier complet ;
- export massif ;
- publicité ;
- Wallet ;
- suppression de trace ;
- modification d’un résultat.

---

# 37. CARTE WASPLEX ET SANTÉ

La Carte Wasplex pourra devenir une clé d’identification et d’accès.

Parcours futur :

```text
Carte scannée
→ sujet de soins identifié
→ professionnel vérifié
→ demande d’urgence
→ autorisation
→ capsule ouverte
→ accès journalisé
→ expiration
```

La carte ne contient pas directement tout le dossier médical.

Le module Carte Wasplex sera spécifié séparément.

---

# 38. APPEL AU SANG

Une demande de sang :

- provient d’un établissement vérifié ;
- définit groupe, composant, zone et durée ;
- protège l’identité du patient ;
- sollicite des volontaires consentants ;
- laisse la compatibilité aux professionnels ;
- ne crédite aucun WP ;
- ne paie pas automatiquement le don ;
- peut apparaître comme information Santé institutionnelle.

---

# 39. PHASES SANTÉ ULTÉRIEURES

## Phase 2

- consultations ;
- allergies ;
- pathologies ;
- traitements ;
- vaccins ;
- documents.

## Phase 3

- laboratoires ;
- résultats ;
- prescriptions ;
- pharmacies ;
- assurance ;
- paiements médicaux.

## Phase 4

- demandes de sang ;
- volontaires ;
- orientation ;
- coordination institutionnelle.

Organes, tissus et cellules restent fermés et séparés.

---

# 40. ÉCRANS UTILISATEUR ALERTES

## 40.1. Accueil Alertes

- onglets ;
- recherche ;
- alertes proches ;
- officielles ;
- suivies ;
- catégories ;
- bouton déclarer ;
- bouton SOS.

## 40.2. Créer une alerte

Étapes :

- catégorie ;
- nature ;
- description ;
- localisation ;
- date ;
- média ;
- confidentialité ;
- récapitulatif ;
- soumission.

## 40.3. SOS

Écran très court.

## 40.4. Détail

- statut ;
- source ;
- zone ;
- chronologie ;
- partager ;
- suivre ;
- signaler ;
- institution ;
- résolution.

## 40.5. Mes déclarations

- statut ;
- institution ;
- visibilité ;
- pistes ;
- historique ;
- actions.

## 40.6. Visibilité renforcée

- forfaits ;
- rayon ;
- durée ;
- prix ;
- paiement ;
- statistiques.

---

# 41. ÉCRANS SANTÉ UTILISATEUR

## 41.1. Ma Santé

- capsule ;
- informations vitales ;
- provenance ;
- dernière vérification ;
- accès récents ;
- actions.

## 41.2. Capsule

- visualiser ;
- modifier une déclaration ;
- demander une vérification ;
- contacts ;
- consentements.

## 41.3. Accès à mon dossier

- qui ;
- institution ;
- date ;
- finalité ;
- type d’accès ;
- bris de glace ;
- contestation éventuelle.

## 41.4. Représentants

- ajouter ;
- vérifier ;
- limiter ;
- suspendre ;
- retirer.

---

# 42. ÉCRANS INSTITUTIONNELS

## 42.1. Police / Gendarmerie

- transmissions ;
- dossiers ;
- recherches autorisées ;
- pistes ;
- transferts ;
- avis ;
- clôture.

## 42.2. Secours

- urgences ;
- carte ;
- prise en charge ;
- étapes ;
- clôture.

## 42.3. Santé

- SOS médical ;
- capsule ;
- patients autorisés ;
- appels au sang ;
- historique.

## 42.4. Administration institutionnelle

- équipe ;
- capacités ;
- territoires ;
- appareils ;
- expiration ;
- incidents.

---

# 43. ADMINISTRATION WASPLEX

Dashboard :

- nouvelles alertes ;
- SOS ;
- non routées ;
- transmissions échouées ;
- institutions ;
- alertes sensibles ;
- projections ;
- visibilité renforcée ;
- accès Santé ;
- incidents ;
- pays ;
- capacités.

Actions :

- revoir ;
- valider ;
- restreindre ;
- publier ;
- retirer ;
- router ;
- transférer ;
- suspendre ;
- configurer ;
- auditer.

---

# 44. MODÈLE DE DONNÉES ALERTES

Entités recommandées :

```text
alert_cases
alert_case_events
alert_categories
alert_category_policies
alert_publications
alert_feed_projections
alert_institution_projections
alert_institution_dispatches
alert_correspondence_reports
alert_matches
alert_restitutions
alert_restitution_events
alert_followers
alert_visibility_packages
alert_visibility_orders
alert_visibility_impressions
alert_rewards
alert_reward_reservations
alert_official_references
alert_country_spaces
alert_territories
alert_institution_links
alert_audit_events
```

---

# 45. MODÈLE DE DONNÉES SANTÉ — PHASE 1

```text
health_patients
health_patient_representations
health_care_organizations
health_practitioner_credentials
health_consent_directives
health_access_events
health_emergency_capsules
health_emergency_facts
health_emergency_accesses
health_audit_events
```

Les tables Santé restent séparées.

---

# 46. MACHINES D’ÉTATS

## 46.1. Alerte communautaire

```text
draft
submitted
under_review
published
restricted
rejected
matched
restitution_scheduled
resolved
disputed
expired
withdrawn
```

## 46.2. SOS

```text
created
transmitted
received
accepted
processing
resolved
```

## 46.3. Visibilité renforcée

```text
draft
awaiting_payment
paid
scheduled
active
paused_for_priority
completed
cancelled
refunded
```

## 46.4. Accès Santé

```text
requested
authorized
denied
active
expired
reviewed
contested
```

---

# 47. API UTILISATEUR

```text
GET    /api/alerts
GET    /api/alerts/nearby
GET    /api/alerts/categories
POST   /api/alerts
GET    /api/alerts/{id}
PATCH  /api/alerts/{id}
POST   /api/alerts/{id}/submit
POST   /api/alerts/{id}/follow
POST   /api/alerts/{id}/share
POST   /api/alerts/{id}/correspondence
POST   /api/alerts/{id}/withdraw

POST   /api/sos
GET    /api/sos/{id}

GET    /api/me/alerts
GET    /api/me/alerts/{id}

GET    /api/alerts/{id}/visibility
POST   /api/alerts/{id}/visibility
GET    /api/alerts/{id}/visibility/stats

POST   /api/alerts/{id}/reward
GET    /api/alerts/{id}/reward

GET    /api/health/me
GET    /api/health/me/emergency-capsule
PATCH  /api/health/me/emergency-capsule
GET    /api/health/me/accesses
GET    /api/health/me/representatives
POST   /api/health/me/representatives
```

---

# 48. API INSTITUTIONNELLE

```text
GET    /api/institution/alerts/dashboard
GET    /api/institution/alerts/dispatches
GET    /api/institution/alerts/cases/{id}
POST   /api/institution/alerts/cases/{id}/receive
POST   /api/institution/alerts/cases/{id}/accept
POST   /api/institution/alerts/cases/{id}/reject
POST   /api/institution/alerts/cases/{id}/process
POST   /api/institution/alerts/cases/{id}/transfer
POST   /api/institution/alerts/cases/{id}/resolve
POST   /api/institution/alerts/cases/{id}/official-reference
POST   /api/institution/alerts/cases/{id}/official-publication

GET    /api/institution/alerts/search
POST   /api/institution/alerts/cases/{id}/request-more-access

POST   /api/institution/health/emergency-access
GET    /api/institution/health/emergency-access/{id}
```

---

# 49. API ADMINISTRATION

```text
GET    /api/admin/alerts/dashboard
GET    /api/admin/alerts/cases
GET    /api/admin/alerts/cases/{id}
POST   /api/admin/alerts/cases/{id}/review
POST   /api/admin/alerts/cases/{id}/publish
POST   /api/admin/alerts/cases/{id}/restrict
POST   /api/admin/alerts/cases/{id}/route
POST   /api/admin/alerts/cases/{id}/withdraw

GET    /api/admin/alerts/institutions
POST   /api/admin/alerts/institutions/{id}/activate
POST   /api/admin/alerts/institutions/{id}/suspend

GET    /api/admin/alerts/visibility
GET    /api/admin/alerts/country-spaces
POST   /api/admin/alerts/country-spaces
PATCH  /api/admin/alerts/country-spaces/{id}

GET    /api/admin/health/accesses
GET    /api/admin/health/incidents
```

---

# 50. ÉVÉNEMENTS MÉTIER

```text
AlertCaseCreated
AlertCaseSubmitted
AlertCaseReviewed
AlertPublicationCreated
AlertPublicationWithdrawn
AlertDispatched
AlertDispatchReceived
AlertDispatchAccepted
AlertProcessingStarted
AlertTransferred
AlertResolved
AlertCorrespondenceReported
AlertMatchConfirmed
AlertRestitutionScheduled
AlertRestitutionConfirmed
AlertVisibilityPurchased
AlertVisibilityActivated
AlertVisibilityPaused
AlertVisibilityCompleted
AlertRewardReserved
AlertRewardReleased

HealthPatientCreated
HealthRepresentativeGranted
HealthConsentGranted
HealthConsentRevoked
HealthEmergencyCapsuleUpdated
HealthEmergencyAccessRequested
HealthEmergencyAccessAuthorized
HealthEmergencyAccessDenied
HealthEmergencyAccessExpired
HealthEmergencyAccessReviewed
```

---

# 51. CAPACITÉS UTILISATEUR

```text
alert.case.submit.self
alert.case.view.self
alert.case.update.self
alert.case.follow
alert.correspondence.submit
alert.visibility.purchase.self
alert.reward.manage.self
health.record.view.self
health.emergency_capsule.manage.self
health.consent.manage.self
health.access_audit.view.self
```

---

# 52. CAPACITÉS INSTITUTIONNELLES

```text
alert.case.receive
alert.case.view.scoped
alert.case.acknowledge
alert.case.accept
alert.case.process
alert.case.transfer
alert.case.resolve
alert.match.review
alert.official_reference.create
alert.publication.official
alert.search.scoped

health.emergency_capsule.read
health.emergency_access.request
health.emergency_access.review
```

Chaque capacité possède :

- finalité ;
- territoire ;
- durée ;
- organisation ;
- catégorie ;
- niveau de session.

---

# 53. CAPACITÉS ADMINISTRATION

```text
alert.case.review
alert.case.publish
alert.case.restrict
alert.case.route
alert.case.withdraw
alert.institution.manage
alert.country_space.manage
alert.visibility.manage
alert.audit.view

health.organization.manage
health.practitioner.manage
health.access.audit
health.incident.manage
```

---

# 54. NOTIFICATIONS

Citoyen :

- déclaration enregistrée ;
- transmission ;
- réception ;
- acceptation ;
- information manquante ;
- piste reçue ;
- restitution ;
- résolution ;
- visibilité activée ;
- visibilité suspendue ;
- visibilité terminée.

Institution :

- nouveau dossier ;
- dossier prioritaire ;
- transfert ;
- piste ;
- correspondance ;
- expiration ;
- incident.

Santé :

- accès demandé ;
- accès autorisé ;
- accès d’urgence ;
- expiration ;
- consentement ;
- mise à jour.

---

# 55. TEMPS RÉEL

Première version :

- outbox transactionnelle ;
- worker ;
- polling raisonnable ;
- notifications ;
- idempotence ;
- accusés ;
- reprise après panne.

Évolution possible :

- WebSocket ;
- push institutionnel ;
- passerelles nationales ;
- files distribuées.

Ne pas introduire une infrastructure complexe avant nécessité.

---

# 56. SÉCURITÉ

Contrôles obligatoires :

- UUID publics ;
- append-only pour les événements ;
- idempotence ;
- chiffrement ;
- MFA institutionnelle ;
- rate limiting ;
- séparation des projections ;
- liens signés ;
- contrôle des pièces ;
- journal d’accès ;
- suspension ;
- conservation des preuves ;
- aucune suppression silencieuse.

---

# 57. PERFORMANCE ET RÉSEAU FAIBLE

Prévoir :

- application mobile légère ;
- cache ;
- compression ;
- images optimisées ;
- brouillons ;
- reprise ;
- chargement progressif ;
- cartes simples ;
- polling adaptatif ;
- notifications différées ;
- mode hors connexion pour la saisie non urgente.

Un SOS doit privilégier la rapidité.

---

# 58. TESTS ALERTES

## Domaine

- création ;
- idempotence ;
- transition ;
- append-only ;
- expiration ;
- retrait public ;
- résolution.

## Confidentialité

- projection sans données interdites ;
- disparition non publiée automatiquement ;
- institution hors territoire refusée ;
- organisation suspendue refusée ;
- recherche sans motif refusée ;
- aucune lecture croisée.

## SOS

- anonyme ;
- état créé ;
- absence de destinataire ;
- transmission réelle ;
- pas de faux reçu ;
- rate limiting.

## Feed

- cercles ;
- rail ;
- insertion ;
- aucun WP ;
- aucune progression publicitaire ;
- priorité vitale ;
- suspension d’une visibilité payante.

## Interopérabilité

- commissariat A ;
- commissariat B ;
- transfert ;
- double accusé ;
- idempotence ;
- reprise réseau.

## Restitution

- code ;
- expiration ;
- double confirmation ;
- témoin ;
- contestation.

---

# 59. TESTS SANTÉ

- patient sans compte ;
- représentant valide ;
- représentant expiré ;
- professionnel hors organisation ;
- capsule ;
- provenance ;
- accès d’urgence ;
- justification ;
- MFA ;
- expiration ;
- audit ;
- aucune donnée dans Advertising ;
- aucune modification du Wallet ;
- aucune lecture du dossier complet via Alertes.

---

# 60. TESTS VISUELS

Captures minimales :

- accueil Alertes ;
- cercles dans le Feed ;
- rail vertical ;
- insertion plein écran ;
- création d’une alerte ;
- SOS ;
- suivi citoyen ;
- portail police ;
- dossier institutionnel ;
- transfert ;
- capsule Santé ;
- historique des accès ;
- mobile 320, 360, 390 ;
- desktop institutionnel.

---

# 61. CRITÈRES D’ACCEPTATION

Le module est accepté lorsque :

1. Alertes est la quatrième destination principale ;
2. Santé est intégré dans Alertes ;
3. les domaines techniques restent séparés ;
4. le citoyen crée un dossier unique ;
5. le dossier produit plusieurs projections ;
6. les alertes apparaissent dans les quatre surfaces ;
7. le Feed ne crédite aucun WP pour une alerte ;
8. une visibilité payante n’éclipse jamais une urgence ;
9. les SOS sont gratuits ;
10. les états sont prouvés ;
11. la police et la gendarmerie disposent d’un portail ;
12. un commissariat B peut recevoir un dossier de A ;
13. B ne réécrit pas l’historique de A ;
14. les recherches sont motivées ;
15. les comptes institutionnels sont nominatifs ;
16. le citoyen voit l’état réel ;
17. la communauté transmet des pistes structurées ;
18. les coordonnées privées ne sont pas exposées ;
19. les restitutions sont protégées ;
20. Santé phase 1 possède la capsule et les accès ;
21. la Carte Wasplex est préparée comme future clé ;
22. le gouvernement peut acheter l’infrastructure ;
23. aucun agent n’est payé par dossier ;
24. les données Santé ne vont pas dans Advertising ;
25. les tests critiques passent.

---

# 62. ORDRE D’IMPLÉMENTATION

## Phase 1 — Socle Alertes

- catégories ;
- dossiers ;
- événements ;
- projections ;
- politiques ;
- capacités.

## Phase 2 — Citoyen

- accueil ;
- création ;
- SOS ;
- suivi ;
- correspondance.

## Phase 3 — Feed

- cercles ;
- rail ;
- insertion ;
- files ;
- priorité ;
- visibilité renforcée.

## Phase 4 — Institutions

- organisations ;
- utilisateurs ;
- portail ;
- dispatch ;
- états ;
- transfert ;
- recherche.

## Phase 5 — Restitutions

- correspondance ;
- match ;
- code ;
- remise ;
- récompense optionnelle.

## Phase 6 — Santé phase 1

- patients ;
- représentants ;
- professionnels ;
- consentements ;
- capsule ;
- bris de glace ;
- audit.

## Phase 7 — Pays et interopérabilité

- espaces souverains ;
- hiérarchies ;
- API ;
- passerelles ;
- événements.

## Phase 8 — Administration

- dashboard ;
- revue ;
- institutions ;
- diffusion ;
- incidents ;
- audit.

## Phase 9 — Stabilisation

- sécurité ;
- performance ;
- accessibilité ;
- tests ;
- captures.

---

# 63. LIVRABLES DÉVELOPPEUR

- migrations ;
- modèles ;
- services ;
- machines d’états ;
- politiques de projection ;
- moteurs de routage ;
- API ;
- portails ;
- composants Feed ;
- écrans ;
- administration ;
- Santé phase 1 ;
- événements ;
- notifications ;
- tests ;
- captures ;
- données de démonstration fictives ;
- commandes de déploiement ;
- procédure d’interopérabilité ;
- procédure d’incident.

---

# 64. DIRECTIVE POUR CLAUDE CODE

Lors du futur codage :

1. lire cette fiche ;
2. auditer le nouveau dépôt ;
3. identifier le framework ;
4. produire un plan de fichiers court ;
5. commencer le code ;
6. ne pas créer une nouvelle Constitution ;
7. ne pas multiplier les documents ;
8. ne pas coder de fausses données ;
9. ne pas afficher un état non prouvé ;
10. ne pas inventer des accès ;
11. travailler par commits ;
12. produire des captures ;
13. exécuter les tests ;
14. signaler seulement les véritables contradictions.

---

# 65. DÉCISION FINALE

Le module Alertes & Santé doit devenir un système où :

- un citoyen déclare facilement ;
- une institution reçoit un dossier structuré ;
- la communauté aide sans exposer la victime ;
- plusieurs commissariats coopèrent ;
- chaque état reste prouvé ;
- les urgences passent avant l’argent ;
- Santé protège les informations vitales ;
- Wasplex travaille avec les gouvernements sans rémunérer les agents par dossier ;
- le Feed reste captivant sans perdre sa fonction de protection.

La priorité du système est :

```text
Protection
→ vérité
→ compétence
→ territoire
→ confidentialité
→ diffusion
→ paiement éventuel
```

Le paiement intervient toujours en dernier.
