# Wasplex — Alertes & Santé

## Document de cadrage business pour le Business Plan

**Statut :** document business de référence  
**Périmètre :** Alertes, Santé et raccordement institutionnel P019  
**Base produit :** P015, P016 et P019 déjà implémentés  
**Principe directeur :** les fonctions vitales, citoyennes et d’urgence restent accessibles indépendamment de la capacité de paiement.

---

# 1. Résumé exécutif

Wasplex Alertes & Santé constitue une infrastructure numérique de confiance reliant les citoyens, la communauté, les professionnels, les établissements et les institutions autour de deux besoins fondamentaux : **signaler une situation utile ou urgente** et **permettre un accès médical limité, consenti et traçable lorsque cela est nécessaire**.

Le modèle repose sur une architecture dans laquelle :

- le citoyen crée une déclaration ou gère sa capsule Santé ;
- Wasplex protège les données sensibles et contrôle les projections ;
- les institutions vérifiées reçoivent uniquement les dossiers qui leur sont explicitement transmis ;
- les professionnels Santé vérifiés demandent un accès au patient ;
- le patient conserve la maîtrise des accès normaux ;
- les accès d'urgence restent limités, justifiés, habilités et journalisés ;
- chaque changement de statut institutionnel correspond à une action réelle ;
- la publicité, le Wallet et les données médicales restent séparés.

Le potentiel économique ne vient donc pas de la vente de l'urgence elle-même, mais de la **plateforme de confiance, de coordination, de vérification, de pilotage, d'intégration et de services professionnels** construite autour de ces usages.

---

# 2. Problème adressé

## 2.1 Alertes

Dans de nombreux contextes, une déclaration citoyenne utile reste fragmentée entre :

- bouche-à-oreille ;
- réseaux sociaux ;
- groupes de messagerie ;
- appels téléphoniques ;
- déplacements physiques ;
- commissariats, brigades, services de secours et administrations qui ne partagent pas toujours le même système ;
- absence de suivi numérique vérifiable après transmission.

Le citoyen peut avoir du mal à savoir si une information a simplement été publiée, réellement transmise ou effectivement prise en charge.

Wasplex transforme cette fragmentation en un parcours structuré :

```text
Déclaration citoyenne
→ qualification
→ projection publique sûre si autorisée
→ transmission à une institution vérifiée
→ accusé de réception réel
→ intervention
→ résolution
→ historique
```

## 2.2 Santé

Dans une situation médicale, l'information utile peut être indisponible au mauvais moment : allergies critiques, groupe sanguin, traitements vitaux, instructions d'urgence ou contact à prévenir.

En parallèle, donner un accès général à un dossier Santé serait disproportionné et risqué.

Wasplex répond par une approche de **projection minimale et contrôlée** :

- capsule médicale d'urgence ;
- consentement explicite ;
- accès normal demandé par un professionnel vérifié ;
- autorisation temporaire ;
- traçabilité de chaque accès ;
- séparation stricte avec la publicité et le Wallet.

---

# 3. Proposition de valeur

## Pour le citoyen

- déclarer une situation une seule fois ;
- suivre son dossier ;
- savoir si une institution a réellement reçu ou traité le dossier ;
- éviter l'exposition publique des données privées ;
- bénéficier d'une mobilisation communautaire contrôlée ;
- conserver une capsule médicale d'urgence ;
- accepter ou refuser les demandes d'accès Santé ;
- consulter l'historique des accès.

## Pour les institutions de sécurité et de secours

- recevoir des dossiers structurés ;
- travailler dans un espace professionnel vérifié ;
- disposer de rôles nominatifs et non de comptes collectifs ;
- suivre la progression des dossiers ;
- réduire les informations non structurées ;
- disposer d'un historique auditable ;
- préparer l'interopérabilité future entre services et territoires.

## Pour les professionnels et établissements Santé

- disposer d'une identité professionnelle vérifiée ;
- demander un accès Santé dans un cadre explicite ;
- obtenir un accès limité dans le temps lorsqu'il est autorisé ;
- accéder à une projection minimale d'urgence selon les droits et consentements ;
- laisser une trace auditable de l'accès.

## Pour Wasplex

- devenir un intermédiaire numérique de confiance ;
- créer un réseau à forte utilité sociale ;
- construire une infrastructure institutionnelle réutilisable ;
- augmenter l'utilité quotidienne de l'écosystème sans dépendre uniquement de la publicité ;
- créer des services B2B/B2G autour de la coordination, de la vérification, du pilotage et de l'intégration.

---

# 4. Produit actuellement construit

Le socle produit comprend déjà :

## Alertes citoyennes

- déclaration citoyenne ;
- catégories et situations ;
- position exacte facultative protégée ;
- publication distincte de la soumission ;
- projection publique minimale ;
- intégration au Feed ;
- historique « Mes déclarations » ;
- statuts institutionnels fondés sur des actions réelles.

## Santé citoyen

- identité patient ;
- capsule médicale d'urgence ;
- informations auto-déclarées clairement identifiées comme telles ;
- consentement d'urgence ;
- représentants ;
- historique des accès ;
- demandes professionnelles visibles dans Santé → Accès.

## Wasplex Pro / P019

- organisations professionnelles ;
- vérification institutionnelle ;
- espaces professionnels ;
- rôles nominatifs ;
- capacités par rôle et type d'institution ;
- territoires ;
- points de service ;
- revue administrative ;
- dossiers institutionnels dans Wasplex Pro.

## Raccordement institutionnel

### Alertes ↔ Institutions

```text
Citoyen
→ Wasplex
→ institution vérifiée
→ dossier reçu
→ intervention
→ résolution
→ retour d'état vers le citoyen
```

### Santé ↔ Professionnels

```text
Professionnel vérifié
→ demande d'accès
→ décision patient
→ accès temporaire
→ consultation
→ journalisation
```

---

# 5. Principes économiques non négociables

Le Business Plan doit préserver les règles suivantes :

1. **Un SOS ne doit jamais être bloqué par un paiement.**
2. Une alerte vitale ne doit pas recevoir une meilleure priorité opérationnelle parce qu'un utilisateur a payé.
3. La capsule médicale d'urgence ne doit pas devenir un produit publicitaire.
4. Les données médicales et les données Alertes ne peuvent pas servir au ciblage publicitaire.
5. Le Wallet ne contient pas de données médicales.
6. Une institution ne paie pas pour obtenir un accès libre à la base citoyenne.
7. Les accès professionnels restent déterminés par les capacités, le territoire, le contexte et le consentement lorsque requis.
8. Le statut « pris en charge » ne peut jamais être acheté ou simulé.
9. Les informations auto-déclarées ne doivent jamais être présentées comme médicalement certifiées.

Ces règles renforcent la confiance, qui constitue elle-même un actif économique majeur de Wasplex.

---

# 6. Segments de clientèle et parties prenantes

## B2C — Citoyens

Le citoyen utilise principalement le service pour :

- signaler ;
- retrouver ;
- suivre ;
- se protéger ;
- gérer ses consentements ;
- conserver une capsule d'urgence.

Le cœur d'urgence doit rester gratuit. Des services facultatifs non vitaux peuvent être étudiés ultérieurement sans créer une discrimination dans le traitement des urgences.

## B2B — Entreprises et établissements

Exemples de partenaires potentiels :

- cliniques ;
- centres médicaux ;
- pharmacies dans des périmètres compatibles avec la réglementation ;
- sociétés de transport ;
- universités ;
- écoles ;
- centres commerciaux ;
- sociétés de sécurité ;
- assureurs, uniquement dans des cas d'usage strictement consentis et légalement compatibles ;
- entreprises disposant d'équipes terrain.

## B2G / Institutionnel

Exemples :

- collectivités ;
- communes ;
- services de secours ;
- institutions de sécurité ;
- structures publiques de Santé ;
- administrations territoriales ;
- programmes publics de prévention et d'information.

La contractualisation B2G doit porter sur l'infrastructure, l'intégration, le pilotage et le support, jamais sur la vente d'un accès indiscriminé aux données citoyennes.

---

# 7. Modèles de revenus envisageables

Le modèle peut être hybride.

## 7.1 Abonnement professionnel / institutionnel

Un abonnement peut couvrir :

- espace Wasplex Pro ;
- gestion des équipes ;
- points de service ;
- rôles et droits ;
- tableaux de bord ;
- reporting ;
- audit ;
- support ;
- SLA selon contrat ;
- modules de coordination avancés.

La tarification peut être définie par :

- organisation ;
- nombre de sites ;
- nombre d'agents actifs ;
- territoire ;
- niveau de support ;
- volume de dossiers institutionnels ;
- options d'intégration.

## 7.2 Frais d'intégration et de déploiement

Pour les organisations nécessitant une intégration spécifique :

- installation ;
- configuration ;
- migration ;
- formation ;
- intégration API ;
- raccordement au SI existant ;
- personnalisation des workflows ;
- accompagnement sécurité et conformité.

## 7.3 API et interopérabilité

Une offre API entreprise/institution peut monétiser :

- routage de dossiers ;
- synchronisation de statuts ;
- notifications ;
- intégration à un logiciel métier ;
- webhooks ;
- reporting agrégé ;
- authentification institutionnelle.

Les API restent soumises au même modèle de capacités et de minimisation des données.

## 7.4 Tableaux de bord et analytique agrégée

Wasplex peut proposer des indicateurs non personnels tels que :

- volumes de dossiers ;
- catégories d'incidents ;
- temps moyen de réception ;
- temps moyen de résolution ;
- répartition territoriale agrégée ;
- charge opérationnelle ;
- taux de clôture ;
- qualité de service.

Il faut privilégier l'agrégation et éviter la revente de données individuelles.

## 7.5 Services de vérification professionnelle

La vérification d'une organisation ou de professionnels peut être incluse dans un contrat ou facturée comme service administratif selon le marché et la réglementation :

- contrôle documentaire ;
- renouvellement ;
- vérification de sites ;
- mise à jour de statut ;
- gestion des habilitations.

La vérification payée ne garantit jamais une approbation.

## 7.6 Communication institutionnelle utile

Des institutions vérifiées peuvent disposer de mécanismes de communication publique clairement identifiés :

- avis officiels ;
- campagnes de prévention ;
- informations de sécurité ;
- informations Santé publique.

Ces contenus doivent rester distincts de la publicité commerciale et signalés comme institutionnels.

## 7.7 Services Santé futurs

Sous réserve de conformité juridique, médicale et réglementaire, les extensions futures peuvent inclure :

- prise de rendez-vous ;
- orientation vers des établissements ;
- coordination de parcours ;
- services de téléconsultation via partenaires autorisés ;
- gestion de programmes Santé ;
- intégration avec logiciels médicaux ;
- dossier longitudinal ;
- services de prévention.

Ces extensions devront faire l'objet d'un cadrage séparé avant monétisation.

---

# 8. Ce qui ne doit pas constituer une source de revenu

Afin de protéger le modèle de confiance, Wasplex ne doit pas construire son revenu sur :

- la vente de données médicales ;
- la vente de coordonnées privées issues des Alertes ;
- la vente de la géolocalisation précise ;
- l'accès prioritaire payant aux secours ;
- le paiement pour modifier un statut institutionnel ;
- la publicité ciblée à partir d'une maladie ou d'une urgence ;
- la vente d'un accès global à la base citoyenne ;
- des comptes institutionnels non vérifiés présentés comme officiels.

---

# 9. Moteurs de croissance

## Effet réseau citoyen

Plus la communauté est importante, plus les alertes non sensibles peuvent bénéficier d'une diffusion utile et d'un potentiel de correspondance élevé.

## Effet réseau institutionnel

Plus les institutions vérifiées utilisent Wasplex Pro, plus la plateforme devient pertinente comme couche commune de transmission et de suivi.

## Effet de confiance

La valeur augmente si les utilisateurs constatent que :

- les statuts sont vrais ;
- les accès sont tracés ;
- les institutions sont vérifiées ;
- les données ne sont pas revendues ;
- les urgences ne sont pas conditionnées par un paiement.

## Intégration au reste de Wasplex

Alertes & Santé bénéficie déjà de :

- l'identité Wasplex ;
- Mon Espace ;
- le Feed ;
- la couche P019 ;
- les capacités et l'audit ;
- la future interopérabilité avec d'autres modules.

Cette réutilisation réduit le coût marginal par nouveau service.

---

# 10. Structure de coûts

Les principaux coûts à intégrer au Business Plan sont :

## Produit et technologie

- développement ;
- maintenance ;
- hébergement ;
- bases de données ;
- stockage ;
- sauvegardes ;
- observabilité ;
- sécurité ;
- SMS, email et notifications selon les canaux retenus ;
- cartographie et géolocalisation si des fournisseurs externes sont utilisés.

## Confiance et opérations

- vérification des institutions ;
- contrôle documentaire ;
- support aux partenaires ;
- modération des alertes ;
- gestion des abus ;
- gestion des incidents de sécurité ;
- audit.

## Juridique et conformité

- protection des données ;
- conditions d'utilisation ;
- consentements ;
- contrats institutionnels ;
- conformité Santé ;
- responsabilités opérationnelles ;
- cybersécurité ;
- conservation et suppression des données.

## Commercial

- acquisition partenaires ;
- démonstrations ;
- pilotes ;
- intégration ;
- formation ;
- gestion grands comptes ;
- relations institutionnelles.

---

# 11. Indicateurs clés à suivre

## Alertes

- déclarations créées ;
- pourcentage publié publiquement ;
- dossiers transmis à une institution ;
- taux d'accusé de réception ;
- délai transmission → réception ;
- délai réception → intervention ;
- taux de résolution ;
- alertes résolues grâce à une contribution communautaire ;
- taux de faux signalements ou abus ;
- satisfaction citoyenne.

## Santé

- patients avec capsule configurée ;
- taux d'activation du consentement d'urgence ;
- demandes d'accès professionnelles ;
- taux d'approbation/refus ;
- durée moyenne d'accès ;
- accès effectivement utilisés ;
- accès d'urgence ;
- incidents de sécurité ;
- consultations de l'historique d'accès.

## Professionnels et institutions

- organisations demandées ;
- organisations vérifiées ;
- délai de vérification ;
- agents actifs ;
- points de service actifs ;
- dossiers traités par organisation ;
- rétention des organisations ;
- revenu récurrent par organisation si modèle d'abonnement retenu.

---

# 12. Phases de commercialisation recommandées

## Phase 1 — Pilotes contrôlés

Objectif : prouver la valeur opérationnelle.

- quelques institutions de sécurité ou secours ;
- quelques structures Santé ;
- territoire limité ;
- accompagnement rapproché ;
- mesure précise des délais et usages ;
- aucune promesse de couverture nationale prématurée.

## Phase 2 — Offre professionnelle structurée

- catalogue Wasplex Pro ;
- contrats types ;
- niveaux de support ;
- vérification formalisée ;
- onboarding équipe ;
- reporting ;
- API partenaires.

## Phase 3 — Interopérabilité territoriale

- plusieurs communes ou zones ;
- coordination entre points de service ;
- transfert de dossiers ;
- statistiques agrégées ;
- partenariats institutionnels plus larges.

## Phase 4 — Extension Santé

Uniquement après validation réglementaire et clinique :

- établissements partenaires ;
- parcours Santé ;
- dossier longitudinal ;
- intégrations médicales ;
- services complémentaires.

---

# 13. Positionnement concurrentiel

Le positionnement de Wasplex ne doit pas être résumé à « une application d'alertes » ou « une application Santé ».

La différenciation visée est :

> **une couche de confiance reliant un compte citoyen, une communauté, des organisations vérifiées et des professionnels habilités, avec des données minimisées, des états prouvés et un historique auditable.**

Les avantages structurels sont :

- identité unique ;
- espace citoyen + espace professionnel ;
- séparation technique des domaines sensibles ;
- rôles et capacités ;
- vérification institutionnelle ;
- consentement Santé ;
- audit ;
- intégration native au Feed sans transformer l'urgence en publicité ;
- possibilité d'interopérabilité API.

---

# 14. Risques principaux

## Risque de confiance

Un seul faux statut institutionnel peut détériorer fortement la crédibilité du service.

Réponse : statuts fondés uniquement sur des actions réelles et auditables.

## Risque de confidentialité

Les données Alertes et Santé peuvent être très sensibles.

Réponse : chiffrement, minimisation, consentement, séparation des domaines et capacités.

## Risque réglementaire Santé

Les services médicaux sont fortement encadrés.

Réponse : conserver la distinction entre donnée auto-déclarée et donnée certifiée ; déployer les fonctions cliniques uniquement avec partenaires et cadre juridique adaptés.

## Risque d'usurpation institutionnelle

Une fausse organisation peut créer un risque grave.

Réponse : espace professionnel en attente par défaut, vérification avant capacités sensibles et comptes nominatifs.

## Risque de dépendance institutionnelle

Les cycles de décision publics peuvent être longs.

Réponse : stratégie hybride B2C/B2B/B2G et pilotes limités permettant de démontrer la valeur sans dépendre d'un seul contrat national.

## Risque de modèle économique mal aligné

Monétiser l'urgence elle-même détruirait la confiance.

Réponse : facturer l'infrastructure professionnelle, l'intégration, le support, l'analytique agrégée et les services complémentaires plutôt que l'accès vital.

---

# 15. Hypothèses financières à renseigner dans le Business Plan

Ce document ne fixe volontairement aucun chiffre de marché ou tarif non validé.

Le modèle financier devra renseigner au minimum :

| Variable | Hypothèse à définir |
|---|---|
| Citoyens actifs mensuels | À valider par scénario |
| Organisations vérifiées | À valider par année |
| Prix moyen abonnement Pro | À tester sur pilotes |
| Frais moyen d'onboarding | À calculer selon effort réel |
| Agents actifs / organisation | À mesurer |
| Dossiers / organisation / mois | À mesurer |
| Coût infrastructure / utilisateur | À mesurer en production |
| Coût support / organisation | À mesurer |
| Coût vérification | À mesurer |
| Marge brute B2B/B2G | À calculer |
| Taux de rétention organisations | À mesurer |
| CAC professionnel/institutionnel | À mesurer |

Trois scénarios sont recommandés : **prudent, central et accéléré**.

---

# 16. Formulation Business Plan courte

> **Wasplex Alertes & Santé est une infrastructure numérique de confiance qui permet aux citoyens de signaler et suivre des situations sensibles, aux institutions vérifiées de recevoir et traiter des dossiers structurés, et aux professionnels Santé habilités d'accéder à des informations minimales selon des règles de consentement et de traçabilité. Le modèle économique repose principalement sur les espaces professionnels, l'intégration institutionnelle, les API, le support, l'audit et les services à valeur ajoutée, tandis que l'urgence et la protection citoyenne fondamentale restent indépendantes du paiement.**

---

# 17. Points à compléter avant présentation investisseurs ou banques

Avant intégration dans un Business Plan financier final, compléter avec :

- taille du marché prioritaire ;
- territoire de lancement ;
- nombre d'institutions ciblées ;
- lettres d'intérêt et pilotes ;
- tarification testée ;
- coût réel d'onboarding ;
- réglementation applicable ;
- partenaires Santé ;
- stratégie de distribution ;
- projections financières 3 à 5 ans ;
- besoins de financement ;
- calendrier de déploiement.

---

# 18. Références internes du dépôt

Ce document doit être lu avec :

- `docs/02-module-alertes-sante-wasplex.md` ;
- `docs/chantiers/P015-A-CHANTIER.md` ;
- `docs/chantiers/P016-A-CHANTIER.md` ;
- `docs/chantiers/P019-CHANTIER.md` ;
- `docs/chantiers/P019-RAPPORT.md`.

Le présent fichier est volontairement orienté **business et Business Plan** ; les documents ci-dessus restent les références produit et techniques.