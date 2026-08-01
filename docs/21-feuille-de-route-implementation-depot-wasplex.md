# WASPLEX — FEUILLE DE ROUTE D’IMPLÉMENTATION DU DÉPÔT

**Version :** 1.0  
**Fichier cible recommandé :** `docs/20-roadmap/00-feuille-de-route-implementation-depot-wasplex.md`  
**Nom du livrable final à générer dans le dépôt :** `IMPLEMENTATION-ROADMAP-WASPLEX.md`  
**Statut :** protocole officiel d’analyse, de cartographie et de génération de la feuille de route réelle  
**Nature :** document méthodologique directement exploitable par un modèle de langage de très haut niveau placé dans le dépôt Wasplex  
**Dépendances :** toutes les notes Wasplex 00 à 20 et le dépôt réel  
**Stack de référence :** PHP Laravel, PostgreSQL, Redis, Tailwind CSS, Vite, stockage S3 compatible, temps réel Laravel  
**Principe central :** la feuille de route finale doit être produite après analyse complète des notes et audit du dépôt réel ; elle ne doit pas être inventée à distance ni imposée avant de connaître l’état exact du code  
**Directive produit :** produire uniquement une méthode de construction, des dépendances, des chantiers, des critères d’acceptation, des tests et un ordre d’exécution ; ne créer aucune constitution, doctrine ou gouvernance bloquante

---

# 1. Objet

Ce document définit la mission qui devra être confiée au modèle supérieur chargé de transformer les spécifications Wasplex en une feuille de route de codage réelle.

Le modèle devra :

- lire toutes les notes officielles ;
- auditer le dépôt en lecture seule ;
- comprendre la stack réellement présente ;
- cartographier les modules ;
- identifier les dépendances ;
- identifier les éléments existants ;
- identifier les éléments incomplets ;
- identifier les contradictions ;
- classer les risques ;
- proposer l’ordre de construction ;
- définir les verticales ;
- découper le travail en chantiers ;
- produire les critères de fusion ;
- produire le plan d’exécution ;
- préparer le dépôt au codage méthodique.

---

# 2. Nature exacte de la note 21

La présente note n’est pas encore la roadmap finale du dépôt.

Elle est :

```text
le protocole
→ la méthode
→ le format attendu
→ les règles d’analyse
→ les livrables obligatoires
```

La roadmap finale sera générée seulement lorsque :

```text
toutes les notes sont présentes
+ le dépôt est accessible
+ l’audit a été réalisé
+ les écarts ont été identifiés
```

---

# 3. Pourquoi ne pas figer immédiatement la roadmap

Une roadmap écrite sans voir le dépôt peut :

- recommander des travaux déjà réalisés ;
- ignorer une structure existante ;
- casser des modules ;
- choisir un mauvais ordre ;
- créer des doublons ;
- imposer des migrations inutiles ;
- ignorer des dépendances techniques ;
- méconnaître le framework ou les versions ;
- produire des conflits.

La roadmap finale doit donc être fondée sur :

```text
spécifications officielles
+ réalité du dépôt
+ dépendances techniques
+ état du code
```

---

# 4. Rôle du modèle supérieur

Le modèle supérieur agit comme :

```text
architecte d’implémentation
+ auditeur technique
+ cartographe du dépôt
+ planificateur des chantiers
```

Il n’agit pas encore comme développeur pendant la première phase.

---

# 5. Modèles adaptés

La mission doit être confiée à un modèle capable de :

- lire de nombreux documents ;
- maintenir une vision globale ;
- inspecter un dépôt important ;
- raisonner sur les dépendances ;
- détecter les contradictions ;
- produire un plan long et cohérent ;
- travailler méthodiquement.

Le choix exact du modèle dépendra des outils disponibles au moment de l’exécution.

---

# 6. Autorité des sources

Ordre de référence :

```text
1. décisions explicites du fondateur
2. notes officielles Wasplex
3. architecture technique officielle
4. état réel du dépôt
5. conventions techniques existantes compatibles
```

Le dépôt ne doit pas annuler silencieusement une décision produit.

Une ancienne implémentation incompatible doit être signalée.

---

# 7. Anciennes notes et anciens dépôts

Les anciens documents peuvent servir à :

- comprendre une idée ;
- récupérer une terminologie ;
- identifier un historique ;
- repérer un besoin.

Ils ne deviennent pas automatiquement :

- la source de vérité ;
- un modèle de code ;
- une constitution ;
- une contrainte bloquante.

---

# 8. Prérequis documentaires

Avant l’audit, le dépôt doit contenir les notes officielles :

```text
00 — Identité visuelle
01 — Fonds
02 — Alertes & Santé
03 — Abonnements et classes économiques
04 — Matching publicitaire
05 — Modèle économique publicitaire
06 — Wallet & Grand Livre
07 — Super moteur de valeur
08 — Feed principal
09 — Compte universel & Mon Espace
10 — Carte Wasplex
11 — Live Wasplex
12 — Administration centrale
13 — Studio Annonceur
14 — Espaces professionnels et institutionnels
15 — Notifications et messagerie
16 — Modération, sécurité et antifraude
17 — Données, permissions et consentements techniques
18 — Reporting, audit et observabilité
19 — Intégrations externes
20 — Architecture technique générale
21 — Feuille de route d’implémentation
```

---

# 9. Prérequis dépôt

Le modèle doit disposer de :

- la branche principale ;
- l’historique Git ;
- le fichier README ;
- les fichiers Composer ;
- les fichiers npm ;
- les migrations ;
- les routes ;
- les modules ;
- les tests ;
- les configurations ;
- les scripts ;
- les workflows CI ;
- les fichiers d’infrastructure ;
- les documentation existantes.

---

# 10. Phase zéro — Préparation

Avant l’audit :

1. vérifier que le dépôt est propre ;
2. vérifier la branche ;
3. relever le commit de base ;
4. ne modifier aucun fichier ;
5. ne lancer aucune migration destructive ;
6. ne supprimer aucun ancien document ;
7. créer un rapport d’état initial.

---

# 11. Audit en lecture seule

La première analyse doit être strictement en lecture seule.

Autorisé :

- lire ;
- rechercher ;
- lister ;
- analyser ;
- exécuter des commandes non destructives ;
- lancer les tests existants ;
- inspecter les migrations ;
- générer des rapports hors du dépôt si nécessaire.

Interdit :

- modifier ;
- formater automatiquement ;
- créer une migration ;
- installer une dépendance sans accord ;
- supprimer ;
- renommer ;
- merger ;
- pousser.

---

# 12. Commandes non destructives possibles

Exemples :

```bash
git status
git branch --show-current
git log --oneline --decorate -n 30
git diff --stat
find .
grep
php --version
composer --version
composer show
php artisan --version
php artisan route:list
php artisan test
npm --version
npm list
```

Le modèle doit adapter les commandes au dépôt.

---

# 13. Inventaire de la stack réelle

Le rapport doit confirmer :

- version PHP ;
- version Laravel ;
- version PostgreSQL attendue ;
- usage Redis ;
- Tailwind CSS ;
- Vite ;
- Blade ;
- Livewire ;
- Inertia ;
- Vue ;
- React ;
- Alpine ;
- outils de tests ;
- stockage ;
- temps réel ;
- infrastructure.

---

# 14. Décision frontend

Le modèle doit déterminer ce que le dépôt utilise réellement.

Il doit ensuite proposer une seule direction cohérente :

```text
Blade + Alpine
ou
Blade + Livewire
ou
Inertia + Vue
ou
approche hybride limitée et justifiée
```

Il ne doit pas introduire plusieurs frameworks concurrents sans nécessité.

---

# 15. Vérification de la stack officielle

Le modèle doit comparer le dépôt à :

```text
PHP Laravel
PostgreSQL
Redis
Tailwind CSS
Vite
S3 compatible
temps réel Laravel
```

Il doit produire :

- conforme ;
- partiellement conforme ;
- absent ;
- incompatible ;
- à confirmer.

---

# 16. Inventaire des applications

Identifier :

- application utilisateur ;
- Studio Annonceur ;
- portail professionnel ;
- administration ;
- backend ;
- workers ;
- scheduler ;
- outils internes.

---

# 17. Inventaire des modules

Pour chaque module :

- emplacement ;
- état ;
- entités ;
- migrations ;
- services ;
- routes ;
- événements ;
- tests ;
- interfaces ;
- dépendances ;
- documentation.

---

# 18. États possibles d’un module

```text
absent
skeleton
partial
functional
tested
integrated
production_ready
legacy
deprecated
conflicting
```

---

# 19. Matrice de couverture

Le modèle doit créer une matrice :

| Module | Note | Code existant | Tests | UI | Intégration | État |
|---|---|---|---|---|---|---|

Cette matrice doit couvrir tous les modules.

---

# 20. Analyse des migrations

Vérifier :

- ordre ;
- doublons ;
- tables ;
- préfixes ;
- contraintes ;
- types monétaires ;
- index ;
- clés étrangères ;
- incohérences ;
- migrations destructives ;
- dépendances inter-modules.

---

# 21. Analyse du Grand Livre

Vérifier :

- double entrée ;
- débits/crédits ;
- append-only ;
- comptes ;
- transactions ;
- écritures ;
- idempotence ;
- compensation ;
- projections Wallet ;
- tests ;
- accès direct aux soldes.

---

# 22. Recherche des écritures directes de solde

Le modèle doit rechercher les motifs susceptibles de modifier un solde directement.

Exemples :

```text
balance =
increment balance
decrement balance
wallet->update
raw SQL
```

Chaque cas doit être classé :

- légitime projection ;
- suspect ;
- violation ;
- à examiner.

---

# 23. Analyse des frontières

Pour chaque module, relever :

- tables possédées ;
- tables lues ;
- tables écrites ;
- contrats ;
- événements ;
- appels directs ;
- dépendances circulaires.

---

# 24. Carte des dépendances

Le livrable doit contenir un graphe :

```text
Identity
→ Accounts
→ Organizations
→ Subscriptions
→ Advertising
→ Matching
→ Feed
→ ValueEngine
→ Ledger
→ Wallet
```

et les autres branches :

```text
Funds
Alerts
Health
Card
Partners
Live
Administration
Reporting
Integrations
```

Le graphe final doit refléter le dépôt réel.

---

# 25. Analyse des routes

Classer :

```text
/api/v1/me
/api/v1/advertiser
/api/v1/professional
/api/v1/admin
/internal
/webhooks
```

Identifier :

- doublons ;
- incohérences ;
- routes non protégées ;
- contrôleurs trop larges ;
- versions absentes.

---

# 26. Analyse des permissions

Vérifier :

- rôles ;
- capacités ;
- espaces ;
- organisations ;
- périmètres ;
- MFA ;
- administration ;
- accès Santé ;
- accès financiers ;
- founder override.

---

# 27. Analyse des événements

Créer un inventaire :

- nom ;
- version ;
- producteur ;
- consommateurs ;
- transport ;
- outbox ;
- idempotence ;
- tests.

---

# 28. Analyse des queues et workers

Vérifier :

- files ;
- priorités ;
- retries ;
- dead letters ;
- idempotence ;
- supervision ;
- timeouts ;
- jobs financiers ;
- notifications ;
- médias ;
- reporting.

---

# 29. Analyse des intégrations

Pour chaque prestataire :

- SDK ;
- adaptateur ;
- contrat ;
- secrets ;
- webhook ;
- idempotence ;
- statut ;
- tests ;
- sandbox ;
- observabilité.

---

# 30. Analyse du frontend

Vérifier :

- Tailwind ;
- design system ;
- tokens ;
- composants ;
- responsive ;
- mobile-first utilisateur ;
- desktop annonceur ;
- desktop professionnel ;
- desktop administration ;
- accessibilité ;
- duplication CSS.

---

# 31. Analyse Tailwind

Le rapport doit identifier :

- configuration ;
- version ;
- fichiers scannés ;
- thème ;
- couleurs Wasplex ;
- composants ;
- styles globaux ;
- classes dupliquées ;
- build Vite ;
- production CSS.

---

# 32. Analyse des tests

Classer :

- unitaires ;
- intégration ;
- contrat ;
- end-to-end ;
- concurrence ;
- sécurité ;
- responsive ;
- visuels ;
- reprise.

---

# 33. Analyse CI/CD

Vérifier :

- lint ;
- formatage ;
- analyse statique ;
- tests ;
- migrations ;
- build Vite ;
- Tailwind ;
- déploiement ;
- rollback ;
- secrets ;
- environnements.

---

# 34. Analyse de l’infrastructure

Relever :

- Nginx ;
- PHP-FPM ;
- PostgreSQL ;
- Redis ;
- workers ;
- scheduler ;
- stockage ;
- certificats ;
- sauvegardes ;
- observabilité ;
- environnements.

---

# 35. Analyse des risques

Catégories :

```text
financial
security
data
architecture
performance
operations
user_experience
integration
deployment
documentation
```

---

# 36. Gravité des écarts

```text
critical
high
medium
low
information
```

---

# 37. Rapport d’écart

Chaque écart doit préciser :

- note concernée ;
- code concerné ;
- description ;
- impact ;
- gravité ;
- recommandation ;
- dépendances ;
- chantier proposé.

---

# 38. Contradictions entre notes

Le modèle doit signaler :

- textes incompatibles ;
- chiffres différents ;
- machines d’états différentes ;
- permissions divergentes ;
- doublons ;
- noms différents.

Il ne doit pas résoudre arbitrairement une contradiction importante.

---

# 39. Contradictions code / note

Le rapport doit distinguer :

```text
code incomplet
code ancien
code incompatible
note imprécise
décision manquante
```

---

# 40. Questions au fondateur

Les questions doivent être :

- limitées ;
- concrètes ;
- bloquantes ;
- accompagnées d’options ;
- accompagnées d’un impact.

Éviter les questions théoriques sans conséquence de codage.

---

# 41. Validation de l’audit

Avant la roadmap finale :

```text
rapport d’audit
→ présentation au fondateur
→ réponses
→ décisions
→ mise à jour
→ roadmap
```

---

# 42. Livrables de la phase d’audit

Le modèle doit produire :

```text
REPO-AUDIT-WASPLEX.md
STACK-BASELINE-WASPLEX.md
MODULE-MAP-WASPLEX.md
DEPENDENCY-GRAPH-WASPLEX.md
GAP-MATRIX-WASPLEX.md
RISK-REGISTER-WASPLEX.md
OPEN-DECISIONS-WASPLEX.md
```

---

# 43. Nature de la roadmap finale

La roadmap finale doit être :

- ordonnée ;
- exécutable ;
- testable ;
- progressive ;
- liée aux fichiers ;
- liée aux notes ;
- liée aux dépendances ;
- liée aux critères d’acceptation ;
- liée aux branches ;
- liée aux preuves de fin.

---

# 44. Unité de travail

Nom recommandé :

```text
chantier
```

Code :

```text
P000
P001
P002
...
```

---

# 45. Contenu obligatoire d’un chantier

Chaque chantier contient :

1. identifiant ;
2. titre ;
3. objectif ;
4. raison ;
5. notes sources ;
6. dépendances ;
7. prérequis ;
8. périmètre inclus ;
9. périmètre exclu ;
10. fichiers ou modules concernés ;
11. migrations ;
12. entités ;
13. API ;
14. événements ;
15. permissions ;
16. écrans ;
17. tests ;
18. captures ;
19. données de démonstration ;
20. critères d’acceptation ;
21. risques ;
22. plan de rollback ;
23. conditions de fusion ;
24. chantier suivant.

---

# 46. Taille d’un chantier

Un chantier doit être :

- suffisamment petit pour être compris ;
- suffisamment complet pour produire une valeur ;
- testable ;
- fusionnable ;
- démontrable ;
- sans dépendance cachée.

Éviter les chantiers de plusieurs mois sans résultat visible.

---

# 47. Verticale fonctionnelle

Une verticale traverse plusieurs couches.

Exemple :

```text
interface
→ API
→ cas d’usage
→ PostgreSQL
→ événement
→ worker
→ temps réel
→ test
```

---

# 48. Priorité aux verticales complètes

Préférer :

```text
une chaîne complète qui fonctionne
```

à :

```text
dix modules incomplets
```

---

# 49. Première verticale économique

La première verticale probable est :

```text
Compte utilisateur minimal
→ espace annonceur
→ marque
→ recharge Wallet annonceur
→ campagne
→ audience
→ budget réservé
→ revue admin
→ approbation
→ Matching
→ Feed
→ attention qualifiée
→ Grand Livre
→ Wallet utilisateur
→ notification
→ reporting annonceur
→ audit
```

Cette hypothèse doit être confirmée par l’audit.

---

# 50. Pourquoi cette verticale

Elle prouve :

- Compte universel ;
- espace annonceur ;
- Wallet annonceur ;
- campagne ;
- Matching ;
- Feed ;
- moteur de valeur ;
- Ledger ;
- Wallet utilisateur ;
- temps réel ;
- reporting ;
- administration ;
- intégration de paiement.

---

# 51. Hypothèse initiale de grands chantiers

Cette liste n’est pas la roadmap finale.

```text
P000 — Socle du dépôt et stack
P001 — Identité et espaces minimaux
P002 — Grand Livre et Wallet minimal
P003 — Configurations économiques
P004 — Abonnements et classes
P005 — Wallet annonceur
P006 — Marques et campagnes
P007 — Profil intelligent et Matching minimal
P008 — Feed publicitaire
P009 — Attention qualifiée
P010 — Crédit Wallet temps réel
P011 — Reporting économique
P012 — Administration économique
P013 — Fonds
P014 — Alertes
P015 — Santé
P016 — Carte et partenaires
P017 — Live
P018 — Espaces professionnels
P019 — Stabilisation
```

L’audit peut fusionner, déplacer ou scinder ces chantiers.

---

# 52. Chantier P000 — Socle

Doit probablement couvrir :

- vérification Laravel ;
- PostgreSQL ;
- Redis ;
- Tailwind ;
- Vite ;
- structure modules ;
- CI ;
- environnements ;
- logs ;
- health checks ;
- conventions.

---

# 53. Chantier identité

Doit probablement couvrir :

- compte ;
- session ;
- appareil ;
- espace ;
- organisation ;
- rôle ;
- capacité ;
- MFA admin ;
- sélecteur d’espace.

---

# 54. Chantier Ledger

Doit probablement couvrir :

- comptes ;
- journaux ;
- transactions ;
- écritures ;
- double entrée ;
- idempotence ;
- compensation ;
- audit ;
- tests de concurrence.

---

# 55. Chantier Wallet

Doit probablement couvrir :

- projection ;
- compartiments ;
- historique ;
- reconstruction ;
- API ;
- temps réel futur.

---

# 56. Chantier configurations économiques

Doit probablement couvrir :

- plans ;
- quotas ;
- poids ;
- frais ;
- versions ;
- publication ;
- simulation ;
- audit.

---

# 57. Chantier Studio Annonceur minimal

Doit probablement couvrir :

- activation espace ;
- marque ;
- Wallet annonceur ;
- dépôt sandbox ;
- campagne rapide ;
- devis ;
- budget ;
- soumission.

---

# 58. Chantier revue administrative

Doit probablement couvrir :

- file ;
- média ;
- ciblage ;
- correction ;
- approbation ;
- rejet ;
- suspension ;
- audit.

---

# 59. Chantier Matching minimal

Doit probablement couvrir :

- profil volontaire minimal ;
- consentement ;
- critères ;
- segment ;
- éligibilité ;
- explication ;
- estimation.

---

# 60. Chantier Feed minimal

Doit probablement couvrir :

- shell mobile ;
- vidéo ;
- publicité ;
- barre de progression ;
- quota ;
- interactions minimales ;
- attention.

---

# 61. Chantier moteur de valeur minimal

Doit probablement couvrir :

- quote ;
- attempt ;
- réservation ;
- preuve ;
- validation ;
- capture ;
- release ;
- idempotence.

---

# 62. Chantier temps réel

Doit probablement couvrir :

- outbox ;
- worker ;
- projection ;
- Reverb/WebSocket ;
- animation Wallet ;
- notification ;
- reprise.

---

# 63. Chantier reporting

Doit probablement couvrir :

- budget ;
- livraison ;
- attention ;
- dépenses ;
- gain ;
- dashboard annonceur ;
- dashboard fondateur ;
- audit.

---

# 64. Dépendances bloquantes

La roadmap doit identifier :

- migrations nécessaires ;
- contrats requis ;
- décisions manquantes ;
- prestataire absent ;
- données de test ;
- design manquant ;
- infrastructure ;
- secret ;
- environnement.

---

# 65. Dépendances non bloquantes

Exemples :

- email marketing ;
- export avancé ;
- replay Live ;
- moteur de recherche dédié ;
- carte physique ;
- IA créative.

Elles peuvent être reportées.

---

# 66. Périmètre V1

La roadmap doit définir clairement :

```text
must_have
should_have
later
excluded
```

---

# 67. Interdiction du scope creep

Un chantier ne doit pas absorber :

- toutes les futures idées ;
- toutes les intégrations ;
- toutes les interfaces ;
- tous les pays ;
- toute l’IA ;
- toute l’automatisation.

---

# 68. Données de démonstration

Chaque chantier visible doit fournir :

- comptes ;
- organisations ;
- marques ;
- campagnes ;
- Wallets ;
- transactions ;
- événements ;
- utilisateurs ;
- états d’erreur.

---

# 69. Environnement de démonstration

La roadmap doit prévoir :

- seeders ;
- prestataires simulés ;
- stockage local ;
- données synthétiques ;
- comptes par rôle ;
- scénarios reproductibles.

---

# 70. Definition of Ready

Un chantier est prêt lorsque :

- notes identifiées ;
- dépendances disponibles ;
- décisions prises ;
- périmètre clair ;
- critères écrits ;
- environnement prêt ;
- données de test disponibles.

---

# 71. Definition of Done

Un chantier est terminé lorsque :

- code ;
- migrations ;
- API ;
- permissions ;
- événements ;
- tests ;
- documentation ;
- captures ;
- audit ;
- CI ;
- rapport ;
- critères validés.

---

# 72. Branches de chantier

Nom recommandé :

```text
agent/p000-platform-foundation
agent/p001-identity-spaces
agent/p002-ledger-wallet
```

Le modèle doit respecter les conventions réelles du dépôt.

---

# 73. Worktrees

Pour les travaux parallèles :

- un chantier ;
- une branche ;
- un worktree ;
- un responsable ;
- aucun chevauchement non coordonné.

---

# 74. Commit de base

Chaque chantier doit déclarer :

```text
base_commit
branch
worktree
owner
started_at
```

---

# 75. Commits

Règles :

- petits ;
- cohérents ;
- descriptifs ;
- tests associés ;
- aucun secret ;
- pas de fichier temporaire ;
- pas d’effacement d’historique.

---

# 76. Fusion

Avant fusion :

- branche à jour ;
- tests ;
- migrations ;
- diff ;
- sécurité ;
- capture ;
- rapport ;
- critères ;
- conflits résolus.

---

# 77. Rapport de chantier

Nom possible :

```text
P002-COMPLETION-REPORT.md
```

Contenu :

- objectif ;
- code ajouté ;
- migrations ;
- tests ;
- captures ;
- limites ;
- décisions ;
- commit ;
- étapes suivantes.

---

# 78. Tests obligatoires par chantier

Selon le domaine :

- unitaires ;
- intégration ;
- contrat ;
- end-to-end ;
- concurrence ;
- sécurité ;
- responsive ;
- reprise ;
- visuels.

---

# 79. Tests financiers

Obligatoires pour :

- Ledger ;
- Wallet ;
- campagne ;
- Fonds ;
- Live ;
- partenaire ;
- remboursement.

Ils doivent couvrir :

- double exécution ;
- concurrence ;
- compensation ;
- arrondi ;
- devise ;
- idempotence.

---

# 80. Tests responsive

Matrice :

```text
Utilisateur
→ mobile strict
→ desktop shell mobile

Annonceur
→ mobile complet
→ desktop complet

Professionnel
→ mobile terrain
→ tablette
→ desktop

Administration
→ desktop
→ mobile urgence
```

---

# 81. Captures obligatoires

Chaque chantier UI doit fournir :

- mobile ;
- tablette si concernée ;
- desktop si concerné ;
- état normal ;
- chargement ;
- vide ;
- erreur ;
- succès ;
- accès refusé.

---

# 82. Vérification design system

Chaque chantier frontend doit vérifier :

- Tailwind ;
- tokens ;
- composants ;
- cohérence ;
- accessibilité ;
- responsive ;
- absence de CSS dispersé.

---

# 83. Vérification sécurité

Chaque chantier doit vérifier :

- authentification ;
- capacité ;
- périmètre ;
- MFA ;
- validation ;
- idempotence ;
- audit ;
- fichiers ;
- secrets ;
- rate limit.

---

# 84. Vérification observabilité

Chaque chantier critique doit fournir :

- logs ;
- métriques ;
- traces ;
- health checks ;
- alertes ;
- erreurs exploitables.

---

# 85. Vérification performance

Définir :

- volume attendu ;
- requêtes ;
- index ;
- files ;
- cache ;
- latence ;
- pagination ;
- traitement asynchrone.

---

# 86. Vérification rollback

Chaque chantier doit indiquer :

- comment désactiver ;
- comment restaurer ;
- comment compenser ;
- comment revenir au code précédent ;
- comment traiter les données créées.

---

# 87. Ordre de livraison

La roadmap doit distinguer :

```text
fondations
→ verticale économique
→ modules sociaux et institutionnels
→ extension
→ optimisation
```

---

# 88. Fondations avant modules avancés

Fondations probables :

- stack ;
- identité ;
- capacités ;
- Ledger ;
- Wallet ;
- configurations ;
- outbox ;
- observabilité.

---

# 89. Ne pas construire le moteur entier avant les usages

Le moteur de valeur doit être développé progressivement.

Correct :

```text
noyau minimal
→ publicité
→ Live
→ partenaire
→ Fonds
```

Incorrect :

```text
moteur universel complet
→ aucun module réel
```

---

# 90. Ne pas construire toutes les interfaces avant le cœur

Priorité :

```text
parcours complet
→ qualité
→ extension
```

et non :

```text
tous les écrans statiques
→ aucune transaction réelle
```

---

# 91. Critères de priorité

Un chantier est prioritaire s’il :

- débloque plusieurs modules ;
- protège la valeur ;
- réduit un risque critique ;
- prouve le modèle économique ;
- permet une verticale ;
- réduit une dette bloquante ;
- apporte une démonstration.

---

# 92. Coût de retard

Le modèle peut classer :

- blocage économique ;
- blocage technique ;
- blocage produit ;
- amélioration ;
- confort ;
- futur.

---

# 93. Chemin critique

La roadmap doit afficher le chemin critique.

Exemple hypothétique :

```text
Stack
→ Identity
→ Ledger
→ Wallet annonceur
→ Campaign
→ Matching
→ Feed
→ Value
→ Wallet utilisateur
```

---

# 94. Travaux parallélisables

Exemples possibles :

- design system ;
- simulateur prestataire ;
- audit ;
- seeders ;
- documentation ;
- composants admin.

Le modèle doit éviter les parallélisations créant des conflits.

---

# 95. Cartographie des propriétaires

Chaque chantier indique :

- module propriétaire ;
- équipe ou agent ;
- reviewer ;
- fondateur pour décision ;
- dépendances externes.

---

# 96. Points de validation du fondateur

Validation nécessaire pour :

- contradiction métier ;
- changement économique ;
- changement navigation ;
- remplacement stack ;
- changement Ledger ;
- accès Santé ;
- priorité Alertes ;
- modification importante du scope.

---

# 97. Intervention exceptionnelle du fondateur

Le fondateur peut :

- modifier l’ordre ;
- suspendre un chantier ;
- fusionner ;
- scinder ;
- autoriser une correction ;
- refuser une recommandation.

La décision doit être documentée, sans créer une gouvernance bloquante.

---

# 98. Questions non bloquantes

Les questions non bloquantes sont enregistrées dans :

```text
OPEN-DECISIONS-WASPLEX.md
```

Le chantier peut avancer avec une hypothèse explicitement réversible si elle ne risque pas la valeur ou la sécurité.

---

# 99. Questions bloquantes

Une question est bloquante si elle concerne :

- argent ;
- données sensibles ;
- identité ;
- priorité vitale ;
- architecture irréversible ;
- suppression ;
- contrat externe réel ;
- changement de stack.

---

# 100. Feuille de route lisible par humain

Le document final doit commencer par :

- résumé ;
- état du dépôt ;
- grandes phases ;
- chemin critique ;
- première verticale ;
- risques ;
- décisions ouvertes.

---

# 101. Feuille de route exploitable par agent

Chaque chantier doit aussi contenir une section structurée :

```yaml
id:
title:
depends_on:
modules:
source_notes:
included:
excluded:
migrations:
apis:
events:
permissions:
tests:
acceptance:
```

Le YAML est descriptif, pas une configuration métier.

---

# 102. Index des chantiers

Créer :

```text
ROADMAP-INDEX.md
```

avec :

- identifiant ;
- titre ;
- statut ;
- dépendances ;
- branche ;
- commit ;
- rapport ;
- date.

---

# 103. Statuts de chantier

```text
proposed
approved
ready
in_progress
blocked
review
completed
merged
cancelled
superseded
```

---

# 104. Mise à jour de la roadmap

La roadmap est mise à jour lorsque :

- chantier terminé ;
- dépendance découverte ;
- contradiction résolue ;
- scope modifié ;
- incident ;
- décision fondateur ;
- évolution du dépôt.

Elle reste un document opérationnel, pas une constitution.

---

# 105. Historique des changements

Conserver :

- date ;
- version ;
- changement ;
- raison ;
- auteur ;
- chantiers affectés.

---

# 106. Roadmap et Git

La roadmap doit pointer vers :

- branche ;
- commit ;
- PR ;
- rapport ;
- tests ;
- captures.

---

# 107. Roadmap et tickets

Chaque chantier peut être découpé en tickets techniques.

Mais les tickets ne remplacent pas la vision du chantier.

---

# 108. Roadmap et documentation

Une note fonctionnelle reste la source du besoin.

La roadmap indique comment et quand la réaliser.

---

# 109. Roadmap et architecture

L’architecture technique définit les règles d’assemblage.

La roadmap définit l’ordre d’application dans le dépôt.

---

# 110. Roadmap et document maître

```text
Document maître
→ carte générale du produit

Roadmap
→ ordre réel de construction

Notes
→ spécifications détaillées

Dépôt
→ implémentation
```

---

# 111. Livrables finaux de la note 21

Le modèle supérieur doit produire :

```text
IMPLEMENTATION-ROADMAP-WASPLEX.md
ROADMAP-INDEX.md
FIRST-VERTICAL-WASPLEX.md
REPO-AUDIT-WASPLEX.md
STACK-BASELINE-WASPLEX.md
MODULE-MAP-WASPLEX.md
DEPENDENCY-GRAPH-WASPLEX.md
GAP-MATRIX-WASPLEX.md
RISK-REGISTER-WASPLEX.md
OPEN-DECISIONS-WASPLEX.md
```

---

# 112. Première mission à donner au modèle

```text
Lis toutes les notes Wasplex présentes dans le dépôt.
Audite le dépôt en lecture seule.
Confirme la stack réelle.
Cartographie les applications, modules, données, routes, événements et tests.
Identifie les écarts avec les notes.
Ne modifie aucun fichier métier.
Produis les rapports d’audit.
Ne commence pas le codage.
```

---

# 113. Deuxième mission

Après validation de l’audit :

```text
À partir des notes et du dépôt audité,
produis la feuille de route complète.
Définis le chemin critique.
Définis la première verticale.
Découpe en chantiers codables.
Définis les critères d’acceptation, tests, branches et rapports.
```

---

# 114. Troisième mission

Après validation de la roadmap :

```text
Prépare le chantier P000.
Ne code que son périmètre.
Fournis les tests, captures et rapport.
Attends la validation avant le chantier suivant.
```

---

# 115. Prompt officiel d’audit

```text
MISSION : AUDIT TECHNIQUE ET CARTOGRAPHIE DU DÉPÔT WASPLEX

Tu travailles dans le nouveau dépôt Wasplex.

1. Lis toutes les notes officielles dans l’ordre.
2. Considère les décisions explicites du fondateur comme prioritaires.
3. Confirme la stack réelle : PHP Laravel, PostgreSQL, Redis, Tailwind CSS, Vite et composants associés.
4. Audite le dépôt strictement en lecture seule.
5. Ne modifie, ne crée, ne supprime et ne formate aucun fichier métier.
6. Cartographie les applications, modules, tables, routes, événements, jobs, permissions, tests et intégrations.
7. Identifie les écarts entre les notes et le code.
8. Signale les contradictions sans les résoudre arbitrairement.
9. Recherche les modifications directes de soldes et les lectures inter-domaines.
10. Vérifie Grand Livre, Wallet, outbox, idempotence, queues, sécurité et observabilité.
11. Produis les livrables d’audit exigés.
12. Ne code rien avant validation du fondateur.
13. Ne crée aucune constitution, doctrine, gouvernance ou texte bloquant.
```

---

# 116. Prompt officiel de roadmap

```text
MISSION : GÉNÉRATION DE LA FEUILLE DE ROUTE D’IMPLÉMENTATION WASPLEX

À partir :
- des notes officielles ;
- du rapport d’audit validé ;
- de la cartographie du dépôt ;
- des décisions du fondateur ;

produis IMPLEMENTATION-ROADMAP-WASPLEX.md.

La roadmap doit :
1. définir le chemin critique ;
2. confirmer la première verticale économique ;
3. ordonner les chantiers ;
4. préciser les dépendances ;
5. distinguer inclus et exclu ;
6. définir migrations, API, événements, permissions, écrans et tests ;
7. définir les critères d’acceptation et de fusion ;
8. définir les branches et rapports ;
9. identifier les risques et décisions ouvertes ;
10. respecter PHP Laravel, PostgreSQL, Redis, Tailwind CSS et Vite ;
11. éviter les microservices prématurés ;
12. protéger le Grand Livre ;
13. ne créer aucune gouvernance ou couche doctrinale bloquante ;
14. ne commencer aucun codage avant validation.
```

---

# 117. Critères d’acceptation de l’audit

L’audit est accepté lorsque :

1. le commit de base est identifié ;
2. la stack est confirmée ;
3. les applications sont cartographiées ;
4. les modules sont inventoriés ;
5. les tables sont rattachées à un propriétaire ;
6. les routes sont classées ;
7. les événements sont inventoriés ;
8. les queues sont analysées ;
9. les intégrations sont cartographiées ;
10. les tests sont recensés ;
11. les écarts sont documentés ;
12. les risques sont classés ;
13. les contradictions sont signalées ;
14. aucun fichier métier n’a été modifié.

---

# 118. Critères d’acceptation de la roadmap

La roadmap est acceptée lorsque :

1. elle repose sur l’audit réel ;
2. elle couvre toutes les notes ;
3. elle définit le chemin critique ;
4. elle définit une première verticale ;
5. elle découpe en chantiers ;
6. chaque chantier a des dépendances ;
7. chaque chantier a un périmètre ;
8. chaque chantier a des tests ;
9. chaque chantier a des critères de fusion ;
10. les risques sont visibles ;
11. les questions ouvertes sont limitées ;
12. le fondateur peut valider l’ordre ;
13. la stack officielle est respectée ;
14. aucune gouvernance bloquante n’est ajoutée.

---

# 119. Critères d’acceptation du premier chantier

Le premier chantier est accepté lorsque :

- branche créée ;
- base commit enregistrée ;
- périmètre respecté ;
- tests verts ;
- documentation ;
- captures si UI ;
- audit ;
- rapport ;
- aucun changement hors scope ;
- fusion possible.

---

# 120. Erreurs à éviter

Interdit de :

- coder avant l’audit ;
- réécrire le dépôt entier ;
- imposer une autre stack ;
- créer des microservices sans besoin ;
- mélanger les modules ;
- créer un moteur universel avant les usages ;
- créer tous les écrans sans transactions ;
- ignorer le Grand Livre ;
- modifier les soldes directement ;
- inventer une gouvernance ;
- transformer la roadmap en texte abstrait.

---

# 121. Résultat attendu

Le résultat final doit permettre au fondateur de répondre immédiatement :

```text
Où en est le dépôt ?
Qu’est-ce qui existe ?
Qu’est-ce qui manque ?
Quel est le premier chantier ?
Pourquoi commence-t-on par lui ?
Quelles sont ses dépendances ?
Comment prouver qu’il est terminé ?
Quel chantier vient ensuite ?
```

---

# 122. Décision finale

La note 21 formalise la méthode suivante :

```text
notes officielles
→ dépôt réel
→ audit en lecture seule
→ cartographie
→ analyse des écarts
→ validation fondateur
→ feuille de route
→ chantiers
→ verticales complètes
→ tests
→ fusion
```

> **La feuille de route Wasplex ne doit pas être une liste théorique de modules. Elle doit être générée dans le dépôt, à partir du code réel et de toutes les notes officielles, puis transformer cette connaissance en chantiers ordonnés, codables, testables et validables par le fondateur.**
