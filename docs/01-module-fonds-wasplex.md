# WASPLEX — MODULE FONDS

**Fichier cible dans le nouveau dépôt :** `docs/02-fonds/00-module-fonds-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique définitive  
**Priorité :** Module principal de la navigation utilisateur  
**Navigation :** Feed — Fonds — Wallet — Alertes — Mon Espace  
**Cible :** Utilisateurs Wasplex disposant d’un abonnement publicitaire payant éligible  
**Principe central :** Solidarité volontaire, prélèvements automatiques plafonnés et réalisation concrète de vœux vérifiés  

---

# 1. OBJET DU DOCUMENT

Ce document définit entièrement le module **Fonds** de Wasplex.

Il remplace les formulations encore ouvertes des notes fondatrices par des règles de fonctionnement par défaut, des parcours, des écrans, des états, des données, des traitements et des critères de validation directement exploitables par les développeurs.

Il ne s’agit pas :

- d’une assurance ;
- d’une tontine classique ;
- d’un placement financier ;
- d’une promesse de remboursement ;
- d’un système dépendant du recrutement permanent de nouveaux membres ;
- d’un service garantissant automatiquement la réalisation de tout vœu.

Le module Fonds est :

> **Un programme volontaire de solidarité organisé par Wasplex dans lequel des membres éligibles acceptent à l’avance que leur Solde Fonds soit débité automatiquement, dans des limites connues, afin de réaliser concrètement les vœux vérifiés d’autres membres.**

---

# 2. MÉMOIRE PRODUIT CONSERVÉE

Les anciens écrans Fonds contenaient plusieurs idées fortes qui doivent être conservées :

- Fonds comme destination principale de la navigation ;
- un écran simple présentant le programme actif ;
- un bouton visible pour déclarer un vœu ;
- une progression en plusieurs étapes ;
- une sélection claire de catégories ;
- des catégories concrètes : voiture, moto, terrain, maison, construction, rénovation, opération médicale ;
- un compteur de vœux disponibles selon l’adhésion ;
- un état vide motivant ;
- une expérience mobile sombre cohérente avec Wasplex ;
- des cartes larges, tactiles et facilement compréhensibles ;
- une logique orientée vers les objectifs de vie.

À améliorer :

- le terme « Fonds Social » peut être maintenu dans les contrats ou explications, mais l’onglet principal reste **Fonds** ;
- le module ne doit pas donner l’impression que tout vœu sera automatiquement réalisé ;
- les catégories ne doivent pas être figées dans l’interface ;
- le parcours doit expliquer l’apport personnel, le mandat et les contributions automatiques ;
- le financement ne doit pas être une simple cagnotte opaque ;
- l’utilisateur doit comprendre son statut, son Solde Fonds et ses contributions à régulariser ;
- l’administration et les partenaires doivent disposer d’espaces complets.

À abandonner :

- toute promesse implicite de réalisation rapide sans conditions ;
- toute donnée ou quota fictif ;
- toute catégorie codée en dur ;
- tout affichage laissant croire que l’adhésion suffit à obtenir un bien ;
- toute confusion entre Fonds, abonnement publicitaire, Wallet et micro-actionnariat.

---

# 3. PLACE DU MODULE DANS WASPLEX

## 3.1. Navigation principale

L’ordre de navigation officiel est :

1. Feed
2. Fonds
3. Wallet
4. Alertes
5. Mon Espace

Le Wallet reste au centre et visuellement dominant.

Fonds possède son propre écran principal et ses sous-écrans.

## 3.2. Relations avec les autres modules

### Feed

Le Feed permet de gagner des WP.

Les WP peuvent être transférés vers le Solde Fonds ou vers l’apport personnel d’un vœu.

### Wallet

Le Wallet conserve les compartiments financiers nécessaires :

- Solde disponible ;
- Solde Fonds ;
- apports personnels réservés ;
- contributions à régulariser ;
- opérations Fonds ;
- remboursements dus ;
- revenus et retraits.

### Mon Espace

Mon Espace affiche :

- l’adhésion Fonds ;
- le programme ;
- le statut du mandat ;
- les vœux actifs ;
- l’indice de réciprocité ;
- les contributions à régulariser ;
- les accès rapides vers Fonds.

### Alertes et Santé

Une situation signalée dans Alertes ou Santé peut donner lieu à un dossier Fonds uniquement après une action explicite et une vérification.

Aucune donnée sensible Santé ne doit être transférée automatiquement vers Fonds.

### Partenaires et institutions

Les partenaires vérifiés ou institutions affiliées peuvent :

- produire des devis ;
- recevoir une commande ;
- recevoir un paiement ;
- confirmer une prestation ;
- joindre une preuve ;
- gérer une garantie ;
- signaler une anomalie.

---

# 4. UNITÉS ET SOLDES

## 4.1. Équivalence Waspoint

La règle économique de base est :

> **1 WP = 1 FCFA**

Cette équivalence doit être unique dans tout Wasplex.

Toute modification future doit être décidée et migrée globalement, jamais écran par écran.

## 4.2. Solde disponible

Le Solde disponible peut contenir :

- WP gagnés dans le Feed ;
- dépôts monétaires ;
- transferts reçus ;
- remboursements ;
- autres crédits autorisés.

Il est librement utilisable selon les règles du Wallet.

## 4.3. Solde Fonds

Le Solde Fonds est le compartiment utilisé pour les contributions automatiques du programme Fonds.

Il peut être alimenté par :

- transfert depuis le Solde disponible ;
- dépôt externe dirigé vers Fonds ;
- affectation automatique d’un pourcentage des gains Feed ;
- montant fixe périodique ;
- versement ponctuel ;
- remboursement d’une opération Fonds ;
- contribution volontaire.

Le Solde Fonds ne peut pas être négatif.

## 4.4. Apport personnel de vœu

Chaque vœu nécessitant un apport personnel possède son propre compartiment.

Exemple :

```text
Vœu Moto
Apport requis :       300 000 FCFA
Apport constitué :    125 000 FCFA
Reste :               175 000 FCFA
Progression :                41 %
```

L’apport est distinct :

- du Solde Fonds général ;
- de la contribution aux vœux d’autres membres ;
- des revenus Wasplex ;
- de la réserve collective.

## 4.5. Contribution à régulariser

Une contribution à régulariser est une obligation Fonds non encore honorée.

Elle n’est pas affichée comme un solde financier négatif.

Exemple :

```text
Solde Fonds :                    0 FCFA
Contribution à régulariser :  170 FCFA
Statut :                       En retard
```

## 4.6. Réserve Fonds

La réserve Fonds est un compartiment collectif destiné à protéger :

- les participants ;
- les bénéficiaires ;
- les engagements déjà pris ;
- les urgences ;
- certaines défaillances de prestataires ;
- certains déficits de collecte ;
- les coûts imprévus autorisés.

## 4.7. Revenus Wasplex

Les frais Wasplex sont comptabilisés séparément :

- des contributions solidaires ;
- de l’apport personnel ;
- de la réserve ;
- de la trésorerie du bénéficiaire ;
- du paiement partenaire.

---

# 5. CONDITIONS D’ACCÈS

Pour adhérer à Fonds, l’utilisateur doit :

- posséder un compte Wasplex actif ;
- disposer d’un abonnement publicitaire payant éligible ;
- avoir l’âge et la capacité juridique requis ;
- disposer des vérifications de compte exigées ;
- accepter les conditions Fonds ;
- accepter un mandat de prélèvement automatique ;
- choisir un programme Fonds ;
- ne pas être suspendu ;
- ne pas être en situation de fraude confirmée ;
- ne pas avoir de contribution grave non régularisée lorsque les règles l’interdisent.

Les membres gratuits de Wasplex ne peuvent pas adhérer à Fonds.

L’éligibilité des niveaux d’abonnement est configurable depuis l’administration.

---

# 6. PROGRAMMES FONDS

Le système doit permettre plusieurs programmes configurables.

Noms initiaux possibles :

- Silver ;
- Gold ;
- Platinum.

Les noms définitifs peuvent être modifiés depuis l’administration.

Chaque programme définit :

- prix d’adhésion ;
- durée ;
- ancienneté minimale ;
- nombre de vœux déposables ;
- nombre de vœux actifs ;
- catégories autorisées ;
- valeur maximale d’un vœu ;
- apport personnel minimal ;
- délai de constitution de l’apport ;
- contribution minimale ;
- contribution maximale ;
- plafond quotidien ;
- plafond mensuel ;
- plafond annuel ;
- frais fixe Wasplex ;
- délai de notification avant débit ;
- nombre maximal de collectes simultanées ;
- délai entre deux vœux ;
- critères de réhabilitation ;
- accès à certains partenaires ;
- services d’accompagnement.

Un programme supérieur peut offrir plus de capacités.

Il ne garantit jamais la réalisation d’un vœu.

---

# 7. ADHÉSION ET MANDAT

## 7.1. Parcours d’adhésion

Le parcours comprend :

1. présentation du programme ;
2. comparaison des programmes ;
3. vérification de l’abonnement publicitaire ;
4. présentation des contributions automatiques ;
5. choix du plafond personnel ;
6. choix du mode d’alimentation du Solde Fonds ;
7. lecture ou accès aux conditions ;
8. acceptation du mandat ;
9. confirmation ;
10. activation de l’adhésion.

## 7.2. Mandat automatique

En adhérant, l’utilisateur autorise Wasplex à débiter automatiquement son Solde Fonds lorsque :

- un vœu a été validé ;
- il appartient au programme concerné ;
- il figure dans le groupe débitable ;
- les plafonds sont respectés ;
- le mandat est actif ;
- le débit n’a pas déjà été exécuté ;
- la collecte n’a pas été annulée.

Une nouvelle confirmation n’est pas demandée pour chaque vœu.

## 7.3. Informations du mandat

Le mandat doit préciser :

- programme ;
- montant minimal ;
- plafond personnel ;
- plafonds par période ;
- frais fixe Wasplex applicable ;
- délai d’information ;
- mécanisme de débit ;
- règle de contribution partielle ;
- règle de régularisation ;
- effets d’une révocation ;
- effets d’un refus ;
- durée ;
- date d’acceptation ;
- version des conditions.

## 7.4. Information avant débit

L’utilisateur reçoit une notification avant le débit selon le délai configuré.

La notification indique :

- catégorie générale du vœu ;
- programme ;
- montant total prévu à débiter sur son compte ;
- date prévue ;
- Solde Fonds disponible ;
- plafond restant ;
- action pour alimenter le Solde Fonds ;
- lien vers les règles applicables.

La notification ne révèle pas l’identité complète du bénéficiaire.

## 7.5. Révocation du mandat

La révocation :

- bloque les nouveaux prélèvements ;
- suspend l’adhésion active ;
- bloque les nouveaux vœux ;
- ne remet pas en cause les débits déjà exécutés ;
- ne supprime pas les obligations déjà engagées ;
- conserve l’historique.

---

# 8. DÉPÔT D’UN VŒU

## 8.1. Définition

Un vœu est une demande structurée visant la réalisation d’un besoin :

- réel ;
- identifiable ;
- licite ;
- vérifiable ;
- réalisable ;
- compatible avec Fonds.

Un vœu n’est pas une demande libre d’argent.

## 8.2. Catégories initiales

Catégories autorisées initiales :

- soins médicaux ;
- médicaments ;
- intervention chirurgicale ;
- logement ;
- amélioration essentielle de l’habitat ;
- terrain ;
- maison ;
- construction ;
- rénovation ;
- véhicule nécessaire à une activité ;
- moto ;
- tricycle ;
- taxi ;
- camion ;
- équipement professionnel ;
- outil de travail ;
- formation ;
- scolarité ;
- équipement scolaire ;
- accessibilité liée au handicap ;
- équipement agricole ;
- accès à l’eau ;
- électrification ;
- création ou relance d’activité ;
- urgence familiale grave ;
- besoin communautaire ;
- autre catégorie validée.

Toutes les catégories sont administrables.

## 8.3. Catégories interdites

Sont interdits :

- activité illégale ;
- spéculation ;
- jeux d’argent ;
- paris ;
- armes ;
- produits dangereux ;
- financement politique ;
- remboursement de dette non justifiée ;
- dépenses somptuaires sans utilité sociale ;
- revente spéculative ;
- activité portant atteinte à la dignité humaine ;
- demande de trésorerie libre ;
- dossier frauduleux ;
- tout projet incompatible avec Wasplex.

## 8.4. Parcours de déclaration

Étapes recommandées :

1. Catégorie
2. Description du besoin
3. Caractéristiques ou catalogue
4. Localisation
5. Documents
6. Budget estimatif
7. Apport personnel
8. Échéancier
9. Récapitulatif
10. Soumission

## 8.5. État brouillon

Un vœu peut être sauvegardé en brouillon.

Le brouillon :

- ne crée aucun droit ;
- n’est pas visible publiquement ;
- ne déclenche aucun prélèvement ;
- peut être repris ;
- peut expirer après une durée configurable.

---

# 9. JUSTIFICATIFS

Selon la catégorie, le système peut demander :

- pièce d’identité ;
- preuve de résidence ;
- devis ;
- facture pro forma ;
- certificat médical ;
- prescription ;
- preuve d’activité ;
- preuve de revenu ;
- document scolaire ;
- document professionnel ;
- preuve d’apport ;
- photos ;
- attestations ;
- coordonnées fournisseur ;
- autorisation administrative ;
- preuve complémentaire.

Les exigences sont configurées par catégorie.

Les pièces sensibles ne sont accessibles qu’aux personnes habilitées.

---

# 10. CYCLE DE VIE D’UN VŒU

États recommandés :

```text
Brouillon
Soumis
Vérification initiale
Informations complémentaires requises
Recherche de partenaire
Devis en cours
Devis reçu
Coût validé
Apport en constitution
Apport complet
Éligible au financement
En file
Collecte programmée
Collecte en cours
Partiellement financé
Financé
Commande engagée
En réalisation
Livraison à confirmer
Réalisé
Clôturé
Suspendu
Rejeté
Annulé
Expiré
Fraude suspectée
Fraude confirmée
```

Chaque état doit correspondre à une preuve réelle.

---

# 11. APPORT PERSONNEL PROGRESSIF

## 11.1. Principe

Le demandeur participe normalement à son propre vœu.

L’apport peut être constitué progressivement.

## 11.2. Sources d’alimentation

Le demandeur peut alimenter son apport par :

- WP gagnés dans le Feed ;
- Solde disponible ;
- Solde Fonds ;
- dépôt Mobile Money ;
- dépôt bancaire ;
- transfert autorisé ;
- versement ponctuel ;
- versement programmé ;
- autre source autorisée.

## 11.3. Échéancier

Le système permet :

- montant libre ;
- montant fixe périodique ;
- pourcentage des gains Feed ;
- date cible ;
- rappel automatique ;
- prélèvement programmé autorisé.

## 11.4. Règle de déclenchement collectif

La collecte collective ne commence que lorsque :

- le coût final est validé ;
- le partenaire est sélectionné ou le fournisseur est connu ;
- l’apport requis est complet ;
- les justificatifs sont conformes ;
- l’éligibilité est confirmée ;
- aucune suspension n’est active.

Exception :

Une urgence vitale peut obtenir :

- apport réduit ;
- apport différé ;
- apport nul ;
- intervention prioritaire de la réserve.

Cette exception doit être validée et tracée.

## 11.5. Apport non terminé

À l’échéance :

- délai supplémentaire possible ;
- redimensionnement possible ;
- changement de partenaire possible ;
- suspension ;
- annulation ;
- restitution des sommes non engagées.

## 11.6. Restitution

L’apport est :

- utilisé si le vœu est réalisé ;
- restitué si rejeté avant engagement ;
- restitué si annulé avant engagement ;
- diminué des coûts déjà engagés si annulation tardive ;
- restitué ou réaffecté avec consentement si irréalisable ;
- bloqué pendant une enquête en cas de fraude présumée.

---

# 12. PARTICIPANTS DÉBITABLES

Pour la première version, un participant est débitable s’il :

- appartient au même programme Fonds ;
- appartient au même pays ;
- utilise la même devise ;
- possède une adhésion active ;
- possède un abonnement publicitaire payant éligible actif ;
- possède un mandat actif ;
- n’est pas suspendu ;
- n’a pas dépassé ses plafonds ;
- remplit les critères du groupe ;
- est présent dans l’instantané de collecte.

Le bénéficiaire n’est pas inclus dans le financement collectif de son propre vœu.

---

# 13. INSTANTANÉ DE COLLECTE

Avant chaque collecte, le moteur crée un instantané immuable contenant :

- identifiant du vœu ;
- programme ;
- pays ;
- devise ;
- coût validé ;
- apport ;
- participation partenaire ;
- réserve ;
- reste à financer ;
- frais fixe Wasplex ;
- liste des participants débitables ;
- plafonds appliqués ;
- version des règles ;
- date ;
- identifiant d’idempotence.

Les membres arrivés après l’instantané ne participent pas à cette collecte.

---

# 14. CALCUL DE LA CONTRIBUTION

## 14.1. Formule principale

```text
Coût final validé
− apport personnel
− remise ou contribution partenaire
− intervention autorisée de la réserve
= montant collectif à financer
```

```text
Contribution solidaire théorique
= montant collectif à financer
÷ nombre de participants débitables
```

```text
Débit total individuel
= contribution solidaire individuelle
+ frais fixe Wasplex
```

## 14.2. Exemple de référence

```text
Coût Moto :                    1 000 000 FCFA
Apport du demandeur :            300 000 FCFA
Reste collectif :                700 000 FCFA
Participants :                    10 000
Contribution solidaire :              70 FCFA
Frais fixe Wasplex :                 100 FCFA
Débit total individuel :             170 FCFA
```

## 14.3. Frais fixe Wasplex

Règles :

- configurable ;
- valeur initiale possible : 100 FCFA ;
- dépend du programme, pays ou devise ;
- appliqué à chaque compte effectivement débité ;
- appliqué une seule fois par participant et par vœu ;
- non répété lors d’une nouvelle tentative technique ;
- séparé comptablement de la contribution ;
- non versé à la réserve ;
- non versé au bénéficiaire ;
- non inclus dans le coût du bien ou service ;
- encaissé uniquement lorsqu’un débit a produit un montant réellement collecté.

Le participant doit être informé dans les conditions qu’un frais fixe de fonctionnement est inclus dans chaque débit Fonds.

Le revenu global ou agrégé de Wasplex sur un vœu n’est pas affiché aux participants.

## 14.4. Arrondis

Le FCFA ne possédant pas de centimes, le moteur doit :

- calculer la part entière ;
- distribuer le reliquat de manière déterministe ;
- ne jamais surcollecter la contribution destinée au vœu ;
- conserver une somme exacte ;
- enregistrer chaque différence.

---

# 15. EXÉCUTION DES DÉBITS

## 15.1. Ordre

Pour chaque participant :

1. vérifier l’idempotence ;
2. vérifier le mandat ;
3. vérifier les plafonds ;
4. vérifier le Solde Fonds ;
5. débiter la contribution solidaire disponible ;
6. débiter le frais fixe Wasplex si le débit produit un montant collecté ;
7. écrire les opérations ;
8. mettre à jour les plafonds ;
9. produire un reçu ;
10. mettre à jour la collecte.

## 15.2. Débit réussi

Le reçu doit contenir :

- référence ;
- date ;
- vœu ou catégorie générale ;
- programme ;
- montant total débité ;
- statut ;
- nouveau Solde Fonds ;
- identifiant d’opération.

## 15.3. Débit partiel

Lorsqu’autorisé :

- le Solde Fonds disponible est débité ;
- le frais Wasplex n’est appliqué qu’une seule fois ;
- le reliquat devient contribution à régulariser ;
- l’utilisateur reçoit un délai ;
- le moteur ne doit jamais appliquer deux frais sur le même vœu.

## 15.4. Débit échoué

Si aucun montant n’est collecté :

- aucun frais Wasplex n’est encaissé ;
- une contribution à régulariser est créée ;
- une notification est envoyée ;
- l’état du membre est mis à jour.

---

# 16. CONTRIBUTION À RÉGULARISER

## 16.1. Délai

Délai initial recommandé :

```text
7 jours
```

Configurable par programme.

## 16.2. Régularisation

La régularisation peut se faire par :

- alimentation du Solde Fonds ;
- transfert depuis le Solde disponible autorisé ;
- affectation de futurs gains Feed ;
- dépôt direct ;
- paiement manuel.

## 16.3. Conséquences

Premier incident :

- notification ;
- délai ;
- aucune qualification automatique de fraude.

Retard persistant :

- dépôt de nouveau vœu bloqué ;
- vœu non engagé suspendu ;
- indice de réciprocité réduit ;
- statut En retard.

Répétition :

- suspension Fonds ;
- réhabilitation obligatoire ;
- contrôle administratif.

Fraude ou refus répété :

- suspension renforcée ;
- examen ;
- éventuelle exclusion.

## 16.4. Interdictions

Il ne faut pas :

- rendre le Wallet monétaire négatif ;
- appliquer des intérêts ;
- augmenter artificiellement la dette ;
- prélever hors mandat ;
- bloquer tout Wasplex pour une dette Fonds ordinaire.

---

# 17. INDICE DE RÉCIPROCITÉ

L’indice mesure la participation au Fonds.

Il peut prendre en compte :

- ancienneté ;
- contributions honorées ;
- régularité ;
- montant cumulé ;
- incidents ;
- refus injustifiés ;
- suspension ;
- aides reçues ;
- respect des engagements ;
- régularisation.

Il ne constitue pas :

- un solde ;
- une monnaie ;
- une garantie ;
- un achat de priorité.

Il sert à :

- vérifier l’éligibilité ;
- départager des dossiers comparables ;
- calculer certains plafonds ;
- appliquer certains délais ;
- organiser une réhabilitation.

Il ne doit jamais passer devant une urgence vitale vérifiée.

---

# 18. FILES DE FINANCEMENT

Pour la première version :

- une file ordinaire par programme ;
- une file d’urgence distincte ;
- nombre limité de collectes actives ;
- un seul vœu majeur actif par bénéficiaire ;
- pas de nouveau déclenchement si les engagements dépassent la capacité ;
- intervalle minimal entre deux débits importants ;
- plafonds quotidiens et mensuels.

L’administration peut configurer :

- nombre de collectes ;
- ordre ;
- priorité ;
- part réservée aux urgences ;
- capacité maximale ;
- calendrier.

---

# 19. DÉFICIT DE FINANCEMENT

Si la collecte est insuffisante :

1. attendre la régularisation ;
2. demander un complément d’apport ;
3. renégocier le prix ;
4. mobiliser la réserve ;
5. redimensionner le vœu ;
6. prolonger ;
7. lancer une seconde vague autorisée ;
8. reporter ;
9. annuler si irréalisable.

Une seconde vague nécessite une nouvelle information.

Un participant ne paie jamais deux fois le frais fixe Wasplex pour le même vœu.

---

# 20. PARTENAIRES ET INSTITUTIONS

## 20.1. Prestataire vérifié ponctuel

Caractéristiques :

- intervention limitée à une opération ;
- pas de portail permanent obligatoire ;
- données minimales ;
- devis ou prestation ciblée ;
- pas d’accès général ;
- pas de pouvoir institutionnel.

## 20.2. Institution partenaire Fonds

Caractéristiques :

- organisation vérifiée ;
- relation durable ;
- portail ;
- comptes nominatifs ;
- capacités séparées ;
- territoire ;
- durée ;
- audit ;
- responsabilités.

## 20.3. Types de partenaires

- évaluateur ;
- fournisseur ;
- prestataire ;
- établissement médical ;
- établissement éducatif ;
- partenaire financier ;
- partenaire de paiement ;
- logistique ;
- contrôleur ;
- assureur ou garant autorisé ;
- collectivité ;
- association ou ONG.

## 20.4. Capacités possibles

- recevoir une demande de devis ;
- produire un devis ;
- modifier un devis ;
- confirmer la disponibilité ;
- accepter une commande ;
- consulter les données nécessaires ;
- recevoir un paiement ;
- confirmer une étape ;
- joindre un justificatif ;
- confirmer une livraison ;
- signaler une anomalie ;
- ouvrir un litige ;
- gérer une garantie.

Chaque capacité est accordée séparément.

---

# 21. PARCOURS PARTENAIRE

```text
Vœu soumis
→ vérification
→ demande de devis
→ réponse partenaire
→ comparaison
→ sélection
→ coût validé
→ apport progressif
→ collecte
→ commande
→ paiement par étapes
→ réalisation
→ preuve
→ validation
→ clôture
→ garantie
```

## 21.1. Paiement

Wasplex privilégie :

1. partenaire affilié ;
2. prestataire vérifié ;
3. fournisseur recherché ;
4. remboursement exceptionnel ;
5. versement direct exceptionnel.

Le bénéficiaire ne reçoit pas librement l’argent lorsque le bien ou service peut être payé directement.

## 21.2. Paiement par étapes

Pour les montants importants :

- acompte ;
- étape 1 ;
- étape 2 ;
- livraison ;
- solde ;
- retenue de garantie éventuelle.

Chaque étape exige une preuve.

## 21.3. Non-livraison

En cas de problème :

- paiement restant bloqué ;
- partenaire suspendu ;
- enquête ;
- demande de remboursement ;
- remplacement ;
- réserve mobilisable selon les règles ;
- sanctions ;
- exclusion.

---

# 22. RÉSERVE FONDS

## 22.1. Alimentation

La réserve peut recevoir :

- reliquats ;
- part d’adhésion annoncée ;
- dons ;
- contributions volontaires ;
- partenaires ;
- ressources Wasplex affectées ;
- pénalités autorisées ;
- remboursements récupérés.

## 22.2. Utilisation

La réserve peut :

- couvrir un déficit limité ;
- protéger un engagement ;
- financer une urgence ;
- compenser une défaillance ;
- absorber un écart ;
- garantir la continuité.

## 22.3. Reliquats

Règle par défaut :

> Les reliquats sont affectés à la réserve du programme.

Le reliquat ne devient pas un argent libre pour le bénéficiaire.

## 22.4. Contrôle

La réserve doit avoir :

- comptes séparés ;
- règles d’utilisation ;
- seuils ;
- historique ;
- décisions ;
- justificatifs ;
- visibilité administrative ;
- vue agrégée pour les membres.

---

# 23. TRANSPARENCE UTILISATEUR

Les membres peuvent consulter sous forme agrégée :

- nombre de vœux réalisés ;
- catégories ;
- montant consacré aux réalisations ;
- nombre de participants ;
- statut ;
- réserve ;
- preuve générale ;
- historique du programme.

Ils ne voient pas :

- identité complète du bénéficiaire ;
- données médicales ;
- coordonnées ;
- justificatifs privés ;
- revenu global Wasplex sur un vœu ;
- détails financiers internes des autres participants.

Chaque membre voit les opérations de son propre compte.

---

# 24. ÉCRANS UTILISATEUR

## 24.1. Accueil Fonds

Contenu :

- titre Fonds ;
- programme actif ;
- adhésion ;
- vœux disponibles ;
- Solde Fonds ;
- contribution à régulariser ;
- prochain débit ;
- bouton Déclarer un vœu ;
- mes vœux ;
- contributions récentes ;
- indice de réciprocité ;
- accès aux règles.

## 24.2. État non adhérent

Afficher :

- présentation ;
- avantages ;
- fonctionnement ;
- programmes ;
- condition d’abonnement payant ;
- bouton Adhérer.

## 24.3. Comparaison des programmes

Afficher :

- prix ;
- durée ;
- catégories ;
- plafonds ;
- nombre de vœux ;
- apport ;
- contribution ;
- frais fixe ;
- délai ;
- bouton Choisir.

## 24.4. Solde Fonds

Afficher :

- solde ;
- alimenter ;
- transférer ;
- alimentation automatique ;
- historique ;
- contributions programmées ;
- contribution à régulariser.

## 24.5. Déclarer un vœu

Parcours mobile à étapes.

## 24.6. Détail d’un vœu

Afficher :

- titre ;
- catégorie ;
- statut ;
- chronologie ;
- coût ;
- apport ;
- progression ;
- partenaire ;
- collecte ;
- réalisation ;
- preuves accessibles ;
- actions disponibles.

## 24.7. Mes contributions

Filtres :

- programmées ;
- exécutées ;
- partielles ;
- à régulariser ;
- annulées ;
- remboursées.

## 24.8. Indice de réciprocité

Afficher :

- niveau ;
- explication ;
- historique ;
- facteurs ;
- actions pour régulariser ;
- jamais de formule opaque exposant les règles anti-fraude.

## 24.9. Paramètres Fonds

- mandat ;
- plafond ;
- alimentation automatique ;
- notifications ;
- programme ;
- renouvellement ;
- quitter le programme.

---

# 25. DESIGN DE L’ACCUEIL FONDS

L’écran doit conserver l’identité sombre Wasplex.

Première hauteur :

- titre Fonds ;
- programme ;
- Solde Fonds ;
- dette éventuelle ;
- bouton Déclarer ;
- vœux disponibles.

Cartes :

- programme actif ;
- mon apport en cours ;
- prochaine contribution ;
- mes vœux ;
- historique ;
- transparence.

Le bouton Déclarer reste visible et important.

L’ancien état vide peut inspirer :

> « Ton premier vœu peut commencer ici. Choisis un besoin réel, constitue ton apport et suis chaque étape. »

Il ne doit pas promettre la réalisation.

---

# 26. ÉCRANS PARTENAIRE

Le portail partenaire Fonds comprend :

- tableau de bord ;
- demandes de devis ;
- devis soumis ;
- commandes ;
- paiements ;
- livraisons ;
- garanties ;
- litiges ;
- documents ;
- équipe ;
- capacités ;
- profil institutionnel.

Le partenaire ne voit que les données nécessaires.

---

# 27. ÉCRANS ADMINISTRATEUR

## 27.1. Dashboard Fonds

Afficher :

- membres actifs ;
- programmes ;
- Solde Fonds agrégé ;
- contributions à régulariser ;
- collectes ;
- vœux ;
- apports ;
- réserves ;
- revenus Wasplex ;
- partenaires ;
- risques ;
- alertes ;
- files d’action.

## 27.2. Gestion des programmes

Configurer :

- éligibilité ;
- adhésion ;
- durée ;
- frais fixe ;
- plafonds ;
- catégories ;
- délais ;
- apports ;
- files ;
- réserves ;
- notifications.

## 27.3. Vœux

Actions :

- consulter ;
- demander un complément ;
- valider ;
- rejeter ;
- suspendre ;
- classer ;
- rechercher un partenaire ;
- valider un devis ;
- déclencher une collecte ;
- mobiliser une réserve ;
- clôturer ;
- ouvrir un litige.

## 27.4. Contributions

Afficher :

- instantané ;
- participants ;
- réussite ;
- échec ;
- partiel ;
- frais ;
- régularisation ;
- idempotence ;
- réconciliation.

## 27.5. Réserve

Afficher :

- solde ;
- entrées ;
- sorties ;
- affectations ;
- décisions ;
- justificatifs ;
- seuils.

## 27.6. Partenaires

- demandes ;
- vérifications ;
- capacités ;
- contrats ;
- devis ;
- commandes ;
- incidents ;
- suspensions.

---

# 28. MODÈLE DE DONNÉES RECOMMANDÉ

Entités principales :

```text
fund_programs
fund_program_versions
fund_memberships
fund_mandates
fund_member_limits
fund_balances
fund_balance_entries
fund_wishes
fund_wish_categories
fund_wish_documents
fund_wish_reviews
fund_wish_quotes
fund_wish_personal_contributions
fund_wish_personal_contribution_entries
fund_collection_snapshots
fund_collection_participants
fund_collection_debits
fund_contribution_arrears
fund_reciprocity_scores
fund_reciprocity_events
fund_reserves
fund_reserve_entries
fund_partner_assignments
fund_orders
fund_order_milestones
fund_delivery_proofs
fund_disputes
fund_audit_events
```

Le modèle financier doit utiliser un grand livre.

Aucun solde ne doit être modifié sans écriture.

---

# 29. GRAND LIVRE FONDS

Types d’écritures :

- alimentation Solde Fonds ;
- transfert vers Fonds ;
- transfert depuis Fonds ;
- réservation apport ;
- libération apport ;
- contribution solidaire ;
- frais Wasplex ;
- régularisation ;
- remboursement ;
- réserve ;
- paiement partenaire ;
- annulation ;
- correction ;
- reliquat.

Chaque écriture contient :

- référence ;
- compte source ;
- compte destination ;
- montant ;
- devise ;
- type ;
- vœu ;
- collecte ;
- utilisateur ;
- statut ;
- idempotency key ;
- date ;
- origine ;
- preuve ;
- métadonnées contrôlées.

---

# 30. API MINIMALES

## 30.1. Utilisateur

```text
GET    /api/funds
GET    /api/funds/programs
POST   /api/funds/memberships
GET    /api/funds/membership
PATCH  /api/funds/membership
POST   /api/funds/membership/revoke

GET    /api/funds/balance
POST   /api/funds/balance/top-up
POST   /api/funds/balance/transfer
GET    /api/funds/balance/history

GET    /api/funds/wishes
POST   /api/funds/wishes
GET    /api/funds/wishes/{id}
PATCH  /api/funds/wishes/{id}
POST   /api/funds/wishes/{id}/submit
POST   /api/funds/wishes/{id}/personal-contribution
POST   /api/funds/wishes/{id}/cancel

GET    /api/funds/contributions
GET    /api/funds/contributions/{id}
POST   /api/funds/arrears/{id}/settle

GET    /api/funds/reciprocity
GET    /api/funds/transparency
```

## 30.2. Partenaire

```text
GET    /api/partner/funds/quote-requests
POST   /api/partner/funds/quotes
PATCH  /api/partner/funds/quotes/{id}
GET    /api/partner/funds/orders
POST   /api/partner/funds/orders/{id}/accept
POST   /api/partner/funds/orders/{id}/milestones
POST   /api/partner/funds/orders/{id}/proofs
POST   /api/partner/funds/disputes
```

## 30.3. Administration

```text
GET    /api/admin/funds/dashboard
GET    /api/admin/funds/programs
POST   /api/admin/funds/programs
PATCH  /api/admin/funds/programs/{id}

GET    /api/admin/funds/wishes
POST   /api/admin/funds/wishes/{id}/request-information
POST   /api/admin/funds/wishes/{id}/approve
POST   /api/admin/funds/wishes/{id}/reject
POST   /api/admin/funds/wishes/{id}/queue
POST   /api/admin/funds/wishes/{id}/start-collection
POST   /api/admin/funds/wishes/{id}/close

GET    /api/admin/funds/collections
GET    /api/admin/funds/collections/{id}
POST   /api/admin/funds/collections/{id}/retry
POST   /api/admin/funds/collections/{id}/reconcile

GET    /api/admin/funds/reserves
POST   /api/admin/funds/reserves/{id}/allocate

GET    /api/admin/funds/partners
POST   /api/admin/funds/partners/{id}/approve
POST   /api/admin/funds/partners/{id}/suspend
```

Les chemins exacts peuvent suivre les conventions du framework.

---

# 31. ÉVÉNEMENTS MÉTIER

Événements principaux :

```text
FundMembershipStarted
FundMandateAccepted
FundMandateRevoked
FundBalanceFunded
FundWishCreated
FundWishSubmitted
FundWishApproved
FundWishRejected
FundQuoteRequested
FundQuoteAccepted
FundPersonalContributionAdded
FundPersonalContributionCompleted
FundWishQueued
FundCollectionSnapshotCreated
FundDebitScheduled
FundDebitSucceeded
FundDebitPartiallySucceeded
FundDebitFailed
FundArrearCreated
FundArrearSettled
FundCollectionCompleted
FundReserveAllocated
FundOrderCreated
FundPartnerPaid
FundMilestoneConfirmed
FundWishDelivered
FundWishCompleted
FundWishCancelled
FundDisputeOpened
FundPartnerSuspended
```

Ces événements alimentent :

- notifications ;
- audit ;
- Wallet ;
- dashboard ;
- partenaires ;
- rapports ;
- historique.

---

# 32. NOTIFICATIONS

Notifications utilisateur :

- adhésion activée ;
- mandat accepté ;
- Solde Fonds faible ;
- débit programmé ;
- débit exécuté ;
- débit partiel ;
- contribution à régulariser ;
- régularisation réussie ;
- vœu reçu ;
- information manquante ;
- devis reçu ;
- apport progressif ;
- apport complet ;
- vœu en file ;
- collecte ;
- réalisation ;
- livraison ;
- clôture ;
- suspension.

Canaux :

- in-app ;
- push ;
- SMS ;
- e-mail ;
- WhatsApp si autorisé.

---

# 33. CAPACITÉS

## 33.1. Utilisateur

```text
fund.view.self
fund.membership.create.self
fund.membership.manage.self
fund.balance.view.self
fund.balance.fund.self
fund.wish.create.self
fund.wish.view.self
fund.wish.update.self
fund.wish.contribute.self
fund.arrear.settle.self
fund.reciprocity.view.self
```

## 33.2. Administration

```text
fund.program.manage
fund.membership.review
fund.wish.review
fund.wish.approve
fund.wish.reject
fund.collection.prepare
fund.collection.execute
fund.collection.reconcile
fund.reserve.view
fund.reserve.allocate
fund.partner.manage
fund.financials.view
fund.audit.view
fund.config.manage
```

## 33.3. Partenaire

```text
fund.partner.quote.view
fund.partner.quote.submit
fund.partner.order.view
fund.partner.order.accept
fund.partner.milestone.submit
fund.partner.delivery.confirm
fund.partner.dispute.open
fund.partner.payment.view
```

---

# 34. SÉCURITÉ ET ANTI-FRAUDE

Contrôles :

- KYC ;
- duplication d’identité ;
- devis falsifié ;
- documents falsifiés ;
- collusion partenaire ;
- bénéficiaire fictif ;
- double vœu ;
- double collecte ;
- débit répété ;
- fausse livraison ;
- prix anormal ;
- compte contrôlé par plusieurs personnes ;
- faux apport ;
- manipulation de réserve ;
- contournement de plafonds.

Toute opération financière doit être idempotente.

Les preuves doivent être conservées.

---

# 35. ÉTATS SYSTÈME

Prévoir :

- chargement ;
- vide ;
- hors connexion ;
- erreur partielle ;
- erreur financière ;
- opération en cours ;
- opération confirmée ;
- opération inconnue ;
- synchronisation ;
- maintenance ;
- fonctionnalité non disponible ;
- compte suspendu ;
- mandat expiré ;
- abonnement publicitaire expiré.

Le système ne doit jamais afficher une réussite si le grand livre n’a pas confirmé l’écriture.

---

# 36. TESTS OBLIGATOIRES

## 36.1. Adhésion

- abonnement gratuit refusé ;
- abonnement payant accepté ;
- mandat versionné ;
- révocation ;
- expiration ;
- plafond personnel.

## 36.2. Apport

- versement partiel ;
- progression ;
- apport complet ;
- restitution ;
- annulation ;
- urgence.

## 36.3. Collecte

- instantané ;
- division ;
- arrondi ;
- frais fixe ;
- débit réussi ;
- débit partiel ;
- débit échoué ;
- absence de double frais ;
- absence de double débit ;
- plafonds ;
- exclusion du bénéficiaire.

## 36.4. Régularisation

- dette sociale ;
- délai ;
- paiement ;
- futur gain ;
- suspension ;
- réhabilitation.

## 36.5. Partenaires

- devis ;
- capacité ;
- commande ;
- paiement par étapes ;
- preuve ;
- non-livraison ;
- suspension.

## 36.6. Réserve

- alimentation ;
- allocation ;
- refus ;
- reliquat ;
- audit.

## 36.7. Frontend

- accueil non adhérent ;
- adhérent ;
- dette ;
- vœu ;
- apport ;
- petite largeur ;
- navigation ;
- contenu non masqué ;
- erreurs ;
- accessibilité.

---

# 37. CRITÈRES D’ACCEPTATION

Le module est accepté lorsque :

1. Fonds est une destination principale ;
2. seuls les abonnés payants éligibles adhèrent ;
3. le mandat automatique est explicite ;
4. le Solde Fonds est séparé ;
5. 1 WP vaut 1 FCFA ;
6. l’apport personnel est progressif ;
7. chaque vœu possède un compartiment d’apport ;
8. le collectif n’est débité qu’après apport complet, sauf urgence ;
9. les participants sont sélectionnés selon les règles ;
10. l’instantané est immuable ;
11. la contribution est calculée exactement ;
12. le frais fixe est appliqué une seule fois ;
13. aucune tentative ne produit un double débit ;
14. le Wallet ne devient pas négatif ;
15. la dette sociale est visible ;
16. les avantages Fonds sont suspendus en cas de retard ;
17. la réserve est séparée ;
18. les partenaires sont vérifiés ;
19. le paiement va en priorité au partenaire ;
20. les preuves sont obligatoires ;
21. le revenu Wasplex est comptabilisé séparément ;
22. le revenu global Wasplex n’est pas exposé aux participants ;
23. l’administration dispose d’une vue complète ;
24. les données privées du bénéficiaire sont protégées ;
25. les tests critiques passent.

---

# 38. ORDRE D’IMPLÉMENTATION

## Phase 1 — Socle

- programmes ;
- adhésions ;
- mandats ;
- capacités ;
- Wallet Fonds ;
- grand livre.

## Phase 2 — Vœux

- catégories ;
- formulaire ;
- documents ;
- états ;
- revue.

## Phase 3 — Apport progressif

- compartiments ;
- versements ;
- échéanciers ;
- progression ;
- restitution.

## Phase 4 — Partenaires

- vérification ;
- demandes de devis ;
- devis ;
- commandes ;
- paiements.

## Phase 5 — Collecte automatique

- instantané ;
- calcul ;
- frais fixe ;
- débits ;
- idempotence ;
- régularisation.

## Phase 6 — Réalisation

- étapes ;
- preuves ;
- livraison ;
- litiges ;
- clôture.

## Phase 7 — Administration

- dashboard ;
- programmes ;
- files ;
- réserve ;
- finance ;
- audit.

## Phase 8 — Stabilisation

- performance ;
- sécurité ;
- accessibilité ;
- tests ;
- captures.

---

# 39. LIVRABLES ATTENDUS

Le développeur doit fournir :

- migrations ;
- modèles ;
- services ;
- moteurs financiers ;
- contrôleurs ;
- API ;
- événements ;
- files de traitement ;
- composants ;
- écrans ;
- administration ;
- portail partenaire ;
- tests ;
- données de démonstration ;
- captures ;
- résultats ;
- commandes de déploiement ;
- procédure de réconciliation.

---

# 40. DÉCISION FINALE

Fonds doit permettre à une grande communauté de réaliser des besoins importants par de petites contributions individuelles.

Plus le programme compte de membres, plus la contribution solidaire individuelle peut devenir légère.

Wasplex reçoit un frais fixe sur chaque compte effectivement débité pour financer :

- le fonctionnement du mécanisme ;
- les contrôles ;
- la technologie ;
- la gestion ;
- les partenaires ;
- la sécurité ;
- la continuité de la plateforme.

Le système doit rester :

- volontaire à l’entrée ;
- automatique après mandat ;
- plafonné ;
- traçable ;
- vérifiable ;
- protégé ;
- orienté vers la réalisation concrète ;
- compréhensible ;
- prêt à fonctionner à grande échelle.
