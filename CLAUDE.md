# CLAUDE.md — WASPLEX CORE

## 1. Mission générale

Tu travailles sur **Wasplex**, un écosystème numérique réunissant :

- utilisateurs ;
- annonceurs ;
- partenaires ;
- professionnels ;
- institutions ;
- opérateurs financiers ;
- acteurs Santé ;
- administration Wasplex.

Ta mission est de construire Wasplex conformément aux spécifications présentes dans `docs/`, en respectant les décisions du fondateur, l’architecture modulaire et les règles financières.

---

## 2. Autorité des sources

Respecte cet ordre de priorité :

1. décision explicite du fondateur dans la mission en cours ;
2. spécification officielle du module dans `docs/` ;
3. `docs/MASTER-WASPLEX.md` ;
4. architecture technique officielle ;
5. roadmap ou chantier validé ;
6. état réel du dépôt ;
7. ancienne documentation ou ancien code.

Ne remplace jamais silencieusement une décision produit par une préférence technique.

En cas de contradiction importante :

- arrête la partie concernée ;
- identifie précisément la contradiction ;
- indique les fichiers et sections concernés ;
- propose les options avec leurs impacts ;
- attends la décision du fondateur.

---

## 3. Documents à lire

Avant toute mission importante :

1. lire `docs/MASTER-WASPLEX.md` ;
2. lire la note du module concerné ;
3. lire les notes de ses dépendances directes ;
4. lire l’architecture technique ;
5. lire le chantier validé dans la roadmap ;
6. inspecter le code existant avant de modifier.

Ne charge pas inutilement toutes les notes pour une petite correction locale.

---

## 4. Stack technique officielle

La stack Wasplex est :

- **PHP** ;
- **Laravel** ;
- **PostgreSQL** ;
- **Redis** ;
- **Tailwind CSS** ;
- **Vite** ;
- stockage **S3 compatible** ;
- temps réel Laravel, notamment Reverb ou une solution compatible ;
- Linux avec Nginx ou équivalent et PHP-FPM.

Ne remplace aucune de ces technologies sans décision explicite du fondateur.

Le choix entre Blade, Livewire, Inertia, Vue ou une approche hybride doit respecter l’état réel du dépôt et rester cohérent. N’introduis pas plusieurs frameworks frontend concurrents sans nécessité validée.

---

## 5. Architecture générale

Wasplex commence comme :

```text
monorepo
→ backend Laravel en monolithe modulaire
→ PostgreSQL principal
→ Redis
→ workers Laravel
→ scheduler Laravel
→ stockage objet
→ temps réel
→ adaptateurs externes
```

N’introduis pas de microservices prématurés.

Chaque module doit posséder :

- ses règles ;
- ses cas d’usage ;
- ses tables ;
- ses migrations ;
- ses événements ;
- ses tests ;
- ses interfaces.

---

## 6. Frontières entre modules

Chaque module est propriétaire de ses données.

Interdictions :

- lire librement les tables internes d’un autre domaine ;
- écrire dans les tables d’un autre module ;
- créer un service global contenant toutes les règles ;
- placer la logique métier dans les contrôleurs ;
- créer des dépendances circulaires.

Utiliser :

- contrats internes ;
- commandes ;
- queries ;
- événements ;
- projections minimales ;
- API internes.

Exemple correct :

```text
Feed
→ MatchingContract
→ résultat d’éligibilité
```

Exemple interdit :

```text
FeedController
→ lecture directe des tables Santé
```

---

## 7. Grand Livre et Wallet

Le **Grand Livre** est la source de vérité financière.

Il doit être :

- en double entrée ;
- équilibré ;
- append-only ;
- idempotent ;
- auditable ;
- explicite sur la devise ;
- corrigé uniquement par compensation.

Le **Wallet** est une projection issue du Grand Livre.

Interdiction absolue :

```php
$wallet->balance += $amount;
```

Toute valeur doit suivre :

```text
transaction Grand Livre
→ écritures
→ commit
→ projection Wallet
→ événement
→ notification
```

Aucune interface, commande, administrateur ou fondateur ne modifie directement un solde.

---

## 8. Règles monétaires

- `1 WP = 1 FCFA`.
- Stocker les WP comme entiers.
- Ne jamais utiliser `float` pour les montants.
- Utiliser un entier en plus petite unité ou un type décimal strict.
- Toute opération financière possède une devise.
- Toute commande financière possède une clé d’idempotence.
- Toute correction utilise une transaction compensatoire.
- Toute réservation possède un état, une référence et une expiration.
- Aucun gain n’est confirmé avant la preuve et le commit du Grand Livre.

---

## 9. Outbox, files et workers

Pour toute opération critique :

```text
état métier + événement outbox
→ commit PostgreSQL
→ worker
→ publication
→ consumer idempotent
```

Les consumers critiques doivent utiliser une inbox ou un registre équivalent.

Les jobs doivent être :

- idempotents ;
- observables ;
- limités en tentatives ;
- configurés avec timeout ;
- associés à une file adaptée ;
- envoyés en dead-letter après échecs répétés.

Ne garde pas une transaction PostgreSQL ouverte pendant un appel externe long.

---

## 10. Intégrations externes

Aucun module métier ne doit utiliser directement un SDK fournisseur.

Utiliser :

```text
contrat interne
→ adaptateur
→ prestataire
→ résultat normalisé
→ événement métier
```

Pour chaque intégration :

- vérifier les signatures ;
- utiliser l’idempotence ;
- normaliser les statuts ;
- journaliser les références ;
- prévoir retries et circuit breaker ;
- traiter les webhooks de manière asynchrone ;
- prévoir rapprochement et statut inconnu ;
- protéger les secrets.

Un prestataire externe confirme un fait externe. Wasplex conserve la décision métier et la vérité interne.

---

## 11. Données, permissions et consentements

Chaque action sensible vérifie :

```text
compte
+ espace actif
+ organisation
+ capacité
+ périmètre
+ contexte
+ niveau MFA
```

Les domaines sensibles sont séparés :

- KYC ;
- Wallet et finance ;
- Fonds ;
- Alertes ;
- Santé ;
- sécurité ;
- audit.

Interdictions :

- utiliser Santé, Alertes, Fonds ou KYC pour le ciblage publicitaire ;
- transmettre les identités des utilisateurs aux annonceurs ;
- donner un accès global implicite à un rôle administrateur ;
- exposer plus de données que nécessaire ;
- contourner les consentements techniques.

Utiliser des projections minimales entre modules.

---

## 12. Alertes et Santé

Alertes et Santé partagent une expérience utilisateur, mais conservent :

- schémas séparés ;
- permissions séparées ;
- journaux séparés ;
- règles d’accès séparées ;
- durées séparées.

Les Alertes vitales ont priorité sur la diffusion commerciale.

Une alerte ne rapporte pas de WP et ne consomme pas le quota publicitaire.

L’accès Santé d’urgence doit être :

- limité à la capsule minimale ;
- temporaire ;
- justifié ;
- protégé par MFA lorsque requis ;
- audité ;
- notifié.

---

## 13. Publicité, Matching et Feed

L’annonceur achète une capacité de ciblage protégée, pas les identités.

Le Matching utilise uniquement des projections autorisées.

Le Feed ne reçoit pas le profil intelligent complet.

Le parcours de valeur publicitaire est :

```text
campagne financée
→ utilisateur éligible
→ publicité réellement livrée
→ attention qualifiée
→ réservation capturée
→ Grand Livre
→ Wallet
→ reporting
```

Une publicité abandonnée après exposition réelle peut consommer le quota sans générer de gain.

---

## 14. Design et frontend

Tailwind CSS est la fondation visuelle officielle.

Utiliser le design system Wasplex :

- couleurs ;
- typographies ;
- rayons ;
- ombres ;
- espacements ;
- composants ;
- états ;
- responsive ;
- accessibilité.

Réutiliser des composants officiels. Ne pas disperser du CSS spécifique sans justification.

Doctrine responsive :

```text
Utilisateur
→ mobile-first strict
→ shell mobile conservé sur desktop

Studio Annonceur
→ mobile complet
→ desktop complet

Professionnels et institutions
→ mobile terrain
→ tablette
→ desktop de pilotage

Administration
→ desktop complet
→ mobile limité aux urgences
```

Le frontend améliore l’expérience, mais le backend Laravel reste l’autorité finale.

---

## 15. Sécurité

Toujours vérifier :

- validation des entrées ;
- autorisation ;
- rate limiting ;
- sessions ;
- MFA ;
- CORS ;
- CSRF selon le client ;
- XSS ;
- anti-replay ;
- idempotence ;
- sécurité des fichiers ;
- masquage des secrets ;
- audit.

Ne place jamais dans Git :

- secret ;
- clé API ;
- mot de passe ;
- certificat privé ;
- token ;
- fichier `.env` réel.

---

## 16. Observabilité

Tout chantier critique doit fournir selon le besoin :

- logs structurés ;
- métriques ;
- traces ;
- health checks ;
- alertes ;
- gestion d’erreur ;
- trace_id.

Les logs ne doivent pas contenir :

- mots de passe ;
- OTP ;
- secrets ;
- données médicales complètes ;
- pièces sensibles ;
- données financières inutiles.

---

## 17. Audit initial d’une mission

Avant toute modification, affiche et enregistre :

```bash
git status
git branch --show-current
git rev-parse HEAD
```

Puis :

- identifie le module ;
- lis ses spécifications ;
- inspecte les fichiers concernés ;
- vérifie les tests existants ;
- vérifie les dépendances ;
- annonce le périmètre exact.

Pour une mission explicitement déclarée comme audit :

- travaille strictement en lecture seule ;
- ne crée aucun fichier métier ;
- ne lance aucune migration destructive ;
- n’installe aucune dépendance ;
- ne formate aucun fichier ;
- ne commence pas le codage.

---

## 18. Méthode de travail

Travaille par chantier validé.

Pour chaque chantier :

1. confirmer la branche et le commit de base ;
2. résumer l’objectif ;
3. lister le périmètre inclus ;
4. lister le périmètre exclu ;
5. identifier les migrations ;
6. identifier les API ;
7. identifier les événements ;
8. identifier les permissions ;
9. coder par étapes cohérentes ;
10. écrire ou mettre à jour les tests ;
11. exécuter les tests ;
12. vérifier le diff ;
13. vérifier `git status` ;
14. produire un rapport de fin.

N’élargis pas silencieusement le scope.

---

## 19. Git

Ne jamais :

- pousser sans instruction explicite ;
- merger sans instruction explicite ;
- supprimer une branche sans instruction explicite ;
- réécrire l’historique ;
- utiliser `git reset --hard` sans autorisation ;
- utiliser un push forcé ;
- committer un secret ;
- modifier `main` directement sans instruction.

Préférer :

- une branche par chantier ;
- un worktree par chantier parallèle ;
- de petits commits cohérents ;
- des messages descriptifs ;
- un arbre de travail propre.

---

## 20. Tests

Selon le chantier, couvrir :

- tests unitaires ;
- tests d’intégration ;
- tests de contrat ;
- tests end-to-end ;
- tests de concurrence ;
- tests de reprise ;
- tests de sécurité ;
- tests responsive ;
- tests visuels ;
- tests de performance.

Pour les fonctions financières, tester obligatoirement :

- double clic ;
- double webhook ;
- deux workers ;
- retry ;
- idempotence ;
- concurrence ;
- compensation ;
- arrondi ;
- devise ;
- reprise après crash.

---

## 21. Critères de fin

Ne déclare pas un chantier terminé sans :

- code conforme ;
- migrations ;
- permissions ;
- événements ;
- tests verts ;
- documentation ;
- audit ;
- gestion d’erreur ;
- observabilité adaptée ;
- captures si interface ;
- rapport de chantier ;
- `git status` vérifié.

Indique clairement les limites restantes.

---

## 22. Rapport de chantier

Le rapport final doit contenir :

- titre et identifiant du chantier ;
- branche ;
- commit de base ;
- objectif ;
- fichiers modifiés ;
- migrations ;
- API ;
- événements ;
- permissions ;
- tests exécutés ;
- résultats ;
- captures ;
- limites ;
- risques ;
- décisions ouvertes ;
- commit final proposé ;
- chantier suivant recommandé.

---

## 23. Autorité du fondateur

Le fondateur conserve la décision finale sur :

- modèle économique ;
- navigation ;
- stack ;
- classes ;
- quotas ;
- frais ;
- Grand Livre ;
- accès sensibles ;
- Santé ;
- priorité Alertes ;
- roadmap ;
- périmètre ;
- ordre des chantiers ;
- fusion ;
- déploiement.

Une préférence technique ne remplace pas cette autorité.

---

## 24. Intervention exceptionnelle

Le fondateur peut demander une intervention exceptionnelle.

Elle doit rester :

- nominative ;
- motivée ;
- limitée ;
- auditée ;
- réversible lorsque possible.

Elle ne permet jamais :

- d’effacer une écriture ;
- de falsifier un audit ;
- de modifier directement un solde ;
- de supprimer une preuve ;
- de rendre une action invisible.

---

## 25. Interdictions finales

Ne jamais :

- ajouter une constitution ou une gouvernance doctrinale ;
- créer un texte bloquant le développement ;
- remplacer la stack sans décision ;
- créer des microservices prématurés ;
- modifier un solde directement ;
- contourner le Grand Livre ;
- créditer avant preuve ;
- notifier un gain avant commit ;
- lire librement les tables d’un autre domaine ;
- utiliser les données sensibles pour la publicité ;
- exposer les identités aux annonceurs ;
- accepter un webhook non vérifié ;
- stocker un secret dans Git ;
- élargir le périmètre sans le signaler ;
- déclarer un travail terminé sans tests.

---

## 26. Première verticale de référence

La première verticale économique complète de Wasplex est :

```text
Compte utilisateur minimal
→ activation de l’espace annonceur
→ création d’une marque
→ recharge du Wallet annonceur
→ création d’une campagne
→ définition de l’audience
→ réservation du budget
→ revue administrative
→ approbation
→ Matching
→ diffusion dans le Feed
→ attention qualifiée
→ Grand Livre
→ Wallet utilisateur
→ notification temps réel
→ reporting annonceur
→ audit
```

Cette verticale doit guider l’ordre initial d’implémentation, sous réserve de la roadmap générée après audit du dépôt.

---

## 27. Règle de conclusion

À la fin de chaque mission, indique uniquement :

- ce qui a été vérifié ;
- ce qui a été modifié ;
- les tests exécutés ;
- les résultats ;
- les limites ;
- l’état Git ;
- la prochaine étape logique.

Ne masque jamais un échec, une incertitude ou un élément non vérifié.
