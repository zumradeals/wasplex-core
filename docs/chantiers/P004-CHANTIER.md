# P004 — Configurations, plans et classes économiques

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `f784543` (P003 + P001-C fusionnés)
**Dépendances déclarées (roadmap) :** P001 (comptes/espaces/capacités), P003 (paiement GeniusPay)
**Spécification :** `docs/03-abonnements-et-classes-economiques-wasplex.md`
**Statut :** proposed

---

## 1. Objectif

Construire le socle des classes économiques (FREE/PREMIUM/GOLD/PLATINUM), des plans commerciaux
versionnés qui s'y rattachent, et du cycle d'abonnement utilisateur (souscription, paiement,
renouvellement, upgrade/downgrade, expiration), avec le compteur de quota publicitaire mensuel
que les modules Feed/Matching (P008/P009) consommeront plus tard via un contrat interne.

Ce chantier livre l'**infrastructure de configuration**, pas le moteur qui la consomme : aucun
module de distribution publicitaire n'existe encore. Le contrat `SubscriptionQuotaContract`
(consommer/restaurer une unité de quota) est posé et testé de façon autonome, prêt à être
appelé par P008/P009 sans modification quand ils existeront.

## 2. Périmètre inclus

- Les 12 tables de `docs/03` §22, exactement (liste fermée, même discipline que P002/§7 tables) :
  `subscription_plans`, `subscription_plan_versions`, `economic_classes`,
  `economic_class_versions`, `plan_economic_class_links`, `user_subscriptions`,
  `subscription_cycles`, `subscription_quota_counters`, `subscription_events`,
  `subscription_payments`, `subscription_refunds`, `subscription_entitlements`.
- Quatre classes économiques initiales, quotas et poids **exacts** de la décision finale §28 :
  Gratuit 120/10 %, Premium 300/20 %, Gold 600/35 %, Platine 900/35 % — administrables et
  versionnés, jamais codés en dur dans la logique métier.
- Coefficients de ciblage par classe (§13), versionnés par pays/devise/période — stockés,
  administrables, **non consommés** par un moteur de matching qui n'existe pas encore.
- Cycle mensuel civil, timezone du compte, remise à zéro idempotente, historique conservé (§8).
- `SubscriptionQuotaContract` : `consume()`, `restore()`, `currentCycle()` — utilisable dès
  maintenant par des tests directs, en attendant un appelant réel (P008/P009). Distinction stricte
  `AdQuotaConsumed` (toute publicité réellement affichée) vs rémunération (hors périmètre, geré
  par un futur `QualifiedAttention` en P009) — ce chantier ne construit pas la rémunération.
- Souscription payante via GeniusPay sandbox : choix du plan → paiement → rapprochement (webhook
  signé + revérification serveur, **même discipline que P003**) → activation → rattachement à la
  classe → initialisation du cycle.
- Upgrade (immédiat après paiement, quota restant recalculé au prorata simple selon §17),
  downgrade (au prochain cycle, §18), expiration (§19 : WP/Wallet toujours accessibles, seules
  les capacités liées au plan sont suspendues).
- API utilisateur et administration exactement selon `docs/03` §24-25.
- Écran de comparaison des plans + souscription côté utilisateur ; onglet de gestion des plans
  côté administration.
- Événements métier de §23 (Laravel events internes, pas de file externe à ce stade — même choix
  que P001-P003 pour les événements sans consommateur asynchrone réel pour l'instant).

## 3. Décision explicite : extraction du contrat de paiement

`PaymentProviderContract`/`GeniusPayAdapter` ont été construits en P003 **à l'intérieur** du
module `AdvertiserWallet`. Un abonnement utilisateur payé par GeniusPay a besoin exactement du
même contrat (créer un paiement, vérifier une signature de webhook, revérifier un statut auprès
du prestataire). Deux options :

1. dupliquer l'adaptateur dans `Subscriptions` (viole DRY, deux implémentations du même contrat
   fournisseur qui divergeraient avec le temps) ;
2. extraire le contrat + l'adaptateur vers un emplacement neutre, non détenu par un domaine
   métier (`app/Shared/Payments/`), utilisé par les deux modules.

**Décision : option 2.** CLAUDE.md §10 décrit l'intégration externe comme
`contrat interne → adaptateur → prestataire → résultat normalisé`, une préoccupation technique
transverse, pas une règle métier d'un domaine. Ce n'est pas un service global contenant des
règles métier (interdit par §6) : `GeniusPayAdapter` ne connaît ni Wallet annonceur, ni
abonnement, uniquement le contrat HTTP du prestataire. Chaque module garde son propre webhook,
sa propre table de paiement, sa propre logique de rapprochement et d'activation.

## 4. Périmètre exclu

- Toute consommation réelle du quota par un module de distribution (Feed/Matching — P008/P009
  n'existent pas).
- La rémunération liée à l'attention qualifiée (P009).
- L'application des coefficients de ciblage dans un moteur d'enchères/matching (P006/P008).
- L'intégration Fonds (`docs/03` §14 : le plan Gratuit n'est pas éligible ; ce chantier stocke le
  flag d'entitlement mais Fonds lui-même n'existe pas encore — P014).
- Le mandat de renouvellement automatique récurrent (paiement différé/carte enregistrée) —
  seul le renouvellement manuel est implémenté ; le renouvellement automatique est documenté
  comme limite (nécessiterait un contrat de prélèvement récurrent que `docs/19` ne spécifie pas).
- Toute écriture au Grand Livre pour la recette d'abonnement — `docs/03` ne le demande pas
  explicitement et `docs/06` ne documente pas de compte de recette d'abonnement ; ajouter ceci
  serait une décision produit non validée. Limite documentée.

## 5. Modèle de données (résumé)

```text
economic_classes            code (FREE/PREMIUM/GOLD/PLATINUM), nom public actif
economic_class_versions     quota, poids, coefficient, pays, devise, dates d'effet
subscription_plans          code, classe rattachée, statut (brouillon/publié/suspendu)
subscription_plan_versions  prix, durée, dates d'effet, snapshot des services inclus
plan_economic_class_links   plan_version_id → economic_class_id (rattachement explicite)
user_subscriptions          compte, plan_version actif, classe active, statut, dates
subscription_cycles         abonnement, période, date de remise à zéro
subscription_quota_counters cycle, quota alloué, consommé, restant
subscription_events         journal append-only (Selected/Activated/Renewed/Upgraded/...)
subscription_payments       montant, devise, statut, référence prestataire (GeniusPay)
subscription_refunds        paiement, montant, motif, statut
subscription_entitlements   abonnement, clé de service (fonds/carte/live/...), activé
```

## 6. Tests obligatoires (docs/03 §26, repris intégralement)

Quotas 120/300/600/900 ; préchargement sans consommation ; affichage réel avec consommation ;
gain indépendant du quota (simulé, pas de moteur réel) ; remise à zéro idempotente ; upgrade ;
downgrade ; expiration ; aucune publicité après quota (test du contrat, pas d'un moteur réel) ;
poids à 100 % ; normalisation partielle (ex. Premium+Gold = 20/55 + 35/55) ; arrondis exacts ;
historique immuable (append-only sur `subscription_events`).

## 7. Chantier suivant recommandé

P005 — Studio Annonceur, marques et financement.
