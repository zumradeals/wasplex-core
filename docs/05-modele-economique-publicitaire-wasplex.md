# WASPLEX — MODÈLE ÉCONOMIQUE PUBLICITAIRE

**Fichier cible :** `docs/04-publicite/05-modele-economique-publicitaire-wasplex.md`  
**Statut :** spécification économique, produit et technique prête au codage  
**Partage :** 50 % Wasplex / 50 % enveloppe utilisateurs  
**Répartition initiale :** 10 % / 20 % / 35 % / 35 %  
**Unité :** 1 WP = 1 FCFA

---

# 1. Objet

Ce document définit :

- ce que l’annonceur achète ;
- le prix ;
- le budget ;
- le partage interne ;
- la répartition par abonnement ;
- le calcul du gain ;
- la réservation ;
- la consommation ;
- les reliquats ;
- le reporting ;
- le rôle du super moteur.

# 2. Ce que l’annonceur achète

L’annonceur achète :

- une diffusion ciblée ;
- une audience agrégée ;
- des événements qualifiés ;
- une mesure de performance ;
- une capacité de ciblage protégée ;
- un accès payant aux classes économiques.

Il n’achète pas :

- identités ;
- téléphones ;
- fichiers ;
- profils individuels ;
- positions exactes ;
- Santé ;
- Alertes ;
- KYC.

# 3. Budget global

Exemple :

```text
Budget annonceur : 100 000 FCFA
```

L’annonceur connaît :

- service ;
- audience ;
- durée ;
- format ;
- événements estimés ;
- budget ;
- taxes ;
- résultats ;
- reliquat.

Il n’est pas obligé de connaître la marge interne complète de Wasplex.

# 4. Partage interne

Règle officielle :

```text
Budget net distribuable
├── 50 % Wasplex
└── 50 % enveloppe utilisateurs
```

Exemple :

```text
Budget net :              100 000 FCFA
Part Wasplex :             50 000 FCFA
Enveloppe utilisateurs :   50 000 FCFA
```

# 5. Confidentialité commerciale

L’annonceur ne voit pas nécessairement :

- la part interne Wasplex ;
- la part individuelle ;
- la répartition complète entre abonnements ;
- les WP détenus ;
- le détail du grand livre.

L’administration Wasplex voit tout.

# 6. Répartition générale

```text
Gratuit :  10 %
Premium :  20 %
Gold :     35 %
Platine :  35 %
Total :   100 %
```

Pour 50 000 FCFA :

```text
Gratuit :   5 000 FCFA
Premium :  10 000 FCFA
Gold :     17 500 FCFA
Platine :  17 500 FCFA
```

# 7. Classe unique

Gold uniquement :

```text
Enveloppe Gold : 50 000 FCFA
```

Gold reçoit 100 % de l’enveloppe utilisateurs de la campagne.

# 8. Plusieurs classes

Gold + Platine :

```text
35 / 35
→ 50 % / 50 %
```

Premium + Gold :

```text
Premium : 20 / 55
Gold :    35 / 55
```

Les arrondis sont exacts et tracés.

# 9. Ciblage économique payant

Cibler un abonnement payant coûte plus cher en raison :

- de l’engagement ;
- de la rareté ;
- de la valeur commerciale ;
- de la capacité économique ;
- de la probabilité d’action.

Formule conceptuelle :

```text
Prix commercial
= prix de base
× format
× durée
× précision
× territoire
× rareté
× classe ciblée
× volume
```

Les coefficients sont administrables.

# 10. Exemple Orange

```text
Annonceur : opérateur télécom
Réseau : Orange
Zone : Abidjan / Cocody
Classes : Gold + Platine
Budget : 100 000 FCFA
```

Wasplex calcule :

- taille d’audience ;
- coût ;
- événements estimés ;
- portée ;
- fréquence ;
- gain unitaire ;
- durée.

# 11. Événement facturable

Le budget n’est pas consommé au préchargement.

Événements possibles :

- vidéo complétée ;
- image visible pendant un seuil ;
- clic valide ;
- appel initié ;
- itinéraire ;
- formulaire ;
- action catalogue.

Chaque événement possède prix, preuve, règle, idempotency key, classe et gain.

# 12. Quota et facturation

Une publicité affichée :

- consomme le quota ;
- ne consomme pas nécessairement l’enveloppe ;
- ne produit pas nécessairement de gain.

Une publicité qualifiée :

- consomme l’enveloppe ;
- produit le gain ;
- reconnaît la part Wasplex ;
- écrit le grand livre.

# 13. Calcul du gain unitaire

```text
Gain unitaire
= enveloppe de classe
÷ nombre d’événements qualifiés financés
```

Exemple :

```text
Enveloppe Gold : 17 500 FCFA
Événements Gold : 100
Gain : 175 WP
```

Le gain exact est connu avant diffusion.

# 14. Nombre d’événements

Le devis détermine :

- nombre d’événements ;
- gain ;
- coût ;
- classe ;
- budget ;
- réserve.

Le moteur ne promet jamais plus que l’enveloppe disponible.

# 15. Réservation

Avant démarrage :

```text
montant réservé
classe identifiée
gain promis
preuve attendue
expiration
```

La même valeur ne peut pas être promise deux fois.

# 16. Abandon

Si l’utilisateur abandonne :

- quota consommé si affichage réel ;
- aucun gain ;
- réservation libérée ;
- enveloppe restaurée ;
- aucun débit final.

# 17. Validation

Si l’événement est valide :

```text
réservation consommée
→ enveloppe débitée
→ Wallet crédité
→ part Wasplex enregistrée
→ reporting mis à jour
```

# 18. Arrondis

Puisque 1 WP = 1 FCFA :

- gain entier ;
- reliquat conservé ;
- aucune fraction cachée ;
- aucune création de valeur ;
- aucune perte silencieuse.

Exemple :

```text
10 000 / 300 = 33 WP
300 événements = 9 900 FCFA
Reliquat = 100 FCFA
```

# 19. Reliquat de classe

Règle adoptée :

1. tenter de consommer dans la classe ;
2. redistribuer vers d’autres classes éligibles si la règle l’autorise ;
3. ne jamais réduire un gain déjà promis ;
4. créditer ou rembourser le reliquat final à l’annonceur selon le contrat.

# 20. Reliquat global

Options :

- prolongation ;
- élargissement avec accord ;
- redistribution ;
- crédit annonceur ;
- remboursement.

Aucun reliquat ne devient automatiquement un revenu caché Wasplex.

# 21. Utilisateur hors quota

L’utilisateur n’est plus éligible.

Le moteur cherche un autre membre de la même classe.

Aucune publicité commerciale ne lui est servie avant le prochain cycle.

# 22. États budget

```text
draft
quoted
awaiting_payment
paid
available
reserved
active
partially_consumed
completed
refundable
refunded
disputed
cancelled
```

# 23. Devis annonceur

Afficher :

- audience estimée ;
- critères ;
- format ;
- durée ;
- classes ;
- prix ;
- budget ;
- événements ;
- portée ;
- fréquence ;
- dates ;
- taxes ;
- reliquat ;
- remboursement.

# 24. Super moteur de valeur

```text
attention
→ preuve
→ consommation
→ partage
→ grand livre
→ Wallet
→ interface
```

Il garantit :

- atomicité ;
- idempotence ;
- compensation ;
- audit ;
- temps réel visible.

# 25. Grand livre

Écritures :

- budget reçu ;
- disponible ;
- réservé ;
- consommé ;
- part Wasplex ;
- part utilisateur ;
- WP crédités ;
- réservation libérée ;
- remboursement ;
- correction ;
- litige.

# 26. Reporting annonceur

- budget initial ;
- consommé ;
- restant ;
- événements commencés ;
- qualifiés ;
- rejets ;
- portée ;
- fréquence ;
- complétion ;
- CTA ;
- coût moyen ;
- classes ;
- dates.

# 27. Reporting administration

- revenu Wasplex ;
- enveloppes utilisateurs ;
- distribution par classe ;
- réservations ;
- consommations ;
- reliquats ;
- remboursements ;
- anomalies ;
- fraude ;
- pays ;
- devise.

# 28. Modèle de données

```text
advertising_price_catalogs
advertising_price_versions
advertising_campaign_quotes
advertising_campaign_budgets
advertising_budget_entries
advertising_user_envelopes
advertising_envelope_allocations
advertising_value_reservations
advertising_qualified_events
advertising_value_splits
advertising_campaign_refunds
advertising_campaign_reports
```

# 29. API annonceur

```text
POST   /api/advertiser/campaigns/{id}/quote
POST   /api/advertiser/campaigns/{id}/fund
POST   /api/advertiser/campaigns/{id}/activate
GET    /api/advertiser/campaigns/{id}/budget
GET    /api/advertiser/campaigns/{id}/report
POST   /api/advertiser/campaigns/{id}/refund-request
```

# 30. API administration

```text
GET    /api/admin/advertising/pricing
POST   /api/admin/advertising/pricing
PATCH  /api/admin/advertising/pricing/{id}
POST   /api/admin/advertising/pricing/{id}/publish

GET    /api/admin/advertising/campaigns
GET    /api/admin/advertising/campaigns/{id}
POST   /api/admin/advertising/campaigns/{id}/approve
POST   /api/admin/advertising/campaigns/{id}/suspend
POST   /api/admin/advertising/campaigns/{id}/refund
```

# 31. Événements métier

```text
CampaignQuoted
CampaignFunded
CampaignApproved
CampaignActivated
CampaignEnvelopeAllocated
AdValueReserved
AdValueReleased
QualifiedEventValidated
CampaignBudgetConsumed
UserRewardCredited
WasplexRevenueRecognized
CampaignCompleted
CampaignRefunded
```

# 32. Tests

- partage 50/50 ;
- enveloppe 10/20/35/35 ;
- Gold unique ;
- Premium + Gold normalisés ;
- ciblage payant ;
- devis ;
- gain unitaire ;
- réservation ;
- abandon ;
- validation ;
- arrondi ;
- reliquat ;
- remboursement ;
- double débit impossible ;
- double crédit impossible ;
- reporting agrégé ;
- aucune identité exposée.

# 33. Critères d’acceptation

1. budget global accepté ;
2. partage 50/50 ;
3. enveloppes créées ;
4. classes normalisées ;
5. ciblage payant ;
6. gain avant affichage ;
7. réservation ;
8. validation atomique ;
9. Wallet immédiat ;
10. arrondis exacts ;
11. reliquats traçables ;
12. remboursement possible ;
13. reporting agrégé ;
14. aucune identité ;
15. tests verts.

# 34. Décision finale

```text
Budget annonceur : 100 000 FCFA
Part Wasplex :      50 000 FCFA
Utilisateurs :      50 000 FCFA
```

Répartition générale :

```text
Gratuit : 10 %
Premium : 20 %
Gold :    35 %
Platine : 35 %
```

Le système calcule, réserve, valide et crédite en temps réel sans exposer la marge interne de Wasplex à l’annonceur.
