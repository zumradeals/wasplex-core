# P018-C — Live sponsorisé : ciblage, devis et réservation du budget

**Statut :** implémentation préparée après P018-B  
**Base :** Live standard LiveKit/WebRTC + interactions Reverb  
**Impact économique :** réservation du budget annonceur au Grand Livre, sans crédit spectateur dans ce lot

## 1. Objectif

P018-C ouvre la deuxième phase du module Live : un annonceur peut transformer un Live standard encore modifiable en **Live sponsorisé financé**.

La chaîne imposée est :

```text
Live brouillon
→ sponsorisation
→ segment protégé
→ estimation agrégée
→ devis 50/50
→ réservation du budget annonceur
→ programmation officielle
```

Aucun WP spectateur n'est distribué par P018-C. Les places rémunérées, blocs d'attention, validations et captures économiques appartiennent aux lots suivants.

## 2. Règles produit

- un Live standard reste utilisable sans sponsorisation et sans mouvement financier ;
- un Live sponsorisé ne peut pas être officiellement programmé ni démarré tant que son budget n'est pas réservé ;
- activer la sponsorisation sur un Live déjà programmé le remet en brouillon tout en conservant la date souhaitée ;
- l'annonceur peut annuler la sponsorisation tant que le Live n'a pas démarré ;
- si un budget avait été réservé, l'annulation le libère vers le Wallet annonceur via une écriture compensatoire ;
- le navigateur ne crédite, ne réserve et ne modifie jamais un solde.

## 3. Ciblage et protection de l'audience

P018-C branche le Live sur le contrat interne `EconomicClassCatalogContract` déjà utilisé pour les estimations agrégées.

Le segment persiste :

```text
territory.country_code
economic_classes[]
```

L'interface Studio reste volontairement simple : le pays est exposé et les classes actives sont sélectionnées automatiquement. L'API conserve la capacité de recevoir une liste de classes afin de préparer les lots futurs sans exposer une liste nominative.

L'estimation renvoie uniquement une fourchette arrondie :

```text
estimated_reach_min
estimated_reach_max
too_small
```

Les décomptes réels par classe ne quittent pas le module propriétaire. Un devis sponsorisé est refusé si le segment est inférieur au seuil `live.minimum_segment_size` (20 par défaut).

## 4. Devis économique

Le devis exige :

- une durée Live planifiée d'au moins 5 minutes ;
- un budget entier positif et divisible par 2 ;
- un bloc d'attention configuré à 2, 5 ou 10 minutes ;
- une audience suffisamment grande.

Répartition immuable de ce lot :

```text
budget sponsorisé
├── 50 % part Wasplex
└── 50 % enveloppe spectateurs
```

Le devis ne détermine pas encore le nombre de places ni le gain individuel. Ces valeurs seront calculées lorsque P018-D/P018-E introduiront l'admission rémunérée et les blocs d'attention.

## 5. Données

### `lives.type`

```text
standard
sponsored
```

### `live_reward_campaigns`

- Live unique ;
- organisation annonceur ;
- créateur ;
- statut ;
- segment version courante.

États P018-C :

```text
draft
quoted
funds_reserved
cancelled
```

### `live_reward_quotes`

- budget total ;
- part Wasplex ;
- enveloppe spectateurs ;
- estimation agrégée ;
- durée du Live ;
- durée du bloc ;
- statut du devis ;
- date du devis.

### `live_reward_budget_reservations`

- campagne Live ;
- devis ;
- organisation ;
- montant ;
- état réservé/libéré ;
- clé d'idempotence ;
- transaction Grand Livre ;
- timestamps de réservation/libération.

## 6. Frontière Wallet / Grand Livre

Live ne lit ni n'écrit directement les tables financières.

Un nouveau contrat interne est fourni par `AdvertiserWallet` :

```text
AdvertiserLiveBudgetReservationContract
```

Il expose uniquement :

```text
reserve(...)
release(...)
```

La réservation réelle suit :

```text
advertiser.budget.available
→ advertiser.live.budget.reserved
```

Les transactions sont append-only et idempotentes :

```text
LIVE_BUDGET_RESERVED
LIVE_BUDGET_RELEASED
```

La capture vers le spectateur et la reconnaissance de la part Wasplex ne sont pas encore réalisées dans P018-C.

## 7. API Studio annonceur

Sous `/api/advertiser/lives/{live}` :

```text
PATCH /sponsorship
POST  /segment-estimate
POST  /quote
POST  /fund
GET   /budget
POST  /sponsorship/cancel
```

Les routes gardent le contexte organisation annonceur actif et les capacités annonceur existantes.

Les routes déjà existantes `/schedule` et `/start` appliquent désormais le garde-fou de financement pour un Live sponsorisé.

## 8. Interface Studio

`AdvertiserLivePanel.vue` distingue désormais :

- Live standard ;
- Live sponsorisé.

`LiveSponsorshipPanel.vue` permet :

- d'activer la sponsorisation ;
- de choisir le pays ;
- d'estimer l'audience ;
- de choisir 2/5/10 minutes par bloc ;
- de saisir le budget ;
- de voir le partage 50/50 ;
- de réserver le budget ;
- de programmer officiellement le Live après financement ;
- d'annuler la sponsorisation avant démarrage.

Le Studio précise que le gain individuel et les places rémunérées restent calculés côté serveur dans les lots suivants.

## 9. Audit

Événements append-only ajoutés selon l'action :

```text
LiveSponsorshipConfigured
LiveRewardCampaignQuoted
LiveRewardCampaignFunded
LiveRewardBudgetReleased
LiveSponsorshipCancelled
```

## 10. Dette de déploiement corrigée

Le dépôt versionne désormais `infra/deploy-production.sh`.

Le script :

- utilise `umask 0022` pendant Git, Composer et NPM ;
- construit les assets avant la maintenance ;
- migre en maintenance ;
- garantit la lecture du code par `www-data` ;
- garde `.env` en `640 root:www-data` ;
- sépare les droits runtime de `storage` et `bootstrap/cache` ;
- régénère les caches Laravel ;
- redémarre les workers et Reverb lorsqu'il est installé ;
- remet automatiquement l'application en ligne en cas d'échec ;
- effectue un health check HTTP final.

Cette normalisation empêche la régression rencontrée en production où des fichiers Git en `root:root 660`, notamment `routes/channels.php`, étaient illisibles par PHP-FPM.

## 11. Tests automatisés

P018-C couvre notamment :

- blocage de programmation/démarrage sans budget réservé ;
- estimation agrégée ;
- partage exact 50/50 ;
- refus d'un budget impair ;
- réservation réelle au Grand Livre ;
- idempotence du financement ;
- solde annonceur insuffisant ;
- annulation avec libération du budget ;
- isolation entre organisations annonceur.

## 12. Hors périmètre — lot suivant

P018-C ne crée pas encore :

- places rémunérées ;
- capacité et liste d'attente ;
- réservation progressive bloc par bloc ;
- heartbeats d'attention ;
- validation d'un bloc ;
- crédit Wallet spectateur ;
- reporting économique final ;
- sondages/quiz/CTA rémunérés ;
- replay.

La prochaine étape est **P018-D — places rémunérées, capacité et liste d'attente**.
