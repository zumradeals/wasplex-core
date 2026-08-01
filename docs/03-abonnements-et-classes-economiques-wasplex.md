# WASPLEX — ABONNEMENTS ET CLASSES ÉCONOMIQUES

**Fichier cible :** `docs/04-publicite/03-abonnements-et-classes-economiques-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Décision directrice :** les décisions récentes du dirigeant priment sur les anciennes notes du dépôt  
**Plans initiaux :** Gratuit, Premium, Gold, Platine  
**Équivalence :** 1 WP = 1 FCFA

---

# 1. Objet

Ce document définit les abonnements Wasplex et leur rôle dans :

- l’accès aux services ;
- la capacité mensuelle de recevoir des publicités ;
- la répartition des enveloppes publicitaires ;
- le ciblage économique des annonceurs ;
- l’accès au programme Fonds ;
- les changements de plan ;
- l’administration des offres.

Les abonnements ne constituent ni un investissement, ni une promesse de revenu, ni une garantie de voir toutes les publicités prévues par le quota.

# 2. Plans et classes économiques

Wasplex utilise quatre classes économiques initiales :

```text
FREE
PREMIUM
GOLD
PLATINUM
```

Noms publics initiaux :

- Gratuit ;
- Premium ;
- Gold ;
- Platine.

Les codes techniques restent stables même si les noms publics changent.

Un **plan** est une offre commerciale. Une **classe économique** détermine le comportement publicitaire.

Exemple :

```text
Premium mensuel
Premium annuel
→ classe économique PREMIUM
```

Une classe économique gouverne :

- le quota publicitaire ;
- le poids dans l’enveloppe utilisateurs ;
- le coefficient de ciblage direct ;
- les plafonds éventuels ;
- les droits liés à certains modules.

# 3. Quotas mensuels officiels

| Classe | Quota mensuel total |
|---|---:|
| Gratuit | 120 publicités |
| Premium | 300 publicités |
| Gold | 600 publicités |
| Platine | 900 publicités |

Le quota représente le **nombre maximal total de publicités commerciales réellement présentées à l’utilisateur pendant son cycle mensuel**.

Il ne représente pas seulement les publicités terminées ou rémunérées.

# 4. Consommation du quota

Deux événements doivent rester distincts :

```text
AdDelivered
→ consomme une unité du quota

QualifiedAttention
→ déclenche la rémunération
```

Ne consomment pas le quota :

- un préchargement technique ;
- une publicité jamais réellement visible ;
- une erreur avant affichage ;
- un défaut Wasplex confirmé.

Consomment une unité :

- une publicité réellement affichée puis balayée ;
- une publicité vue partiellement au-delà du seuil minimal ;
- une publicité terminée ;
- une nouvelle exposition autorisée à la même campagne.

Le seuil d’exposition minimale doit être configurable.

# 5. Quota et gain

Exemple Gold :

```text
Quota mensuel : 600
Publicités affichées : 420
Publicités terminées et validées : 300
Publicités interrompues : 120
Quota restant : 180
Publicités rémunérées : 300
```

Une publicité peut donc consommer le quota sans produire de WP.

# 6. Épuisement du quota

Quand le quota est atteint :

- aucune nouvelle publicité commerciale n’est injectée ;
- aucune publicité non rémunérée ne doit être cachée dans le Feed ;
- Alertes, Santé, Live, conseils et informations institutionnelles restent accessibles ;
- l’utilisateur voit la date de remise à zéro ;
- une proposition de changement de plan peut apparaître.

Affichage recommandé :

> **Quota publicitaire mensuel atteint — renouvellement dans 4 jours.**

# 7. Maximum, pas promesse

Le produit doit afficher :

> **Jusqu’à 600 publicités par mois**

et non :

> **600 publicités garanties**

La disponibilité dépend des campagnes, du ciblage, du territoire, du consentement, du budget, de la fréquence et de la sécurité.

# 8. Cycle mensuel

Règle initiale :

- cycle mensuel civil ;
- timezone du pays principal de l’utilisateur ;
- remise à zéro en début de cycle ;
- aucun report automatique du quota inutilisé ;
- historique conservé ;
- remise à zéro idempotente.

# 9. Poids économiques officiels

Quand une campagne vise toutes les classes :

| Classe | Poids dans l’enveloppe utilisateurs |
|---|---:|
| Gratuit | 10 % |
| Premium | 20 % |
| Gold | 35 % |
| Platine | 35 % |
| **Total** | **100 %** |

Ces poids représentent la part de l’enveloppe utilisateurs réservée à chaque classe, jamais un gain individuel garanti.

# 10. Ciblage d’une seule classe

Exemple Gold uniquement :

```text
Enveloppe utilisateurs : 50 000 FCFA
Part Gold :              50 000 FCFA
```

Les poids des autres classes ne s’appliquent pas.

# 11. Ciblage de plusieurs classes

Les poids sont normalisés entre les classes choisies.

Exemple Premium + Gold :

```text
Premium : 20 / 55
Gold :    35 / 55
```

Le moteur doit gérer les arrondis sans perte ni création de valeur.

# 12. Ciblage direct d’un abonnement

Le niveau payant constitue un signal d’engagement utilisateur.

L’annonceur peut cibler :

- tous les utilisateurs ;
- les abonnements payants ;
- Premium ;
- Gold ;
- Platine ;
- une combinaison de classes.

Ce ciblage coûte plus cher.

L’annonceur achète une audience agrégée et un signal d’engagement. Il n’achète jamais l’identité individuelle des membres.

# 13. Coefficients

Chaque classe possède un coefficient administrable, versionné par pays, devise, période et marché.

Exemple illustratif :

```text
Audience générale : 1,00
Premium :           1,15
Gold :              1,35
Platine :           1,60
```

Ces valeurs ne doivent jamais être codées en dur.

# 14. Services liés aux plans

Chaque plan peut définir :

- quota publicitaire ;
- accès Fonds ;
- plafonds Fonds ;
- personnalisation ;
- avantages partenaires ;
- support ;
- Carte Wasplex ;
- Live ;
- statistiques ;
- services futurs.

Le plan Gratuit n’est pas éligible au programme Fonds.

# 15. Souscription

```text
Choix du plan
→ récapitulatif
→ paiement
→ rapprochement
→ activation
→ rattachement à la classe
→ initialisation du cycle
→ mise à jour de Mon Espace
```

# 16. Renouvellement

Modes :

- manuel ;
- automatique avec mandat valide ;
- rappel avant expiration ;
- délai de grâce configurable.

# 17. Surclassement

Règle recommandée :

- application immédiate après paiement confirmé ;
- gains passés inchangés ;
- quota déjà consommé conservé ;
- nouveau restant = nouveau quota − quota déjà consommé ;
- nouvelles règles uniquement pour les nouveaux matchings.

Exemple :

```text
Premium : 300
Déjà consommé : 180
Passage Gold : 600
Reste : 420
```

# 18. Déclassement

Règle recommandée :

- application au prochain cycle ;
- aucun retrait rétroactif ;
- aucun blocage des WP acquis ;
- aucun blocage artificiel du Wallet.

# 19. Expiration

À l’expiration :

- WP et historique restent acquis ;
- Wallet reste accessible ;
- l’utilisateur bascule vers la classe prévue ;
- seules les capacités dépendantes du plan sont suspendues ;
- Fonds peut être suspendu selon ses propres règles.

# 20. Écran de comparaison

Chaque carte affiche :

- nom ;
- prix ;
- durée ;
- quota ;
- classe ;
- services ;
- accès Fonds ;
- renouvellement ;
- absence de revenu garanti ;
- bouton de souscription.

# 21. Administration

L’administration doit permettre :

- création et versionnement des plans ;
- rattachement aux classes ;
- configuration des quotas ;
- poids ;
- coefficients ;
- accès Fonds ;
- dates d’effet ;
- simulation d’impact ;
- suspension ;
- consultation de l’historique.

# 22. Modèle de données

```text
subscription_plans
subscription_plan_versions
economic_classes
economic_class_versions
plan_economic_class_links
user_subscriptions
subscription_cycles
subscription_quota_counters
subscription_events
subscription_payments
subscription_refunds
subscription_entitlements
```

# 23. Événements métier

```text
SubscriptionSelected
SubscriptionPaymentReceived
SubscriptionActivated
SubscriptionRenewed
SubscriptionUpgraded
SubscriptionDowngradeScheduled
SubscriptionExpired
SubscriptionSuspended
SubscriptionRefunded
AdQuotaConsumed
AdQuotaRestored
AdQuotaReset
```

# 24. API utilisateur

```text
GET    /api/subscriptions/plans
GET    /api/subscriptions/current
POST   /api/subscriptions
POST   /api/subscriptions/{id}/renew
POST   /api/subscriptions/{id}/upgrade
POST   /api/subscriptions/{id}/cancel
GET    /api/subscriptions/quota
GET    /api/subscriptions/history
```

# 25. API administration

```text
GET    /api/admin/subscriptions/plans
POST   /api/admin/subscriptions/plans
PATCH  /api/admin/subscriptions/plans/{id}
POST   /api/admin/subscriptions/plans/{id}/publish
POST   /api/admin/subscriptions/plans/{id}/suspend

GET    /api/admin/economic-classes
PATCH  /api/admin/economic-classes/{id}
POST   /api/admin/economic-classes/validate-weights
```

# 26. Tests obligatoires

- quotas 120/300/600/900 ;
- préchargement sans consommation ;
- affichage réel avec consommation ;
- gain indépendant du quota ;
- remise à zéro ;
- upgrade ;
- downgrade ;
- expiration ;
- aucune publicité après quota ;
- poids à 100 % ;
- normalisation partielle ;
- arrondis exacts ;
- historique immuable.

# 27. Critères d’acceptation

1. quatre classes initiales existent ;
2. les quotas sont configurés ;
3. les poids sont configurés ;
4. le quota compte toutes les publicités réellement reçues ;
5. le gain dépend d’un événement qualifié séparé ;
6. aucune publicité commerciale après épuisement ;
7. le ciblage de classe est facturé ;
8. les noms ne sont pas codés comme règles ;
9. upgrade et downgrade sont traçables ;
10. aucun revenu n’est garanti ;
11. le Wallet reste accessible après expiration ;
12. les tests passent.

# 28. Décision finale

```text
Gratuit  : quota 120 — poids 10 %
Premium  : quota 300 — poids 20 %
Gold     : quota 600 — poids 35 %
Platine  : quota 900 — poids 35 %
```

Toutes les valeurs sont administrables et versionnées.
