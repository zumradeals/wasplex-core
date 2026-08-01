# WASPLEX — ARCHITECTURE TECHNIQUE GÉNÉRALE

**Version :** 2.0 — stack technique explicitement définie  
**Fichier cible recommandé :** `docs/19-architecture-technique/00-architecture-technique-generale-wasplex.md`  
**Statut :** spécification d’architecture prête à être utilisée pour l’audit du dépôt et le codage  
**Nature :** document transversal d’assemblage des applications, modules, contrats, données, événements, infrastructures, interfaces et méthodes de livraison  
**Dépendances :** toutes les notes fonctionnelles Wasplex 00 à 19  
**Principe central :** construire Wasplex avec une stack PHP moderne et maîtrisée, organisée en monolithe modulaire, capable de livrer rapidement une première verticale économique complète  
**Directive produit :** l’architecture doit accélérer le développement, protéger les frontières entre modules et permettre l’évolution future ; elle ne doit créer ni constitution, ni doctrine, ni mécanisme textuel bloquant

---

# 1. Objet

Ce document définit de manière explicite :

- la stack technique officielle ;
- la forme du dépôt ;
- les applications ;
- les interfaces ;
- les modules métier ;
- les frontières de données ;
- les API ;
- les événements ;
- les transactions ;
- le Grand Livre ;
- le Wallet ;
- le Super moteur de valeur ;
- les files et workers ;
- le temps réel ;
- les intégrations externes ;
- le stockage ;
- le frontend ;
- Tailwind CSS ;
- le design system ;
- la sécurité ;
- l’observabilité ;
- les environnements ;
- le déploiement ;
- les sauvegardes ;
- les tests ;
- les règles de codage ;
- la première verticale technique.

---

# 2. Stack technique officielle Wasplex

La stack de référence retenue est :

| Composant | Technologie retenue |
|---|---|
| Backend principal | PHP avec Laravel |
| Base transactionnelle | PostgreSQL |
| Cache | Redis |
| Files et workers | Laravel Queue avec Redis |
| Planification | Laravel Scheduler |
| Temps réel | Laravel Reverb ou solution WebSocket compatible Laravel |
| Style et design system | Tailwind CSS |
| Compilation frontend | Vite |
| Templates et composants | Blade, Livewire ou Inertia/Vue selon l’audit du dépôt |
| API | REST JSON Laravel |
| Stockage de fichiers | S3 compatible |
| Développement local du stockage | MinIO ou service S3 de test compatible |
| Tests backend | Pest ou PHPUnit |
| Tests navigateur | outil end-to-end adapté au frontend retenu |
| Serveur | Linux, Nginx ou équivalent, PHP-FPM |
| Observabilité | logs structurés, métriques, traces et health checks |
| Gestion du code | Git dans un monorepo |

---

# 3. Décisions techniques déjà fixées

Les éléments suivants ne doivent plus être considérés comme facultatifs :

```text
PHP
Laravel
PostgreSQL
Redis
Tailwind CSS
Vite
architecture modulaire
Grand Livre en double entrée
outbox transactionnelle
idempotence
adaptateurs externes
observabilité
```

Le modèle chargé du codage ne doit pas remplacer librement PHP/Laravel ou PostgreSQL par une autre stack.

---

# 4. Versions des technologies

Les numéros de version exacts doivent être fixés au démarrage du dépôt après vérification des versions stables et supportées.

La règle est :

```text
version stable supportée
→ verrouillée dans le dépôt
→ documentée
→ mise à jour de manière contrôlée
```

Ne pas utiliser automatiquement une version expérimentale.

---

# 5. Rôle de PHP

PHP porte :

- les règles métier ;
- les API ;
- les contrôleurs ;
- les commandes ;
- les queries ;
- les workers ;
- les événements ;
- les intégrations ;
- les migrations ;
- les tests backend ;
- l’administration ;
- le Scheduler.

PHP reste le langage principal de Wasplex.

---

# 6. Rôle de Laravel

Laravel fournit :

- le framework applicatif ;
- le routage ;
- l’authentification ;
- la validation ;
- l’accès PostgreSQL ;
- les migrations ;
- les files ;
- les événements ;
- les commandes ;
- le Scheduler ;
- le cache Redis ;
- les notifications ;
- le temps réel ;
- les tests ;
- les politiques d’accès.

Laravel ne doit pas être utilisé comme justification pour mélanger tous les modules.

---

# 7. Rôle de PostgreSQL

PostgreSQL est la base transactionnelle principale.

Il conserve notamment :

- comptes ;
- organisations ;
- abonnements ;
- campagnes ;
- profils ;
- Grand Livre ;
- Wallets ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- partenaires ;
- Live ;
- notifications ;
- audits ;
- configurations.

PostgreSQL reste la vérité durable du système.

---

# 8. Rôle de Redis

Redis est utilisé pour :

- cache ;
- files ;
- sessions selon configuration ;
- rate limiting ;
- verrous courts ;
- événements temps réel ;
- compteurs temporaires ;
- files prioritaires.

Redis ne devient jamais la source de vérité financière.

```text
PostgreSQL
→ vérité durable

Redis
→ accélération, orchestration et traitement temporaire
```

---

# 9. Rôle de Tailwind CSS

Tailwind CSS devient la fondation visuelle officielle de Wasplex.

Il sert à construire :

- couleurs ;
- espacements ;
- grilles ;
- responsive ;
- bordures ;
- rayons ;
- ombres ;
- typographies ;
- états ;
- animations ;
- accessibilité visuelle ;
- composants mobiles ;
- tableaux desktop.

Tailwind ne remplace pas le design system Wasplex.

---

# 10. Rôle de Vite

Vite compile :

- Tailwind CSS ;
- JavaScript ;
- Vue si retenu ;
- composants frontend ;
- assets ;
- fichiers de production optimisés.

Node.js sert principalement à la construction des assets frontend.

Le backend métier reste PHP Laravel.

---

# 11. Choix frontend à confirmer après audit

La note fixe Tailwind CSS, mais le moteur d’interaction final doit être confirmé après audit du nouveau dépôt.

Options compatibles :

```text
Laravel Blade + Alpine.js + Tailwind
Laravel Blade + Livewire + Tailwind
Laravel + Inertia + Vue + Tailwind
approche hybride maîtrisée
```

Le modèle d’audit doit choisir l’option la plus cohérente avec le dépôt existant, sans introduire plusieurs frameworks concurrents inutilement.

---

# 12. Règle de choix frontend

Le choix doit privilégier :

- simplicité ;
- maintenabilité ;
- expérience mobile ;
- performance ;
- temps réel ;
- formulaires complexes ;
- composants partagés ;
- disponibilité des développeurs ;
- cohérence avec le dépôt.

---

# 13. Design system Wasplex

Le design system doit être construit au-dessus de Tailwind.

Il doit définir :

```text
couleurs
typographies
rayons
ombres
espacements
grilles
animations
icônes
composants
états
responsive
accessibilité
```

---

# 14. Composants officiels

Exemples :

```text
WButton
WInput
WSelect
WCard
WModal
WBottomSheet
WBadge
WAvatar
WToast
WDataTable
WWalletCard
WCampaignCard
WAlertCard
WLiveCard
WEmptyState
WLoadingState
```

Les développeurs ne doivent pas recréer un bouton différent dans chaque module.

---

# 15. Tokens visuels

Les tokens Wasplex doivent centraliser :

- or Wasplex ;
- noir ;
- blanc ;
- fonds ;
- succès ;
- avertissement ;
- danger ;
- information ;
- rayons ;
- ombres ;
- espacements ;
- tailles ;
- vitesses d’animation.

---

# 16. Exemple Tailwind

```html
<button
    class="rounded-xl bg-wasplex-gold px-5 py-3
           font-semibold text-wasplex-black
           shadow-md transition
           hover:-translate-y-0.5 hover:shadow-lg
           focus:outline-none focus:ring-4"
>
    Créer une campagne
</button>
```

Dans le code réel, ce style doit être encapsulé dans un composant réutilisable.

---

# 17. Doctrine responsive officielle

```text
Espace utilisateur
→ mobile-first strict
→ shell mobile conservé sur desktop

Studio Annonceur
→ mobile complet
→ desktop complet et stratégique

Partenaires et professionnels
→ mobile terrain
→ desktop pilotage
→ tablette opérationnelle

Institutions
→ mobile agent
→ desktop centre opérationnel

Administration
→ desktop complet
→ tablette supportée
→ mobile urgence et supervision
```

---

# 18. Décision d’architecture générale

La V1 utilise :

```text
monorepo
+ backend Laravel en monolithe modulaire
+ PostgreSQL principal
+ Redis
+ applications frontend spécialisées
+ événements internes
+ outbox
+ workers
+ stockage S3 compatible
+ temps réel
+ adaptateurs externes
```

---

# 19. Pourquoi un monolithe modulaire

Cette architecture permet :

- un développement plus rapide ;
- des transactions cohérentes ;
- un déploiement plus simple ;
- une compréhension globale ;
- moins de pannes réseau internes ;
- moins de duplication ;
- un audit plus facile ;
- une première verticale complète.

---

# 20. Ce que monolithe modulaire ne signifie pas

Il ne signifie pas :

- contrôleurs géants ;
- code mélangé ;
- table globale utilisée partout ;
- absence de frontières ;
- service universel ;
- dépendances circulaires ;
- accès direct aux données de tous les modules.

---

# 21. Microservices non retenus pour la V1

Wasplex ne doit pas commencer avec :

```text
un dépôt par module
une base par module
un serveur par module
des communications réseau internes partout
```

Les microservices pourront être envisagés uniquement après un besoin réel.

---

# 22. Conditions futures d’extraction

Un module pourra devenir un service indépendant si nécessaire pour :

- charge ;
- disponibilité ;
- équipe ;
- sécurité ;
- technologie spécialisée ;
- déploiement indépendant ;
- streaming ;
- média ;
- recherche ;
- analytique.

---

# 23. Monorepo

Structure recommandée :

```text
wasplex/
├── apps/
│   ├── platform
│   ├── user-app
│   ├── advertiser-studio
│   ├── professional-portal
│   └── admin-console
├── packages/
├── docs/
├── infrastructure/
├── tests/
└── README.md
```

La structure exacte doit respecter le dépôt réel.

---

# 24. Applications principales

```text
apps/
├── platform
├── user-app
├── advertiser-studio
├── professional-portal
├── admin-console
├── workers
└── scheduler
```

Les workers et le scheduler peuvent rester exécutés depuis l’application Laravel principale.

---

# 25. Application utilisateur

Elle couvre :

- Feed ;
- Fonds ;
- Wallet ;
- Alertes ;
- Santé ;
- Mon Espace ;
- Carte ;
- Live ;
- notifications ;
- support.

Le cœur reste mobile même sur écran de bureau.

---

# 26. Studio Annonceur

Il couvre :

- marques ;
- charte graphique ;
- bibliothèque ;
- Wallet annonceur ;
- campagnes ;
- audiences ;
- Live sponsorisé ;
- reporting ;
- équipe ;
- facturation.

---

# 27. Portail professionnel

Il couvre :

- partenaires ;
- prestataires Fonds ;
- institutions ;
- Santé ;
- terrain ;
- opérateurs ;
- dossiers ;
- preuves ;
- rapports.

---

# 28. Console d’administration

Elle couvre :

- supervision ;
- configurations ;
- utilisateurs ;
- organisations ;
- abonnements ;
- campagnes ;
- Wallet ;
- Grand Livre ;
- Fonds ;
- Alertes ;
- Santé ;
- Carte ;
- Live ;
- sécurité ;
- audit ;
- intégrations ;
- observabilité.

---

# 29. Domaines backend

Modules recommandés :

```text
Identity
Accounts
Organizations
Subscriptions
SmartProfile
Advertising
Matching
Feed
Wallet
Ledger
ValueEngine
Funds
Alerts
Health
Card
Partners
Live
Notifications
Messaging
Moderation
Risk
DataAccess
Reporting
Audit
Integrations
Administration
```

---

# 30. Structure Laravel par module

Exemple :

```text
app/Modules/Wallet/
├── Domain/
├── Application/
├── Infrastructure/
├── Http/
├── Database/
├── Events/
├── Jobs/
├── Policies/
└── Tests/
```

---

# 31. Couche Domain

Elle contient :

- entités ;
- objets valeur ;
- règles ;
- machines d’états ;
- événements métier ;
- erreurs métier ;
- interfaces de dépôt.

Elle ne dépend pas des contrôleurs HTTP.

---

# 32. Couche Application

Elle contient :

- commandes ;
- queries ;
- handlers ;
- cas d’usage ;
- orchestrations ;
- autorisations ;
- transactions.

---

# 33. Couche Infrastructure

Elle contient :

- implémentations PostgreSQL ;
- cache Redis ;
- files ;
- adaptateurs externes ;
- stockage ;
- clients ;
- instrumentation.

---

# 34. Couche Interfaces

Elle contient :

- contrôleurs ;
- routes ;
- validateurs ;
- ressources JSON ;
- webhooks ;
- commandes Artisan ;
- consumers.

---

# 35. Propriété des tables

Chaque module possède ses tables.

Exemples :

```text
ledger_accounts
ledger_transactions
ledger_entries

wallet_accounts
wallet_projections

advertising_campaigns
advertising_campaign_versions
advertising_budget_reservations

health_accesses
health_emergency_capsules
```

---

# 36. Préfixes ou schémas PostgreSQL

Les tables doivent être identifiables par domaine.

Exemples :

```text
identity_*
advertising_*
ledger_*
wallet_*
funds_*
alerts_*
health_*
card_*
live_*
```

L’utilisation de schémas PostgreSQL distincts peut être étudiée après audit, sans complexifier inutilement la V1.

---

# 37. Interdiction des lectures directes entre domaines

Interdit :

```text
FeedController
→ SELECT dans health_records
```

Correct :

```text
Feed
→ MatchingContract
→ EligibilityProjection
```

---

# 38. Noyau partagé minimal

Le Shared Kernel contient seulement :

- identifiants ;
- monnaies ;
- dates ;
- pagination ;
- résultats ;
- erreurs communes ;
- traces ;
- idempotence ;
- primitives d’événement ;
- primitives d’autorisation.

Il ne doit pas absorber les règles métier.

---

# 39. Contrats internes

Exemples :

```text
WalletCreditContract
LedgerPostingContract
CampaignEligibilityContract
ValueQuoteContract
NotificationContract
DataProjectionContract
PaymentProviderContract
```

---

# 40. Carte des dépendances

Le dépôt doit fournir une carte montrant :

- module source ;
- module cible ;
- contrat ;
- événement ;
- projection ;
- dépendance autorisée.

Aucune dépendance circulaire n’est admise.

---

# 41. Commandes et queries

```text
Command
→ demande une modification

Query
→ demande une lecture
```

Cette séparation est logique ; elle ne nécessite pas deux infrastructures physiques en V1.

---

# 42. Transactions PostgreSQL

Une transaction doit :

- être courte ;
- rester cohérente ;
- respecter les contraintes ;
- écrire l’outbox ;
- être idempotente ;
- éviter les appels externes longs.

---

# 43. Appels externes hors transaction longue

Éviter :

```text
BEGIN
→ appel Mobile Money
→ attente
→ COMMIT
```

Préférer :

```text
intention enregistrée
→ commit
→ job externe
→ résultat
→ nouvelle transaction
```

---

# 44. Outbox transactionnelle

Flux :

```text
état métier + événement outbox
→ commit PostgreSQL
→ worker Laravel
→ publication
→ consumer
```

L’outbox est obligatoire pour les événements critiques.

---

# 45. Inbox des consumers

Chaque consumer critique conserve :

- event_id ;
- consumer_name ;
- statut ;
- tentatives ;
- date ;
- résultat.

Cela empêche un traitement double.

---

# 46. Laravel Queue

Files possibles :

```text
critical
financial
realtime
notifications
integrations
media
analytics
default
low
```

---

# 47. Workers

Les workers sont des processus Laravel :

```bash
php artisan queue:work
```

Ils traitent :

- webhooks ;
- notifications ;
- rapprochements ;
- médias ;
- exports ;
- événements ;
- reporting ;
- reprises.

---

# 48. Supervisor de workers

En production, les workers doivent être supervisés par :

- Supervisor ;
- systemd ;
- Laravel Horizon si compatible avec le choix final ;
- plateforme de conteneurs future.

Le processus exact doit être documenté.

---

# 49. Laravel Scheduler

Le Scheduler gère :

- renouvellements ;
- quotas ;
- expirations ;
- rapprochements ;
- rapports ;
- rétention ;
- nettoyage ;
- health checks ;
- snapshots.

Toutes les tâches doivent être idempotentes.

---

# 50. Grand Livre

Le Grand Livre fonctionne dans PostgreSQL.

Principes :

- double entrée ;
- débits = crédits ;
- append-only ;
- idempotence ;
- monnaie explicite ;
- références ;
- compensation ;
- audit.

---

# 51. Tables principales du Grand Livre

```text
ledger_accounts
ledger_transactions
ledger_entries
ledger_journals
ledger_idempotency_keys
```

---

# 52. Interdiction de modifier un solde

Interdit :

```php
$wallet->balance += 500;
```

Correct :

```text
création LedgerTransaction
→ LedgerEntries
→ WalletProjectionUpdated
```

---

# 53. Wallet

Le Wallet est une projection d’usage.

Compartiments :

- disponible ;
- pending ;
- réservé ;
- Fonds ;
- retrait réservé ;
- promotion ;
- obligations séparées.

---

# 54. Cohérence Wallet

Le système doit pouvoir reconstruire une projection Wallet depuis le Grand Livre.

Toute divergence déclenche :

- alerte ;
- comparaison ;
- reconstruction ;
- audit.

---

# 55. Super moteur de valeur

Il orchestre :

```text
événement
→ règle versionnée
→ éligibilité
→ quote
→ réservation
→ preuve
→ décision
→ Grand Livre
→ Wallet
→ temps réel
```

---

# 56. Limites du Super moteur

Il ne remplace pas :

- Ledger ;
- Wallet ;
- Matching ;
- Feed ;
- Fonds ;
- Live ;
- Partenaires.

---

# 57. Registre des événements valorisables

Exemples :

```text
AD_QUALIFIED_ATTENTION
LIVE_REWARDED_BLOCK
PARTNER_CASHBACK
ALERT_RETURN_REWARD
CAMPAIGN_REFUND
FUNDS_FIXED_FEE
```

---

# 58. Réservations

Avant toute promesse de valeur :

- budget disponible ;
- règle active ;
- montant ;
- réservation ;
- expiration ;
- référence ;
- idempotence.

---

# 59. Matching

Le Matching reçoit des projections autorisées.

Réponse :

```text
campaign_id
eligible
score
reward_quote
explanation
```

Le Feed ne reçoit pas le profil complet.

---

# 60. Feed

Le Feed orchestre :

- contenus ;
- publicités ;
- Alertes ;
- Live ;
- fréquence ;
- quotas ;
- attention ;
- interactions.

Il ne crée pas directement les écritures Ledger.

---

# 61. Temps réel Laravel

Le temps réel peut utiliser Laravel Reverb ou une solution WebSocket compatible.

Usages :

- Wallet ;
- messages ;
- campagnes ;
- Live ;
- tâches ;
- incidents ;
- administration.

---

# 62. Règle du commit avant l’interface

```text
commit PostgreSQL
→ outbox
→ projection
→ WebSocket
→ animation
```

Le frontend ne doit jamais afficher un crédit définitif avant la transaction confirmée.

---

# 63. API Laravel

Types :

```text
/api/v1/me/*
/api/v1/advertiser/*
/api/v1/professional/*
/api/v1/admin/*
/internal/*
/webhooks/*
```

---

# 64. Format des erreurs

```json
{
  "code": "CAMPAIGN_BUDGET_INSUFFICIENT",
  "message": "Le solde annonceur est insuffisant.",
  "details": {},
  "trace_id": "..."
}
```

---

# 65. Idempotency-Key

Obligatoire pour :

- dépôt ;
- retrait ;
- transfert ;
- campagne financée ;
- réservation ;
- remboursement ;
- récompense ;
- opération partenaire ;
- webhook.

---

# 66. Authentification

Le Compte universel fournit :

- account_id ;
- session ;
- appareil ;
- MFA ;
- espace actif ;
- organisation active ;
- capacités ;
- pays ;
- langue.

---

# 67. Autorisation

Chaque action vérifie :

```text
compte
+ espace
+ organisation
+ capacité
+ périmètre
+ contexte
+ niveau MFA
```

---

# 68. Sessions Redis ou PostgreSQL

Le stockage des sessions doit être choisi selon les besoins du dépôt.

Redis est recommandé pour les sessions actives à grande échelle.

Les événements de sécurité durables restent dans PostgreSQL.

---

# 69. Données sensibles

Domaines séparés :

- KYC ;
- Santé ;
- Alertes ;
- Fonds ;
- sécurité ;
- finance ;
- audit.

Les échanges passent par des projections minimales.

---

# 70. Base PostgreSQL unique au lancement

Pour la V1 :

```text
une instance PostgreSQL principale
```

avec séparation logique des domaines.

Ne pas créer une base différente par module au démarrage.

---

# 71. Types monétaires

Interdit :

```text
float
```

Utiliser :

- entier ;
- decimal strict si nécessaire ;
- code devise ;
- arrondi explicite.

Pour WP :

```text
1 WP = 1 FCFA
```

Le stockage recommandé est entier.

---

# 72. Identifiants

Utiliser :

- UUID ;
- ULID ;
- identifiant équivalent sécurisé.

Les références humaines sont séparées.

---

# 73. Contraintes PostgreSQL

Exemples :

- unicité idempotency_key ;
- unicité provider_reference ;
- unicité event_id + consumer ;
- version de campagne ;
- référence Ledger ;
- QR nonce ;
- version consentement.

---

# 74. Concurrence

Techniques :

- contraintes uniques ;
- transactions ;
- verrou optimiste ;
- `SELECT FOR UPDATE` ;
- compare-and-swap ;
- version ;
- réservation atomique.

Cas :

- budget campagne ;
- quota ;
- Wallet ;
- place Live ;
- collecte Fonds ;
- QR.

---

# 75. Cache Redis

Usages :

- configurations ;
- capacités ;
- taxonomies ;
- sessions ;
- campagnes éligibles ;
- rate limiting ;
- compteurs temporaires.

Le cache ne contient pas la vérité financière.

---

# 76. Invalidation

Méthodes :

- événement ;
- version ;
- TTL ;
- publication de configuration ;
- révocation de capacité ;
- retrait de consentement.

---

# 77. Stockage S3 compatible

Catégories :

```text
public
private
sensitive
medical
audit
temporary
```

---

# 78. MinIO en développement

MinIO peut simuler un stockage S3 local pour :

- uploads ;
- médias ;
- documents ;
- tests ;
- développement.

Il ne remplace pas automatiquement le choix de production.

---

# 79. Pipeline média

```text
upload
→ antivirus
→ métadonnées
→ transcodage
→ miniature
→ modération
→ publication
```

---

# 80. CDN

Le CDN peut distribuer :

- médias publics ;
- publicités ;
- images ;
- miniatures ;
- replays publics.

Les données Santé et preuves privées ne passent pas par un CDN public.

---

# 81. Recherche

La V1 peut commencer avec PostgreSQL :

- index ;
- recherche textuelle ;
- filtres ;
- pagination.

Un moteur dédié pourra être ajouté plus tard si nécessaire.

---

# 82. Reporting analytique

Flux :

```text
événements
→ ingestion
→ agrégats
→ tableaux de bord
```

Le reporting ne modifie pas les tables métier.

---

# 83. Intégrations externes

Tout prestataire passe par :

- contrat ;
- adaptateur ;
- routeur ;
- statut normalisé ;
- webhook ;
- idempotence ;
- observabilité ;
- audit.

---

# 84. Webhooks Laravel

Pipeline :

```text
route webhook
→ signature
→ persistance
→ réponse rapide
→ job Laravel
→ normalisation
→ événement métier
```

---

# 85. Secrets

Les secrets ne sont jamais stockés dans Git.

Utiliser :

- secret manager ;
- variables d’environnement ;
- chiffrement ;
- rotation ;
- accès limité ;
- séparation des environnements.

---

# 86. Configuration

Distinguer :

```text
configuration applicative
configuration métier versionnée
secret
feature flag
variable d’environnement
```

---

# 87. Configurations métier

Exemples :

- quotas ;
- poids ;
- frais ;
- plans ;
- blocs Live ;
- seuils ;
- territoires ;
- frais Fonds.

Elles sont versionnées et administrables.

---

# 88. Feature flags

Utilisés pour :

- pays ;
- pilote ;
- organisation ;
- version app ;
- pourcentage ;
- date ;
- environnement.

Ils ne remplacent pas les règles métier.

---

# 89. Kill switches

Exemples :

- retraits ;
- dépôts ;
- récompenses ;
- Live ;
- Carte ;
- prestataire ;
- accès sensible.

Activation auditée et réversible.

---

# 90. Sécurité Laravel

Mesures :

- validation ;
- policies ;
- middleware ;
- rate limiting ;
- MFA ;
- tokens courts ;
- sessions ;
- CORS ;
- CSRF selon client ;
- protection XSS ;
- anti-replay ;
- idempotence ;
- audit.

---

# 91. Sécurité des fichiers

- MIME réel ;
- extension ;
- taille ;
- antivirus ;
- quarantaine ;
- URL signée ;
- expiration ;
- capacités ;
- journal d’accès.

---

# 92. Observabilité

Chaque service ou module produit :

```text
logs
metrics
traces
health checks
events
audit
```

---

# 93. Logs structurés

Exemple :

```text
timestamp
level
service
module
event_code
trace_id
account_id_pseudonym
organization_id
duration_ms
status
```

---

# 94. Traces

Une trace suit :

```text
requête
→ API Laravel
→ cas d’usage
→ PostgreSQL
→ outbox
→ worker
→ intégration
→ notification
```

---

# 95. Health checks

Exposer :

- liveness ;
- readiness ;
- PostgreSQL ;
- Redis ;
- stockage ;
- files ;
- intégrations ;
- version ;
- build.

---

# 96. Environnements

```text
local
test
staging
production
```

Les secrets, bases, fichiers et prestataires sont séparés.

---

# 97. Développement local

Le dépôt doit fournir :

- installation ;
- `.env.example` ;
- PostgreSQL local ;
- Redis local ;
- stockage S3 compatible local ;
- seeders ;
- simulateurs ;
- comptes de démonstration ;
- tests ;
- documentation.

---

# 98. Serveur de production

Architecture minimale possible :

```text
Linux
→ Nginx
→ PHP-FPM
→ Laravel
→ PostgreSQL
→ Redis
→ workers
→ scheduler
→ stockage S3
```

Elle pourra évoluer selon la charge.

---

# 99. Conteneurs

Docker peut être utilisé pour :

- développement ;
- CI ;
- staging ;
- déploiement.

Il n’est pas obligatoire de transformer immédiatement Wasplex en architecture Kubernetes.

---

# 100. Design frontend et Tailwind

Le frontend doit éviter :

- classes incohérentes ;
- couleurs aléatoires ;
- CSS dupliqué ;
- composants uniques par écran ;
- animations excessives.

Préférer :

```text
tokens
→ composants
→ variantes
→ compositions
→ écrans
```

---

# 101. Frontend utilisateur

Composants :

- shell mobile ;
- navigation fixe ;
- Feed vertical ;
- Wallet central ;
- bottom sheets ;
- toasts ;
- overlays ;
- faible réseau.

---

# 102. Frontend Studio Annonceur

Desktop :

- navigation latérale ;
- formulaire ;
- aperçu mobile ;
- audience ;
- budget ;
- bibliothèque ;
- tableaux.

Mobile :

- assistant étape par étape ;
- upload ;
- Wallet ;
- soumission ;
- suivi.

---

# 103. Frontend professionnel

- tableaux desktop ;
- cartes ;
- dossiers ;
- scanner mobile ;
- preuve ;
- signature ;
- mode terrain ;
- tablette.

---

# 104. Frontend admin

- tableaux denses ;
- filtres ;
- audit ;
- finance ;
- incidents ;
- comparateurs ;
- confirmations renforcées.

---

# 105. État frontend

Séparer :

- état serveur ;
- session ;
- état UI ;
- formulaires ;
- cache local ;
- hors ligne.

Ne pas recopier le Grand Livre dans le frontend.

---

# 106. Validation

Le frontend améliore l’expérience.

Le backend Laravel reste l’autorité finale.

---

# 107. Faible réseau

Prévoir :

- compression ;
- images adaptatives ;
- vidéo optimisée ;
- reprise upload ;
- cache ;
- skeletons ;
- data saver ;
- retry contrôlé ;
- messages clairs.

---

# 108. Mode hors ligne

Autorisé :

- brouillons ;
- formulaires terrain ;
- notes ;
- pièces en attente.

Interdit pour finaliser :

- paiement ;
- récompense ;
- retrait ;
- cashback ;
- accès Santé critique ;
- écriture Ledger.

---

# 109. Accessibilité

- clavier ;
- lecteur d’écran ;
- contraste ;
- focus ;
- tailles ;
- sous-titres ;
- erreurs ;
- alternatives ;
- réduction des animations ;
- tableaux accessibles.

Tailwind doit intégrer ces exigences dans les composants.

---

# 110. Internationalisation

Prévoir :

- langues ;
- pays ;
- devises ;
- fuseaux ;
- formats ;
- numéros ;
- moyens de paiement ;
- territoires.

Les textes ne doivent pas être codés en dur dans les composants.

---

# 111. Temps et fuseaux

Stockage :

```text
UTC
```

Affichage :

```text
fuseau du contexte
```

Cas :

- quotas ;
- abonnements ;
- campagnes ;
- Live ;
- Fonds ;
- rapports.

---

# 112. Migrations Laravel

Règles :

- par module ;
- petites ;
- testées ;
- contraintes ;
- index ;
- sans perte silencieuse ;
- compatibles avec le déploiement progressif.

---

# 113. Stratégie de migration

```text
expand
→ déployer code compatible
→ migrer
→ basculer
→ retirer l’ancien
```

---

# 114. CI

La CI exécute :

- formatage PHP ;
- lint ;
- analyse statique ;
- tests ;
- migrations ;
- build Vite ;
- Tailwind ;
- sécurité ;
- tests frontend ;
- tests end-to-end critiques.

---

# 115. CD

Le déploiement permet :

- staging ;
- validation ;
- production ;
- rollback ;
- feature flags ;
- migrations contrôlées ;
- audit de version.

---

# 116. Version de build

Chaque déploiement expose :

- commit ;
- build ;
- version ;
- date ;
- environnement ;
- migrations ;
- assets Vite ;
- feature flags principales.

---

# 117. Git

Recommandation :

- branche principale protégée ;
- branches de chantier ;
- petites PR ;
- tests obligatoires ;
- revue ;
- commits descriptifs ;
- aucun secret ;
- aucun historique effacé.

---

# 118. Tests unitaires

Couvrent :

- calculs ;
- objets valeur ;
- machines d’états ;
- règles ;
- arrondis ;
- transitions ;
- permissions.

---

# 119. Tests d’intégration

Couvrent :

- PostgreSQL ;
- Redis ;
- transactions ;
- outbox ;
- queues ;
- cache ;
- Ledger ;
- projections ;
- adaptateurs simulés.

---

# 120. Tests de contrat

Couvrent :

- interfaces ;
- événements ;
- API ;
- prestataires ;
- mappings ;
- projections.

---

# 121. Tests end-to-end

La première verticale obligatoire est :

```text
annonceur recharge
→ campagne
→ approbation
→ Feed
→ attention
→ Ledger
→ Wallet
→ reporting
```

---

# 122. Tests de concurrence

Cas :

- double clic ;
- double webhook ;
- deux workers ;
- deux réservations ;
- deux places Live ;
- deux débits ;
- QR rejoué ;
- retrait répété.

---

# 123. Tests de reprise

Cas :

- crash avant commit ;
- crash après commit ;
- outbox non publiée ;
- worker en échec ;
- prestataire timeout ;
- statut inconnu ;
- dead-letter ;
- replay.

---

# 124. Tests de sécurité

- mauvais espace ;
- capacité absente ;
- périmètre incorrect ;
- injection ;
- XSS ;
- CSRF ;
- rate limit ;
- fichier ;
- webhook ;
- replay ;
- secret ;
- session ;
- élévation.

---

# 125. Tests Tailwind et responsive

Matrice :

```text
Utilisateur
→ 320 / 360 / 390 px
→ shell mobile sur desktop

Studio Annonceur
→ mobile complet
→ 1280 / 1440 px desktop

Professionnel
→ mobile terrain
→ tablette
→ desktop

Administration
→ desktop
→ tablette
→ mobile urgence
```

---

# 126. Tests visuels

Prévoir :

- captures de référence ;
- composants ;
- états ;
- erreurs ;
- chargements ;
- vide ;
- mobile ;
- tablette ;
- desktop ;
- faible réseau ;
- mode sombre futur si retenu.

---

# 127. Qualité du code

Exigences :

- typage ;
- noms explicites ;
- petites classes ;
- erreurs métier ;
- contrats ;
- tests ;
- documentation ;
- observabilité ;
- aucune règle économique cachée.

---

# 128. Interdictions architecturales

Interdit :

- remplacer PHP/Laravel sans décision du fondateur ;
- remplacer PostgreSQL sans décision ;
- modifier un solde directement ;
- lire les tables Santé depuis Advertising ;
- placer les SDK fournisseurs dans le domaine ;
- créer un service géant ;
- créer des événements sans version ;
- créer des jobs non idempotents ;
- stocker des secrets dans Git ;
- utiliser Redis comme Ledger ;
- disperser du CSS non structuré ;
- ajouter une couche doctrinale bloquante.

---

# 129. Documentation du dépôt

```text
docs/
├── product/
├── modules/
├── architecture/
├── api/
├── events/
├── data/
├── operations/
├── runbooks/
├── decisions/
└── roadmap/
```

---

# 130. ADR techniques

Une ADR contient :

- contexte ;
- décision ;
- alternatives ;
- conséquences ;
- date ;
- statut.

Elle reste remplaçable et ne devient pas une constitution.

---

# 131. Sauvegardes

Sauvegarder :

- PostgreSQL ;
- Grand Livre ;
- fichiers ;
- configurations ;
- audits ;
- rapports critiques.

Redis ne remplace pas une sauvegarde PostgreSQL.

---

# 132. Tests de restauration

Tester :

- base ;
- transaction ;
- fichier ;
- audit ;
- configuration ;
- environnement isolé.

---

# 133. Ordre de reprise

```text
Identité
→ PostgreSQL
→ Grand Livre
→ Wallet
→ intégrations financières
→ moteur de valeur
→ campagnes
→ Feed
→ notifications
→ autres modules
```

---

# 134. Scalabilité

Commencer par :

- index PostgreSQL ;
- optimisation ;
- cache Redis ;
- workers ;
- files séparées ;
- CDN ;
- agrégats ;
- partitionnement ;
- réplication future.

Ne pas extraire un microservice avant d’avoir identifié le besoin.

---

# 135. Candidats à extraction future

- streaming ;
- média ;
- notifications massives ;
- recherche ;
- analytique ;
- Matching à grande échelle ;
- fraude temps réel ;
- intégrations financières.

---

# 136. Première verticale technique

```text
Compte utilisateur minimal
→ espace annonceur
→ marque
→ Wallet annonceur
→ dépôt sandbox ou simulé
→ campagne
→ audience
→ budget réservé
→ approbation admin
→ Matching
→ Feed
→ attention qualifiée
→ Grand Livre PostgreSQL
→ Wallet utilisateur
→ notification temps réel
→ reporting annonceur
→ audit
```

---

# 137. Découpage de la première verticale

## P000 — Socle Laravel

- dépôt ;
- Laravel ;
- PostgreSQL ;
- Redis ;
- Tailwind ;
- Vite ;
- stockage local S3 compatible ;
- CI ;
- logs.

## P001 — Identité minimale

- compte ;
- session ;
- espace ;
- organisation ;
- capacités ;
- MFA admin.

## P002 — Grand Livre et Wallet minimal

- comptes ;
- transactions ;
- écritures ;
- projections ;
- tests.

## P003 — Wallet annonceur

- dépôt ;
- solde disponible ;
- réservation ;
- historique.

## P004 — Campagne

- marque ;
- média ;
- devis ;
- budget ;
- soumission ;
- approbation.

## P005 — Matching et Feed

- profil minimal ;
- audience ;
- sélection ;
- livraison ;
- quota.

## P006 — Attention et valeur

- tentative ;
- preuve ;
- validation ;
- capture ;
- Wallet.

## P007 — Temps réel et reporting

- outbox ;
- Reverb/WebSocket ;
- notification ;
- reporting ;
- audit ;
- observabilité.

---

# 138. Critères d’acceptation

L’architecture est acceptée lorsque :

1. PHP Laravel est le backend principal ;
2. PostgreSQL est la base transactionnelle ;
3. Redis sert au cache et aux files ;
4. Tailwind CSS est la fondation visuelle ;
5. Vite compile les assets ;
6. le dépôt est modulaire ;
7. les applications sont séparées par expérience ;
8. chaque module possède ses données ;
9. les lectures croisées directes sont interdites ;
10. le noyau partagé reste minimal ;
11. les contrats internes existent ;
12. les événements sont versionnés ;
13. l’outbox est utilisée ;
14. les workers sont idempotents ;
15. le Grand Livre est append-only ;
16. le Wallet est une projection ;
17. les intégrations passent par des adaptateurs ;
18. le temps réel intervient après commit ;
19. les configurations sont versionnées ;
20. les environnements sont séparés ;
21. la CI compile PHP, Tailwind et Vite ;
22. les sauvegardes sont testées ;
23. la première verticale fonctionne ;
24. aucune couche doctrinale ne bloque le code.

---

# 139. Livrables obligatoires du dépôt

- README ;
- installation ;
- stack officielle ;
- architecture ;
- carte des modules ;
- carte des dépendances ;
- design system Tailwind ;
- registre des capacités ;
- registre des événements ;
- migrations ;
- seeders ;
- simulateurs ;
- tests ;
- runbooks ;
- captures ;
- rapports de déploiement.

---

# 140. Directive pour le modèle d’audit

1. lire toutes les notes ;
2. analyser le dépôt en lecture seule ;
3. confirmer PHP, Laravel, PostgreSQL, Redis, Tailwind et Vite ;
4. identifier les versions présentes ;
5. identifier Blade, Livewire, Inertia, Vue ou React déjà utilisés ;
6. choisir l’approche frontend minimale cohérente ;
7. ne pas réécrire toute la stack ;
8. cartographier les modules ;
9. cartographier les dépendances ;
10. vérifier le Grand Livre ;
11. vérifier les frontières ;
12. produire la feuille de route ;
13. ne pas coder pendant la première analyse ;
14. ne pas introduire de doctrine ou de blocage abstrait.

---

# 141. Directive pour Claude Code ou Codex

1. respecter la stack officielle ;
2. travailler chantier par chantier ;
3. lire la note du module ;
4. vérifier ses dépendances ;
5. créer migrations et contrats ;
6. écrire les tests ;
7. utiliser PostgreSQL comme vérité durable ;
8. utiliser Redis uniquement pour les fonctions adaptées ;
9. utiliser Tailwind et le design system ;
10. ne jamais modifier un solde directement ;
11. ne jamais lire librement les tables d’un autre domaine ;
12. publier via outbox ;
13. instrumenter logs, métriques et traces ;
14. fournir captures et rapport ;
15. ne pas ajouter de texte bloquant.

---

# 142. Décision finale

L’architecture technique officielle de Wasplex est :

```text
PHP Laravel
→ PostgreSQL
→ Redis
→ Tailwind CSS
→ Vite
→ monorepo
→ monolithe modulaire
→ contrats internes
→ événements versionnés
→ outbox
→ Grand Livre
→ Wallet projections
→ adaptateurs externes
→ temps réel
→ observabilité
```

> **Wasplex sera construit avec PHP Laravel et PostgreSQL, enrichi par Redis pour les files et le cache, Tailwind CSS pour une identité visuelle moderne et cohérente, Vite pour la compilation frontend, et une architecture modulaire permettant de livrer rapidement sans créer prématurément la complexité des microservices.**
