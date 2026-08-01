# WASPLEX — SUPER MOTEUR UNIFIÉ DE VALEUR EN TEMPS RÉEL

**Fichier cible recommandé :** `docs/06-value-engine/00-super-moteur-unifie-valeur-temps-reel-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique prête au codage  
**Dépendances :** Feed, Matching publicitaire, Abonnements, Wallet & Grand Livre, Fonds, Alertes, Partenaires et future Carte Wasplex  
**Référence :** 1 WP = 1 FCFA  
**Principe central :** chaque événement admissible peut produire une valeur calculée, réservée, prouvée, comptabilisée et affichée immédiatement, sans double débit ni double crédit.

---

# 1. OBJET

Le Super moteur unifié de valeur est le cœur d’orchestration économique de Wasplex.

Il relie :

```text
Événement métier
→ règle versionnée
→ éligibilité
→ calcul
→ réservation éventuelle
→ preuve
→ décision
→ transaction comptable
→ Wallet
→ retour visuel temps réel
→ audit
```

Il intervient notamment pour :

- la rémunération publicitaire ;
- les abonnements ;
- les dépôts, retraits et transferts ;
- les contributions Fonds ;
- les frais fixes Fonds ;
- la visibilité renforcée Alertes ;
- les récompenses de restitution ;
- les opérations partenaires ;
- la future Carte Wasplex ;
- les remboursements ;
- les corrections financières ;
- les futurs flux Live et Santé.

---

# 2. EXPÉRIENCE CIBLE

## 2.1. Pour l’utilisateur

```text
Je regarde une publicité
→ la barre progresse réellement
→ je termine l’action demandée
→ la preuve est validée
→ le Wallet s’anime
→ les WP apparaissent immédiatement
→ l’historique explique le gain
```

## 2.2. Pour l’annonceur

```text
Je finance une campagne
→ Wasplex réserve mon budget
→ seuls les événements valides consomment la valeur
→ je vois des résultats agrégés
→ aucune identité utilisateur ne m’est exposée
```

## 2.3. Pour l’administration

```text
Je configure une règle
→ je simule son effet
→ je publie une version
→ le moteur l’applique
→ chaque décision reste explicable et auditable
```

---

# 3. CE QUE LE MOTEUR EST — ET N’EST PAS

Le moteur est une couche d’orchestration commune.

Il n’est pas :

- le Wallet ;
- le grand livre ;
- le Feed ;
- le moteur de matching ;
- le module Fonds ;
- le module Alertes ;
- une intelligence artificielle libre de choisir des montants ;
- un service monolithique contenant toutes les règles de Wasplex.

Les modules métier demandent une opération. Le moteur résout la règle et orchestre. Le grand livre comptabilise. Le Wallet affiche.

---

# 4. ARCHITECTURE CONCEPTUELLE

```text
Modules producteurs d’événements
├── Feed
├── Abonnements
├── Wallet
├── Fonds
├── Alertes
├── Partenaires
├── Carte
└── Live futur

Super moteur de valeur
├── registre des événements valorisables
├── résolution des règles
├── contrôle d’éligibilité
├── calcul économique
├── réservation
├── validation de preuve
├── orchestration comptable
├── idempotence
├── compensation
└── projection temps réel

Grand livre
→ source de vérité financière

Wallet
→ projection utilisateur

Outbox / Event Bus
→ diffusion fiable des résultats
```

Aucun module ne doit écrire directement dans `user.available.wp` ou dans un champ de solde.

---

# 5. VOCABULAIRE TECHNIQUE

## Événement métier

Fait produit par un module :

```text
QualifiedAttentionValidated
SubscriptionSelected
FondsContributionRequested
AlertRewardConfirmed
PartnerOperationSettled
```

## Événement valorisable

Événement explicitement inscrit dans le registre comme pouvant produire un effet financier.

## Règle de valeur

Configuration versionnée décrivant :

- conditions ;
- formule ;
- comptes ;
- frais ;
- plafonds ;
- dates d’effet ;
- bénéficiaires ;
- politique d’arrondi ;
- politique de compensation.

## Tentative de valeur

Instance unique d’application d’une règle à un événement donné.

## Réservation

Blocage temporaire d’une valeur avant décision finale.

## Preuve

Élément technique, financier ou institutionnel confirmant l’événement.

## Capture

Transformation d’une réservation en opération définitive.

## Libération

Retour d’une réservation dans le disponible.

## Compensation

Nouvelle opération corrigeant une précédente sans suppression de l’historique.

---

# 6. REGISTRE DES ÉVÉNEMENTS VALORISABLES

Catalogue initial :

| Code | Domaine | Effet possible |
|---|---|---|
| `AD_QUALIFIED_ATTENTION` | Advertising | crédit WP et consommation de campagne |
| `SUBSCRIPTION_PURCHASE` | Abonnements | débit et activation |
| `FONDS_PERSONAL_CONTRIBUTION` | Fonds | transfert vers le compartiment Fonds |
| `FONDS_COLLECTIVE_DEBIT` | Fonds | contribution et frais Wasplex |
| `ALERT_VISIBILITY_PURCHASE` | Alertes | débit Wallet |
| `ALERT_REWARD_RELEASE` | Alertes | transfert de récompense |
| `PARTNER_BENEFIT_SETTLED` | Partenaires | cashback ou avantage |
| `CARD_PURCHASE` | Carte | paiement de service |
| `REFUND_APPROVED` | Transversal | crédit compensatoire |
| `ADMIN_CORRECTION_APPROVED` | Administration | correction auditée |

Un événement inconnu ne produit aucune valeur.

---

# 7. VERSIONNEMENT DES RÈGLES

Chaque règle possède :

- un code stable ;
- une version ;
- un statut ;
- un pays ;
- une devise ou unité ;
- une période d’effet ;
- une priorité ;
- une formule ;
- une politique d’arrondi ;
- des frais ;
- des plafonds ;
- un modèle d’écriture comptable ;
- un auteur ;
- un approbateur ;
- une date de publication.

États :

```text
draft
simulated
approved
scheduled
active
superseded
suspended
retired
```

Une opération conserve toujours la version exacte utilisée.

---

# 8. AUCUNE VALEUR CODÉE EN DUR

Ne doivent pas être codés en dur :

- les gains publicitaires ;
- les quotas ;
- les poids 10/20/35/35 ;
- les frais Fonds ;
- les frais de retrait ;
- les coefficients de ciblage ;
- les prix d’abonnement ;
- les forfaits Alertes ;
- les commissions partenaires ;
- les plafonds ;
- les règles de remboursement.

Les valeurs initiales peuvent être installées par migration ou configuration, mais elles restent modifiables et versionnées.

---

# 9. RÉSOLUTION DE RÈGLE

Le moteur sélectionne une règle selon :

```text
event_code
+ source_module
+ country
+ currency
+ economic_class
+ campaign_version
+ effective_at
+ purpose
```

Ordre :

1. règle spécifique active ;
2. règle de marché ;
3. règle générale ;
4. refus explicite si aucune règle ne correspond.

Aucune formule de secours silencieuse.

---

# 10. ÉLIGIBILITÉ

Avant le calcul, vérifier :

- événement reconnu ;
- règle active ;
- utilisateur ou bénéficiaire valide ;
- module source autorisé ;
- pays et devise compatibles ;
- abonnement actif si requis ;
- quota disponible ;
- consentement si nécessaire ;
- budget suffisant ;
- plafond non dépassé ;
- compte non suspendu ;
- absence de doublon ;
- niveau KYC si requis ;
- capacité institutionnelle ;
- délai encore valable.

Résultats :

```text
eligible
ineligible
manual_review
temporarily_unavailable
```

Codes de refus :

```text
RULE_NOT_FOUND
RULE_NOT_ACTIVE
BUDGET_INSUFFICIENT
QUOTA_EXHAUSTED
CONSENT_MISSING
SUBSCRIPTION_INELIGIBLE
DUPLICATE_EVENT
PROOF_INVALID
RESERVATION_EXPIRED
LIMIT_EXCEEDED
WALLET_INSUFFICIENT
CURRENCY_MISMATCH
ACCOUNT_SUSPENDED
MANUAL_REVIEW_REQUIRED
```

---

# 11. DEVIS DE VALEUR

Le calcul produit un objet immuable `ValueQuote` contenant :

- montant brut ;
- frais externes ;
- taxes configurées ;
- montant net ;
- part Wasplex ;
- part utilisateur ;
- sous-enveloppes ;
- arrondi ;
- reliquat ;
- comptes à utiliser ;
- montant affichable ;
- règle et version ;
- expiration ;
- hash de calcul.

Le devis peut être affiché ou utilisé en interne selon le module.

---

# 12. MODÈLE PUBLICITAIRE ADOPTÉ

Pour une campagne :

```text
Budget net distribuable
├── 50 % Wasplex
└── 50 % enveloppe utilisateurs
```

Pour une campagne générale :

```text
Gratuit : 10 %
Premium : 20 %
Gold :    35 %
Platine : 35 %
```

Pour une seule classe ciblée : 100 % de l’enveloppe utilisateurs va à cette classe.

Pour plusieurs classes : normalisation entre les poids sélectionnés.

---

# 13. QUOTAS PUBLICITAIRES

Configuration initiale :

```text
Gratuit : 120 publicités/mois
Premium : 300 publicités/mois
Gold : 600 publicités/mois
Platine : 900 publicités/mois
```

Deux événements distincts :

```text
AdDelivered
→ consomme le quota

QualifiedAttentionValidated
→ produit le gain
```

Le quota compte toutes les publicités réellement affichées au-delà du seuil minimal configuré.

---

# 14. CALCUL DU GAIN PUBLICITAIRE

Exemple :

```text
Enveloppe Gold : 17 500 FCFA
Événements Gold financés : 100
Gain unitaire : 175 WP
```

Avant la diffusion, le moteur retourne :

```text
gain_displayed = 175 WP
reservation_amount = 175
```

Le montant promis ne change plus pendant la lecture.

---

# 15. RÉSERVATION AVANT LA PUBLICITÉ

Flux :

```text
Feed demande une publicité
→ Matching sélectionne une campagne
→ Value Engine calcule le gain
→ Ledger réserve la valeur
→ Feed reçoit la tentative
→ la publicité démarre
```

La réservation contient :

- `value_attempt_id` ;
- campagne ;
- utilisateur ;
- classe économique ;
- montant ;
- règle ;
- événement attendu ;
- expiration ;
- idempotency key.

Sans réservation valide, aucune publicité rémunérée ne commence.

---

# 16. BARRE DE PROGRESSION RÉELLE

La barre doit refléter l’attention réelle et non une animation décorative.

Événements suivis :

- lecture démarrée ;
- média visible ;
- temps actif ;
- pause ;
- perte de focus ;
- retour ;
- seuil atteint ;
- lecture terminée ;
- CTA réalisé ;
- abandon.

Le serveur vérifie :

- durée réelle du média ;
- séquence cohérente ;
- visibilité ;
- horodatages ;
- absence de saut interdit ;
- absence de duplication ;
- signature de session ;
- règle du format.

---

# 17. SESSION D’ATTENTION

Champs recommandés :

```text
attention_session_id
value_attempt_id
campaign_id
creative_version_id
user_id
device_session_id
started_at
last_heartbeat_at
visible_duration_ms
required_duration_ms
progress_percent
status
```

États :

```text
created
started
active
paused
backgrounded
completed
abandoned
expired
rejected
```

---

# 18. HEARTBEATS ET PREUVE

Le client envoie des heartbeats limités.

Le serveur :

- refuse les durées impossibles ;
- agrège la durée visible ;
- vérifie la séquence ;
- détecte les replays ;
- limite les appels ;
- conserve le minimum nécessaire.

Lorsque le seuil est atteint, le service Attention soumet une `AttentionProof` contenant :

- session ;
- événement attendu ;
- durée requise ;
- durée validée ;
- horodatages ;
- campagne/version ;
- signature ;
- décision antifraude ;
- idempotency key.

---

# 19. DÉCISION PUBLICITAIRE

## Validée

```text
réservation capturée
→ enveloppe campagne consommée
→ part utilisateur créditée
→ part Wasplex reconnue
→ tentative completed
```

## Abandonnée

```text
réservation libérée
→ aucun WP
→ quota conservé comme consommé si AdDelivered est valide
```

## Défaut Wasplex

```text
réservation libérée
→ quota restauré si nécessaire
→ aucun préjudice utilisateur
```

## Fraude confirmée

```text
réservation libérée
→ aucun gain
→ événement de sécurité
```

---

# 20. TRANSACTION ATOMIQUE

Opération finale :

```text
BEGIN
- verrouiller la tentative
- vérifier son statut
- vérifier la réservation
- poster la transaction grand livre
- mettre à jour la projection Wallet
- marquer la tentative completed
- écrire les événements outbox
COMMIT
```

Si une étape échoue :

```text
ROLLBACK
```

Aucun crédit Wallet sans transaction comptable publiée.

---

# 21. CONCURRENCE ET IDEMPOTENCE

Le moteur doit résister à :

- double clic ;
- double heartbeat final ;
- requêtes simultanées ;
- retries mobiles ;
- webhook dupliqué ;
- reprise worker ;
- deux appareils ;
- traitement parallèle.

Mécanismes :

- contraintes uniques ;
- idempotency keys ;
- verrouillage ciblé ;
- version optimiste ;
- statuts monotones ;
- transactions courtes.

Clé recommandée :

```text
source_module:event_type:business_event_id
```

Même clé et même charge : retourner le résultat existant.

Même clé et charge différente : conflit explicite et incident audité.

---

# 22. MACHINE D’ÉTAT D’UNE TENTATIVE

```text
created
quoted
reserved
started
awaiting_proof
validating
completed
rejected
released
expired
cancelled
compensated
manual_review
```

Transitions interdites :

- `completed → reserved` ;
- `expired → captured` ;
- suppression d’une tentative terminée ;
- réutilisation d’une tentative rejetée comme nouvelle tentative.

---

# 23. OUTBOX ET TEMPS RÉEL

Dans la transaction finale, écrire notamment :

```text
WalletRewardConfirmed
WalletBalanceChanged
AdvertisingBudgetConsumed
ValueAttemptCompleted
```

Un worker publie ensuite les événements.

Le client reçoit :

```text
wallet.balance.changed
```

avec :

- montant ;
- nouveau solde ;
- origine ;
- opération ;
- timestamp ;
- version de projection.

---

# 24. ANIMATION WALLET

L’animation démarre seulement après confirmation serveur :

1. barre à 100 % ;
2. état court « Validation… » si nécessaire ;
3. confirmation financière ;
4. animation du martin-pêcheur ou autre élément validé ;
5. icône Wallet centrale qui pulse ;
6. compteur qui augmente ;
7. toast `+175 WP` ;
8. nouvelle ligne d’historique.

En cas d’échec : aucune fausse animation.

---

# 25. ABONNEMENTS

Flux :

```text
Plan choisi
→ devis
→ réservation du paiement
→ confirmation
→ transaction Wallet
→ activation abonnement
→ événements outbox
```

Paiement possible :

- Wallet ;
- moyen externe rapproché ;
- combinaison si autorisée.

Si le débit réussit mais l’activation échoue : reprise, puis compensation si nécessaire.

---

# 26. FONDS — CONTRIBUTION PERSONNELLE

```text
Demande utilisateur
→ vérification du solde
→ calcul de règle
→ transfert vers le Solde Fonds
→ mise à jour du vœu
→ notification
```

Aucun solde général négatif.

---

# 27. FONDS — COLLECTE COLLECTIVE

Pour chaque participant :

```text
mandat actif
+ programme/pays/devise compatibles
+ plafond disponible
+ solde Fonds suffisant
+ bénéficiaire exclu
```

Le moteur calcule :

- contribution de solidarité ;
- frais fixe Wasplex configurable ;
- total à débiter ;
- éventuelle contribution à régulariser ;
- comptes de destination.

Le traitement est repris de manière idempotente participant par participant.

---

# 28. FRAIS FIXE FONDS

Le frais est distinct de la contribution :

```text
part solidarité
≠ frais Wasplex
```

Il est appliqué par compte effectivement débité, selon une règle versionnée.

---

# 29. ALERTES — VISIBILITÉ RENFORCÉE

Flux :

```text
Alerte admissible
→ choix du forfait
→ devis
→ débit Wallet
→ activation de diffusion
```

Le moteur vérifie :

- catégorie autorisée ;
- alerte validée ;
- aucune priorité vitale achetée ;
- solde disponible ;
- durée ;
- territoire ;
- règle active.

Si l’activation échoue : compensation ou crédit de service.

---

# 30. ALERTES — RÉCOMPENSE

```text
Auteur crée une récompense
→ montant réservé
→ restitution vérifiée
→ double confirmation
→ libération au bénéficiaire
```

En litige : `manual_review`.

Aucune institution policière n’est rémunérée par cette opération.

---

# 31. PARTENAIRES

Flux :

```text
Opération partenaire
→ preuve
→ confirmation
→ règlement ou garantie contractuelle
→ calcul commission/avantage
→ crédit pending ou available
```

Le moteur distingue :

- avantage utilisateur ;
- part Wasplex ;
- coût externe ;
- pool collectif éventuel ;
- remboursement.

Aucun crédit sur simple déclaration non vérifiée.

---

# 32. FUTURE CARTE WASPLEX

Le moteur réserve les usages :

- achat ou renouvellement ;
- support physique ;
- cashback ;
- avantage partenaire ;
- QR de paiement ;
- restitution Alertes ;
- accès à un service ;
- correction d’opération.

La Carte agit comme clé ou support relié au Wallet. Elle ne crée pas automatiquement un solde indépendant.

---

# 33. RETRAITS

```text
demande
→ devis des frais
→ éligibilité
→ réservation
→ prestataire
→ confirmation
→ capture
```

En échec : libération ou compensation, avec rapprochement obligatoire.

---

# 34. DÉPÔTS

```text
intention
→ paiement externe
→ détection
→ rapprochement
→ crédit
```

Une approbation manuelle est un événement signé, autorisé et audité.

---

# 35. TRANSFERTS

Le débit de A et le crédit de B appartiennent à la même transaction comptable.

Aucun état intermédiaire ne doit laisser :

- A débité sans B crédité ;
- B crédité sans A débité.

---

# 36. REMBOURSEMENTS ET CORRECTIONS

Un remboursement référence l’opération d’origine et précise :

- total ou partiel ;
- motif ;
- bénéficiaire ;
- frais remboursés ou non ;
- règle ;
- approbation ;
- transaction compensatoire.

Une correction exige :

```text
ADMIN_CORRECTION_APPROVED
```

avec initiateur, approbateur, motif, pièce, comptes, montant et référence.

Aucun bouton « modifier le solde ».

---

# 37. MULTI-PAYS, MULTI-DEVISE ET ARRONDIS

La résolution tient compte de :

- pays ;
- devise ;
- unité ;
- espace souverain ;
- prestataire ;
- règle locale ;
- timezone.

Une transaction ne mélange pas des devises sans conversion explicite.

Montants entiers uniquement.

Le moteur renvoie :

```text
distributed_amount
rounding_remainder
undistributed_remainder
```

Aucun reliquat caché.

---

# 38. PLAFONDS

Contrôles :

- par événement ;
- par utilisateur ;
- par jour ;
- par mois ;
- par campagne ;
- par module ;
- par pays ;
- par classe économique ;
- par KYC ;
- par prestataire.

Les contrôles critiques s’effectuent dans la transaction afin d’éviter les dépassements concurrents.

---

# 39. ANTIFRAUDE

Le moteur consomme une décision antifraude :

```text
allow
allow_with_monitoring
hold
manual_review
deny
```

Une décision `hold` peut placer la valeur en attente ou bloquer le retrait, mais elle n’efface aucune écriture.

---

# 40. MODE DÉGRADÉ ET REPRISE APRÈS PANNE

Si le temps réel échoue :

- le grand livre reste prioritaire ;
- l’outbox conserve l’événement ;
- le client affiche « synchronisation en cours » ;
- la notification sera rejouée.

Si le grand livre est indisponible :

- ne pas afficher de gain final ;
- conserver la preuve ;
- mettre la tentative en reprise ;
- ne pas créer de valeur locale fictive.

Workers de reprise :

- réservations expirées ;
- preuves en attente ;
- transactions non publiées ;
- événements outbox non diffusés ;
- projections Wallet à reconstruire ;
- rapprochements externes.

---

# 41. OBSERVABILITÉ

Mesures :

- tentatives créées ;
- réservations ;
- captures ;
- libérations ;
- montants ;
- latence Feed → Wallet ;
- doublons ;
- erreurs ;
- comptes de suspense ;
- divergences de projection ;
- reprises ;
- événements en revue.

Corrélation :

```text
trace_id
business_event_id
value_attempt_id
ledger_transaction_id
wallet_operation_id
```

---

# 42. JOURNAL D’EXPLICATION

Pour chaque décision :

```text
Événement reçu
Règle sélectionnée
Conditions évaluées
Montants calculés
Preuve vérifiée
Comptes utilisés
Transaction créée
Résultat diffusé
```

Les données sensibles ne sont pas copiées inutilement dans les métadonnées financières.

---

# 43. MODÈLE DE DONNÉES

Entités recommandées :

```text
value_event_types
value_rules
value_rule_versions
value_rule_conditions
value_rule_components
value_quotes
value_attempts
value_attempt_events
value_reservations
value_proofs
value_decisions
value_compensations
value_limits
value_limit_counters
value_idempotency_records
value_outbox_events
value_recovery_jobs
value_audit_events
```

Le grand livre reste dans son propre domaine.

---

# 44. CHAMPS ESSENTIELS

## value_rules

```text
id
code
version
event_type
country_code
currency
status
effective_from
effective_to
priority
formula_definition
rounding_policy
ledger_template
created_by
approved_by
published_at
```

## value_attempts

```text
id
event_type
source_module
business_event_id
beneficiary_type
beneficiary_id
economic_class
rule_version_id
quote_id
reservation_id
status
amount_minor
currency
idempotency_key
expires_at
ledger_transaction_id
created_at
completed_at
```

## value_proofs

```text
id
value_attempt_id
proof_type
source_reference
payload_hash
status
verified_at
verification_method
risk_decision
created_at
```

---

# 45. API INTERNE

```text
POST /internal/value/quotes
POST /internal/value/attempts
GET  /internal/value/attempts/{id}

POST /internal/value/attempts/{id}/reserve
POST /internal/value/attempts/{id}/start
POST /internal/value/attempts/{id}/proofs
POST /internal/value/attempts/{id}/validate
POST /internal/value/attempts/{id}/capture
POST /internal/value/attempts/{id}/release
POST /internal/value/attempts/{id}/cancel
POST /internal/value/attempts/{id}/compensate
```

Ces routes ne sont pas exposées directement au mobile.

---

# 46. API FEED

```text
POST /api/feed/ads/{ad}/start
POST /api/feed/attention/{session}/heartbeat
POST /api/feed/attention/{session}/complete
POST /api/feed/attention/{session}/abandon
GET  /api/feed/value-attempts/{id}
```

La réponse de démarrage contient :

- gain exact ;
- durée ;
- tentative ;
- session ;
- expiration ;
- règles d’affichage.

---

# 47. API ADMINISTRATION

```text
GET    /api/admin/value-engine/dashboard
GET    /api/admin/value-engine/rules
POST   /api/admin/value-engine/rules
PATCH  /api/admin/value-engine/rules/{id}
POST   /api/admin/value-engine/rules/{id}/simulate
POST   /api/admin/value-engine/rules/{id}/approve
POST   /api/admin/value-engine/rules/{id}/publish
POST   /api/admin/value-engine/rules/{id}/suspend

GET    /api/admin/value-engine/attempts
GET    /api/admin/value-engine/attempts/{id}
POST   /api/admin/value-engine/attempts/{id}/review

GET    /api/admin/value-engine/recovery
POST   /api/admin/value-engine/recovery/{id}/retry
```

---

# 48. SIMULATEUR ADMINISTRATIF

Avant publication, afficher :

- exemple de budget ;
- partage ;
- gains ;
- frais ;
- arrondis ;
- impact par classe ;
- nombre d’événements ;
- reliquat ;
- comptes comptables ;
- différence avec la version précédente.

Aucune règle économique critique ne doit être publiée sans simulation.

---

# 49. CONTRÔLE DU FONDATEUR ADMINISTRATEUR

Le fondateur administrateur doit pouvoir :

- voir toutes les configurations ;
- proposer, approuver et publier selon les capacités ;
- suspendre une règle ;
- consulter les calculs ;
- auditer les tentatives ;
- superviser les reprises ;
- déclencher une correction exceptionnelle explicite.

Toute intervention exceptionnelle reste nominative, motivée, datée, auditée et non destructive.

---

# 50. ÉVÉNEMENTS MÉTIER

```text
ValueQuoteCreated
ValueAttemptCreated
ValueAttemptEligible
ValueAttemptRejected
ValueReserved
ValueReservationExpired
ValueProofSubmitted
ValueProofValidated
ValueProofRejected
ValueCaptured
ValueReleased
ValueCompensated
ValueManualReviewRequired
ValueRulePublished
ValueRuleSuspended
WalletRewardConfirmed
LedgerPostingFailed
ValueRecoveryScheduled
```

---

# 51. TESTS UNITAIRES ET D’INTÉGRATION

## Règles

- résolution ;
- absence de règle ;
- version ;
- dates ;
- formule ;
- arrondi ;
- normalisation ;
- plafond ;
- devise ;
- compensation.

## Publicité

- campagne générale 10/20/35/35 ;
- Gold seul ;
- Premium + Gold ;
- gain avant diffusion ;
- réservation ;
- progression ;
- perte de focus ;
- abandon ;
- validation ;
- Wallet ;
- double preuve ;
- quota consommé ;
- quota restauré sur défaut Wasplex ;
- aucune publicité après quota.

## Grand livre

- transaction équilibrée ;
- atomicité ;
- double capture impossible ;
- rollback ;
- outbox ;
- compensation ;
- reconstruction Wallet ;
- concurrence.

## Fonds

- contribution personnelle ;
- collecte ;
- bénéficiaire exclu ;
- frais fixe ;
- insuffisance ;
- régularisation sans négatif ;
- reprise de batch.

## Alertes et partenaires

- visibilité admissible ;
- SOS non payant ;
- récompense ;
- litige ;
- cashback ;
- annulation ;
- remboursement ;
- aucune rémunération policière par dossier.

## Pannes et sécurité

- Ledger indisponible ;
- outbox bloquée ;
- worker redémarré ;
- réponse mobile perdue ;
- retry ;
- réservation expirée ;
- preuve dupliquée ;
- appel interne non autorisé ;
- règle modifiée sans capacité ;
- preuve falsifiée ;
- replay ;
- montant négatif ;
- overflow ;
- correction exceptionnelle auditée.

---

# 52. TESTS VISUELS

Produire au minimum :

1. publicité avec gain annoncé ;
2. barre de progression ;
3. état Validation ;
4. animation Wallet ;
5. solde avant/après ;
6. historique ;
7. abandon sans gain ;
8. quota atteint ;
9. administration des règles ;
10. simulateur ;
11. tentative en revue ;
12. reprise après erreur.

---

# 53. CRITÈRES D’ACCEPTATION

Le moteur est accepté lorsque :

1. aucun module ne modifie directement les soldes ;
2. les règles sont versionnées ;
3. les événements valorisables sont enregistrés ;
4. le gain publicitaire est connu avant lecture ;
5. une réservation existe ;
6. la barre représente l’attention réelle ;
7. la preuve est validée ;
8. le grand livre est écrit atomiquement ;
9. le Wallet est crédité après commit ;
10. l’animation dépend de la confirmation serveur ;
11. le double crédit est impossible ;
12. le double débit est impossible ;
13. les abandons libèrent la valeur ;
14. les quotas sont respectés ;
15. les flux Fonds fonctionnent ;
16. les flux Alertes fonctionnent ;
17. les partenaires disposent d’un contrat d’intégration ;
18. les remboursements sont compensatoires ;
19. les règles sont simulables ;
20. le fondateur peut superviser ;
21. les pannes sont récupérables ;
22. les tests critiques passent.

---

# 54. ORDRE D’IMPLÉMENTATION

## Phase 1 — Fondation

- registre d’événements ;
- règles/versionnement ;
- devis ;
- tentatives ;
- idempotence ;
- contrats Ledger.

## Phase 2 — Réservations

- réserver ;
- capturer ;
- libérer ;
- expirer ;
- reprendre.

## Phase 3 — Verticale publicitaire complète

- Feed ;
- session d’attention ;
- progression ;
- preuve ;
- Wallet ;
- animation.

## Phase 4 — Administration

- règles ;
- simulation ;
- publication ;
- audit ;
- dashboard.

## Phase 5 — Abonnements et transferts

- paiement ;
- activation ;
- compensation.

## Phase 6 — Fonds

- contributions ;
- collecte ;
- frais ;
- régularisation.

## Phase 7 — Alertes

- visibilité ;
- récompenses ;
- litiges.

## Phase 8 — Partenaires et Carte

- opérations ;
- commissions ;
- cashback ;
- remboursements.

## Phase 9 — Stabilisation

- concurrence ;
- pannes ;
- sécurité ;
- performance ;
- observabilité ;
- captures.

---

# 55. PREMIÈRE TRANCHE DE CODAGE RECOMMANDÉE

La première démonstration doit couvrir ce parcours complet :

```text
Campagne financée
→ utilisateur éligible
→ publicité sélectionnée
→ gain calculé
→ valeur réservée
→ vidéo regardée
→ barre complétée
→ preuve validée
→ grand livre écrit
→ Wallet crédité
→ animation
→ historique
→ reporting annonceur
```

Cette tranche devient la référence d’intégration pour les autres modules.

---

# 56. DIRECTIVE POUR CLAUDE CODE

1. lire les notes Publicité, Abonnements, Wallet et ce document ;
2. auditer le nouveau dépôt ;
3. identifier les contrats existants ;
4. produire un plan de fichiers court ;
5. coder les invariants avant l’interface ;
6. construire le grand livre ou son contrat avant les crédits ;
7. ne jamais écrire un solde directement ;
8. implémenter l’idempotence ;
9. réaliser la verticale Feed → Wallet ;
10. ajouter les tests de concurrence ;
11. ajouter l’administration des règles ;
12. fournir les migrations ;
13. fournir les captures ;
14. signaler seulement les contradictions réelles.

---

# 57. DÉCISION FINALE

Le Super moteur unifié de valeur en temps réel transforme une action admissible en valeur prouvée.

Il suit toujours cette chaîne :

```text
Événement
→ règle
→ éligibilité
→ calcul
→ réservation
→ preuve
→ transaction
→ Wallet
→ animation
→ audit
```

Principe final :

> **Wasplex ne promet jamais une valeur non financée, ne crédite jamais une action non prouvée, ne dépense jamais deux fois la même enveloppe et n’affiche jamais un gain définitif avant son inscription au grand livre.**
