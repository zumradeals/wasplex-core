# WASPLEX — INTÉGRATIONS EXTERNES

**Fichier cible recommandé :** `docs/18-integrations-externes/00-integrations-externes-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** couche transversale d’adaptateurs, connecteurs, webhooks, échanges sécurisés, reprise et supervision des services tiers  
**Interfaces officielles :**
- espace utilisateur : mobile-first strict ;
- Studio Annonceur, partenaires, professionnels et institutions : desktop complet + mobile opérationnel ;
- administration : desktop-first, mobile limité aux incidents et actions urgentes.  
**Dépendances :** Compte universel, Wallet & Grand Livre, Studio Annonceur, Feed, Fonds, Alertes, Santé, Carte, Live, Notifications, Espaces professionnels, Sécurité & Antifraude, Données & Permissions, Reporting & Observabilité, Administration centrale  
**Principe central :** aucun module métier Wasplex ne doit dépendre directement du format, du nom ou des particularités d’un prestataire externe  
**Directive produit :** toute intégration externe doit être encapsulée derrière un contrat interne stable, versionné, testable, remplaçable, observable et auditable  
**Important :** un prestataire externe peut confirmer un fait externe, mais il ne devient pas la source de vérité globale de Wasplex

---

# 1. Objet

Ce document définit :

- les principes d’intégration ;
- la couche d’adaptateurs ;
- les contrats internes ;
- les prestataires de paiement ;
- Mobile Money ;
- banques et virements ;
- cartes bancaires futures ;
- SMS ;
- email ;
- push ;
- stockage de fichiers ;
- CDN ;
- streaming Live ;
- géolocalisation et cartes ;
- KYC et vérification ;
- partenaires commerciaux ;
- institutions ;
- Santé ;
- webhooks ;
- callbacks ;
- polling ;
- files ;
- reprise ;
- idempotence ;
- rapprochement ;
- sécurité ;
- secrets ;
- supervision ;
- administration ;
- API ;
- modèles de données ;
- événements ;
- tests.

---

# 2. Vision produit

La chaîne cible est :

```text
module métier
→ contrat interne Wasplex
→ orchestrateur d’intégration
→ adaptateur prestataire
→ appel externe
→ réponse normalisée
→ événement métier
→ audit
```

Exemple dépôt :

```text
Wallet
→ PaymentProviderGateway
→ adaptateur Mobile Money
→ prestataire
→ webhook signé
→ normalisation
→ vérification
→ Grand Livre
→ Wallet
```

Exemple Live :

```text
Live
→ StreamingGateway
→ adaptateur fournisseur streaming
→ session
→ playback
→ événements techniques
→ observabilité
```

---

# 3. Ce que la couche d’intégration doit permettre

- ajouter un prestataire ;
- remplacer un prestataire ;
- utiliser plusieurs prestataires ;
- choisir par pays ;
- choisir par devise ;
- gérer le basculement ;
- normaliser les statuts ;
- traiter les webhooks ;
- réessayer ;
- rapprocher ;
- auditer ;
- superviser ;
- tester en sandbox ;
- désactiver un prestataire ;
- fonctionner en mode dégradé.

---

# 4. Ce que la couche d’intégration ne doit pas faire

Elle ne doit pas :

- contenir la logique métier complète ;
- modifier directement un solde ;
- approuver seule une campagne ;
- attribuer seule une récompense ;
- exposer les secrets aux interfaces ;
- propager les formats bruts des prestataires dans tous les modules ;
- dépendre d’un seul fournisseur sans abstraction ;
- accepter aveuglément un webhook ;
- dupliquer une opération sur retry ;
- supprimer les preuves ;
- devenir une couche doctrinale ou juridique bloquante.

---

# 5. Architecture générale

```text
Modules Wasplex
├── Payments Gateway
├── Messaging Gateway
├── Storage Gateway
├── Streaming Gateway
├── Identity Verification Gateway
├── Mapping Gateway
├── Institutional Exchange Gateway
├── Health Exchange Gateway
├── Partner Operations Gateway
└── External Data Gateway
        ↓
Provider Adapters
        ↓
External Providers
```

---

# 6. Contrats internes

Chaque catégorie d’intégration possède un contrat interne.

Exemples :

```text
PaymentProviderContract
PayoutProviderContract
SmsProviderContract
EmailProviderContract
PushProviderContract
StorageProviderContract
StreamingProviderContract
IdentityVerificationContract
MapProviderContract
InstitutionExchangeContract
HealthExchangeContract
PartnerSettlementContract
```

Les modules métier utilisent ces contrats, jamais les SDK fournisseurs directement.

---

# 7. Adaptateurs

Un adaptateur traduit :

```text
commande Wasplex
→ format prestataire
```

et :

```text
réponse prestataire
→ résultat normalisé Wasplex
```

Exemples :

```text
OrangeMoneyAdapter
MTNMobileMoneyAdapter
WaveAdapter
BankTransferAdapter
S3StorageAdapter
FirebasePushAdapter
GenericSmtpAdapter
StreamingProviderAdapter
```

Les noms réels seront choisis lors de l’intégration effective.

---

# 8. Registre des prestataires

Chaque prestataire possède :

```text
code
category
countries
currencies
capabilities
environments
status
priority
version
```

États :

```text
draft
testing
active
degraded
suspended
disabled
retired
```

---

# 9. Capacités d’un prestataire

Exemples :

```text
deposit
withdrawal
refund
balance_check
transaction_lookup
webhook
polling
recurring_debit
qr_payment
bank_transfer
sms
email
push
streaming
storage
identity_verification
```

---

# 10. Sélection d’un prestataire

Le routeur peut choisir selon :

- pays ;
- devise ;
- montant ;
- méthode ;
- disponibilité ;
- priorité ;
- coût ;
- limite ;
- santé ;
- version ;
- organisation ;
- contrat.

---

# 11. Routage multi-prestataires

Exemple :

```text
Côte d’Ivoire
├── Mobile Money A
├── Mobile Money B
└── Virement bancaire
```

Le routeur peut :

- proposer plusieurs choix ;
- sélectionner par défaut ;
- basculer en cas d’incident ;
- désactiver une méthode ;
- conserver l’intention initiale.

---

# 12. Identifiants externes

Chaque opération externe conserve :

- provider_code ;
- provider_reference ;
- internal_reference ;
- idempotency_key ;
- correlation_id ;
- trace_id ;
- statut brut ;
- statut normalisé ;
- dates ;
- preuves.

---

# 13. Idempotence

Toute commande externe sensible doit posséder une clé d’idempotence.

Exemples :

- dépôt ;
- retrait ;
- remboursement ;
- paiement partenaire ;
- SMS OTP ;
- création de session Live ;
- upload ;
- webhook.

Le retry ne doit jamais créer une seconde valeur.

---

# 14. Statuts normalisés

Chaque catégorie possède des états Wasplex indépendants des états fournisseurs.

Exemple paiement :

```text
created
pending
processing
confirmed
failed
cancelled
expired
reversed
refunded
unknown
```

---

# 15. Mapping des statuts

Chaque adaptateur possède une table :

```text
provider_status
→ wasplex_status
```

Les statuts inconnus deviennent :

```text
unknown
```

et déclenchent une revue ou une récupération.

---

# 16. Timeouts

Chaque intégration définit :

- timeout connexion ;
- timeout réponse ;
- timeout global ;
- durée d’attente métier ;
- expiration ;
- nombre de retries ;
- stratégie de reprise.

---

# 17. Retries

Stratégies :

- retry immédiat limité ;
- backoff exponentiel ;
- jitter ;
- retry différé ;
- polling ;
- file de reprise ;
- dead-letter queue.

Aucun retry aveugle pour une opération non idempotente.

---

# 18. Circuit breaker

Un circuit breaker peut :

- détecter les échecs ;
- ouvrir le circuit ;
- stopper les appels ;
- utiliser un fallback ;
- tester la reprise ;
- refermer progressivement.

États :

```text
closed
open
half_open
```

---

# 19. Mode dégradé

Exemples :

- dépôt enregistré mais en attente ;
- retrait indisponible ;
- SMS remplacé par push ;
- email différé ;
- Live non rémunéré ;
- upload local temporaire ;
- campagne conservée en brouillon ;
- institution via portail sécurisé alternatif.

---

# 20. Webhooks

Un webhook doit être :

- authentifié ;
- signé ;
- horodaté ;
- idempotent ;
- journalisé ;
- rejouable ;
- associé à un prestataire ;
- vérifié contre une intention connue.

---

# 21. Vérification d’un webhook

Contrôles :

```text
signature
timestamp
nonce
provider
event_type
reference
amount
currency
beneficiary
status
```

Un webhook invalide ne déclenche aucune opération métier définitive.

---

# 22. États d’un webhook

```text
received
verified
rejected
processed
duplicated
failed
requeued
dead_lettered
```

---

# 23. Réponse au prestataire

La réponse HTTP au webhook doit être séparée du traitement métier lourd.

Exemple :

```text
réception
→ validation minimale
→ persistance
→ réponse 200
→ traitement asynchrone
```

---

# 24. Polling

Utilisé lorsque :

- webhook absent ;
- webhook non fiable ;
- statut incertain ;
- rapprochement ;
- récupération après incident.

Le polling doit être limité et configurable.

---

# 25. Rapprochement

Le rapprochement compare :

- intentions Wasplex ;
- références prestataire ;
- confirmations ;
- montants ;
- devises ;
- statuts ;
- dates ;
- écritures Grand Livre.

---

# 26. États de rapprochement

```text
matched
missing_internal
missing_external
amount_mismatch
currency_mismatch
status_mismatch
duplicate
under_review
resolved
```

---

# 27. Source de vérité financière

Principe :

```text
prestataire
→ preuve externe de paiement

Grand Livre Wasplex
→ vérité financière interne
```

Le solde Wallet n’est jamais lu comme vérité depuis le prestataire externe.

---

# 28. Dépôts Mobile Money

Parcours :

```text
intention
→ choix opérateur
→ demande de paiement
→ interaction utilisateur
→ callback/webhook
→ vérification
→ Grand Livre
→ Wallet
→ notification
```

---

# 29. Dépôt initié par USSD ou application externe

Le système doit pouvoir gérer :

- redirection ;
- push opérateur ;
- code ;
- référence ;
- attente ;
- retour utilisateur ;
- confirmation ultérieure.

---

# 30. Dépôts non confirmés

États :

- en attente ;
- expiré ;
- abandonné ;
- à rapprocher ;
- preuve reçue ;
- confirmé.

Aucun crédit définitif sans confirmation suffisante.

---

# 31. Dépôt supervisé

En cas d’intégration incomplète :

```text
intention
→ preuve
→ revue administrative
→ vérification externe
→ approbation
→ Grand Livre
```

---

# 32. Retraits Mobile Money

Parcours :

```text
demande
→ réserve Wallet
→ sélection prestataire
→ envoi payout
→ statut
→ confirmation
→ capture définitive
```

En cas d’échec :

```text
échec confirmé
→ libération ou compensation
```

---

# 33. Retrait à statut inconnu

Le système doit :

- maintenir la réserve ;
- lancer un polling ;
- éviter une seconde tentative automatique ;
- rapprocher ;
- demander une revue si nécessaire.

---

# 34. Virements bancaires

Cas possibles :

- recharge par virement ;
- retrait entreprise ;
- règlement partenaire ;
- paiement prestataire Fonds ;
- remboursement.

Données :

- référence ;
- banque ;
- compte ;
- montant ;
- devise ;
- preuve ;
- date ;
- statut.

---

# 35. Facturation entreprise

Le Studio Annonceur peut permettre :

```text
facture
→ paiement bancaire
→ rapprochement
→ crédit Wallet annonceur
```

Le crédit commercial futur doit rester séparé du dépôt confirmé.

---

# 36. Cartes bancaires futures

L’architecture doit réserver :

- tokenisation ;
- autorisation ;
- capture ;
- annulation ;
- remboursement ;
- chargeback ;
- 3-D Secure ou mécanisme équivalent ;
- rapprochement.

Wasplex ne doit pas stocker inutilement les données complètes de carte.

---

# 37. Débits automatiques Fonds

Un prestataire peut permettre un mandat externe.

Le contrat interne doit gérer :

- création de mandat ;
- validation ;
- plafond ;
- statut ;
- débit ;
- échec ;
- révocation ;
- expiration ;
- preuve.

---

# 38. SMS

Cas :

- OTP ;
- sécurité ;
- paiement ;
- urgence ;
- support ;
- information institutionnelle.

Contrat :

```text
send
get_status
validate_sender
estimate_cost
```

---

# 39. Email

Cas :

- invitation ;
- reçu ;
- facture ;
- rapport ;
- export ;
- sécurité ;
- support.

Contrat :

```text
send_transactional
send_template
get_delivery_status
```

---

# 40. Push

Cas :

- Wallet ;
- Feed ;
- Live ;
- messages ;
- Alertes ;
- tâches professionnelles.

Le système doit gérer :

- tokens ;
- appareils ;
- invalidation ;
- sujets ;
- priorité ;
- données visibles ;
- deep links.

---

# 41. Stockage de fichiers

Catégories :

- médias publicitaires ;
- preuves ;
- documents ;
- avatars ;
- pièces Alertes ;
- pièces Santé ;
- cartes ;
- rapports ;
- exports ;
- replays Live.

---

# 42. Contrat de stockage

```text
upload
multipart_upload
complete_upload
get_signed_url
delete
archive
restore
get_metadata
```

---

# 43. URLs signées

Les fichiers sensibles utilisent :

- URL signée ;
- expiration ;
- capacité ;
- finalité ;
- journal d’accès ;
- protection contre partage durable.

---

# 44. Classification du stockage

Séparer :

```text
public_assets
private_assets
sensitive_assets
medical_assets
audit_archives
temporary_exports
```

---

# 45. CDN

Le CDN sert aux contenus publics ou autorisés :

- publicités ;
- images ;
- vidéos ;
- miniatures ;
- replays publics.

Les données Santé, preuves sensibles et exports ne doivent pas être placés sur un CDN public.

---

# 46. Traitement média

Pipeline possible :

```text
upload
→ scan
→ métadonnées
→ transcodage
→ miniature
→ sous-titres
→ modération
→ publication
```

---

# 47. Streaming Live

Le contrat doit permettre :

- créer une session ;
- générer une clé ;
- démarrer ;
- arrêter ;
- playback ;
- enregistrer ;
- récupérer l’état ;
- webhooks ;
- métriques ;
- modération ;
- replay.

---

# 48. Séparation streaming et rémunération

Le prestataire streaming confirme :

- session ;
- flux ;
- état technique ;
- événements de lecture.

Le Super moteur Wasplex décide :

- éligibilité ;
- bloc ;
- preuve ;
- récompense ;
- capture.

---

# 49. Reprise Live

En cas de panne prestataire :

- pause ;
- bascule future ;
- information ;
- préservation des blocs validés ;
- annulation des blocs non servis ;
- remboursement du reliquat ;
- rapport.

---

# 50. Cartes et géolocalisation

Usages :

- zones publicitaires approximatives ;
- points de vente ;
- établissements ;
- missions ;
- Alertes ;
- partenaires ;
- carte institutionnelle.

---

# 51. Contrat de cartographie

```text
geocode
reverse_geocode
search_places
validate_zone
calculate_route
render_map
```

---

# 52. Localisation publicitaire

Le ciblage publicitaire doit utiliser :

- zone déclarée ;
- ville ;
- commune ;
- zone approximative ;
- lieu d’intérêt autorisé.

La position exacte en temps réel n’est pas le comportement par défaut.

---

# 53. KYC et vérification d’identité

Un prestataire peut aider à :

- vérifier un document ;
- comparer des données ;
- vérifier une entreprise ;
- détecter un doublon ;
- produire un statut.

Wasplex conserve :

- résultat normalisé ;
- référence ;
- preuve minimale ;
- date ;
- version.

---

# 54. Contrat de vérification

```text
create_verification
submit_document
get_status
cancel
get_result
```

---

# 55. Résultats KYC normalisés

```text
pending
verified
rejected
needs_review
expired
cancelled
```

---

# 56. Limites du prestataire KYC

Le prestataire ne doit pas :

- devenir le compte utilisateur ;
- gérer les permissions Wasplex ;
- accéder au Wallet ;
- fournir des données au Matching publicitaire ;
- décider seul d’une sanction globale.

---

# 57. Partenaires commerciaux

Modes d’intégration :

- portail Wasplex ;
- API ;
- QR ;
- fichier de rapprochement ;
- webhook ;
- terminal futur.

---

# 58. Opération partenaire par API

```text
création
→ référence
→ preuve
→ confirmation partenaire
→ validation Wasplex
→ avantage
→ Grand Livre
→ règlement
```

---

# 59. Règlement partenaire

Le contrat peut gérer :

- montant ;
- période ;
- opérations ;
- frais ;
- remboursement ;
- preuve ;
- statut ;
- paiement ;
- rapprochement.

---

# 60. Institutions

Modes possibles :

```text
API sécurisée
portail institutionnel
échange de fichiers sécurisé
messagerie de dossier
gateway inter-institutionnelle
```

---

# 61. Principe institutionnel

Une institution ne reçoit pas une copie générale de Wasplex.

Elle reçoit :

- projection ;
- dossier ;
- événements ;
- pièces autorisées ;
- statut ;
- accusés ;
- transferts.

---

# 62. Contrat institutionnel

```text
send_case
acknowledge_case
send_case_event
request_information
transfer_case
close_case
```

---

# 63. Transfert transfrontalier futur

Prévoir un gateway permettant :

- institution source ;
- institution destination ;
- pays ;
- périmètre ;
- référence ;
- pièces ;
- statut ;
- accusé ;
- audit.

Aucune base policière universelle n’est créée par défaut.

---

# 64. Santé

Modes :

- portail Santé ;
- API établissement ;
- QR Carte ;
- capsule d’urgence ;
- échanges de documents ;
- notifications.

---

# 65. Contrat Santé

```text
request_access
grant_access
get_emergency_capsule
submit_health_event
close_access
acknowledge
```

---

# 66. Limites Santé

Une intégration Santé ne doit pas :

- exposer le dossier complet par défaut ;
- utiliser les données pour la publicité ;
- donner un accès général à l’institution ;
- contourner les permissions Wasplex ;
- garder un accès après expiration.

---

# 67. Carte Wasplex

Intégrations possibles :

- fabricant de support ;
- imprimeur ;
- expédition ;
- terminal partenaire ;
- QR ;
- NFC futur ;
- prestataire de paiement futur.

---

# 68. Fabrication et expédition

Le contrat peut gérer :

- commande ;
- lot ;
- personnalisation ;
- expédition ;
- suivi ;
- livraison ;
- échec ;
- retour ;
- remplacement.

---

# 69. QR et jetons

Les QR sensibles doivent être :

- signés ;
- expirants ;
- liés à une finalité ;
- non réutilisables si nécessaire ;
- audités ;
- vérifiés côté serveur.

---

# 70. Notifications externes

La couche communication peut utiliser plusieurs prestataires.

Le routeur choisit selon :

- canal ;
- pays ;
- coût ;
- priorité ;
- disponibilité ;
- langue ;
- type.

---

# 71. Antivirus et analyse de fichiers

Un service externe ou interne peut :

- scanner ;
- détecter ;
- mettre en quarantaine ;
- produire un verdict ;
- libérer ;
- rejeter.

États :

```text
pending_scan
clean
suspicious
infected
scan_failed
```

---

# 72. Modération assistée externe

Des outils externes peuvent produire :

- étiquettes ;
- scores ;
- transcriptions ;
- détection ;
- recommandations.

La décision métier finale reste dans Wasplex.

---

# 73. Intelligence artificielle future

Usages possibles :

- création publicitaire ;
- sous-titres ;
- traduction ;
- résumé ;
- classification ;
- assistance support ;
- détection d’anomalie.

Chaque usage doit passer par :

- contrat ;
- finalité ;
- données minimales ;
- version ;
- journal ;
- contrôle humain lorsque nécessaire.

---

# 74. API publiques Wasplex

Wasplex peut exposer des API à :

- partenaires ;
- institutions ;
- annonceurs avancés ;
- applications autorisées.

Elles doivent être :

- versionnées ;
- authentifiées ;
- limitées ;
- documentées ;
- observables ;
- auditables.

---

# 75. Versionnement API

Exemple :

```text
/api/v1/partner/operations
/api/v1/institution/cases
```

Une version retirée suit :

```text
active
deprecated
sunset
retired
```

---

# 76. Authentification API

Méthodes possibles :

- OAuth client credentials ;
- clés API limitées ;
- certificats ;
- signatures ;
- mTLS futur ;
- jetons courts.

Aucune clé maître partagée entre toutes les organisations.

---

# 77. Scopes API

Exemples :

```text
partner.operations.write
partner.settlements.read
institution.cases.receive
institution.cases.update
health.capsule.read
advertiser.reports.read
```

---

# 78. Rate limiting

Limites par :

- client ;
- organisation ;
- route ;
- pays ;
- type ;
- période ;
- risque.

Les limites critiques peuvent avoir une file ou un traitement prioritaire.

---

# 79. Quotas API

Un contrat peut définir :

- appels par minute ;
- appels par jour ;
- volume de fichiers ;
- webhooks ;
- rapports ;
- utilisateurs.

---

# 80. Sandbox

Chaque intégration doit proposer autant que possible :

- environnement sandbox ;
- identifiants de test ;
- références simulées ;
- webhooks de test ;
- scénarios ;
- erreurs simulées ;
- documentation.

---

# 81. Simulateur de prestataire

Lorsque le prestataire ne fournit pas une sandbox suffisante, Wasplex doit pouvoir simuler :

```text
success
pending
timeout
failure
duplicate
reversal
unknown
```

---

# 82. Secrets

Les secrets comprennent :

- clés API ;
- certificats ;
- tokens ;
- mots de passe ;
- clés webhook ;
- clés de chiffrement.

Ils doivent être :

- stockés dans un secret manager ;
- masqués ;
- versionnés ;
- rotatables ;
- limités par environnement ;
- audités.

---

# 83. Rotation des secrets

Flux :

```text
nouveau secret
→ test
→ activation
→ double période éventuelle
→ retrait ancien
→ audit
```

---

# 84. Accès aux secrets

Seuls les services et opérateurs autorisés peuvent :

- référencer ;
- renouveler ;
- désactiver ;
- tester.

L’interface n’affiche pas la valeur complète.

---

# 85. Certificats

Gérer :

- émission ;
- installation ;
- expiration ;
- rotation ;
- révocation ;
- alerte.

---

# 86. Journalisation

Chaque appel externe conserve :

```text
provider
operation
internal_reference
external_reference
request_status
response_status
duration
attempt
trace_id
created_at
```

Les secrets et données sensibles sont masqués.

---

# 87. Audit métier

Pour les opérations sensibles :

- acteur ;
- organisation ;
- prestataire ;
- action ;
- montant ;
- référence ;
- résultat ;
- motif ;
- trace ;
- date.

---

# 88. Observabilité

Indicateurs par prestataire :

- disponibilité ;
- latence ;
- taux de succès ;
- erreurs ;
- timeouts ;
- retries ;
- webhooks ;
- files ;
- rapprochements ;
- coûts ;
- dernière réussite.

---

# 89. Tableau de bord intégrations

Le back-office affiche :

- prestataires ;
- état ;
- pays ;
- catégories ;
- dernières erreurs ;
- volume ;
- latence ;
- webhooks ;
- rapprochements ;
- incidents ;
- secrets expirants ;
- certificats ;
- coûts.

---

# 90. Alertes

Exemples :

- prestataire indisponible ;
- taux d’échec élevé ;
- webhook non reçu ;
- signature invalide ;
- divergence de montant ;
- certificat bientôt expiré ;
- file saturée ;
- secret invalide ;
- coût inhabituel ;
- rapprochement en retard.

---

# 91. Kill switches

Exemples :

```text
disable_provider
disable_provider_country
disable_deposits
disable_withdrawals
disable_sms_provider
disable_streaming_provider
disable_partner_api
disable_institution_gateway
```

---

# 92. Basculement

Un basculement peut être :

- manuel ;
- automatique ;
- par pays ;
- par méthode ;
- par seuil ;
- temporaire ;
- progressif.

Le basculement automatique doit être testé et observable.

---

# 93. Coûts prestataires

Chaque prestataire peut avoir :

- frais fixes ;
- pourcentage ;
- minimum ;
- maximum ;
- devise ;
- SMS ;
- stockage ;
- streaming ;
- bande passante ;
- appel API.

Ces coûts servent au reporting, sans modifier silencieusement le modèle économique.

---

# 94. Administration

Le fondateur ou les agents autorisés peuvent :

- ajouter un prestataire ;
- configurer un pays ;
- configurer une devise ;
- activer ;
- suspendre ;
- tester ;
- changer la priorité ;
- gérer les secrets ;
- voir les webhooks ;
- relancer ;
- rapprocher ;
- déclencher un basculement ;
- ouvrir un incident.

---

# 95. Intervention exceptionnelle du fondateur

Le fondateur peut :

- désactiver un prestataire ;
- changer le routeur ;
- autoriser une reprise ;
- approuver une correction ;
- imposer un rapprochement ;
- activer un fallback ;
- bloquer un canal compromis.

L’action reste auditée.

---

# 96. Capacités

```text
integrations.providers.view
integrations.providers.manage
integrations.routes.manage
integrations.secrets.rotate
integrations.webhooks.view
integrations.webhooks.replay
integrations.reconciliation.view
integrations.reconciliation.manage
integrations.incidents.manage
integrations.kill_switch.activate
```

---

# 97. Modèle de données

Entités recommandées :

```text
integration_categories
integration_providers
integration_provider_versions
integration_provider_capabilities
integration_provider_countries
integration_provider_currencies
integration_routes
integration_route_rules

integration_credentials
integration_certificates
integration_secret_versions

integration_requests
integration_responses
integration_attempts
integration_operations
integration_status_mappings

integration_webhooks
integration_webhook_events
integration_webhook_failures
integration_polling_jobs

integration_reconciliations
integration_reconciliation_items
integration_disputes

integration_health_checks
integration_metrics
integration_alerts
integration_incidents
integration_costs
integration_audit_events
```

---

# 98. Champs — Provider

```text
id
code
category
name
status
priority
environment
adapter_class
version
created_at
```

---

# 99. Champs — Integration Operation

```text
id
provider_id
operation_type
internal_reference
external_reference
idempotency_key
status
amount
currency
attempt_count
trace_id
created_at
completed_at
```

---

# 100. Champs — Webhook Event

```text
id
provider_id
event_type
external_reference
signature_status
status
payload_hash
received_at
processed_at
trace_id
```

---

# 101. Champs — Reconciliation

```text
id
provider_id
period_start
period_end
status
matched_count
mismatch_count
total_internal
total_external
currency
created_at
```

---

# 102. API interne de paiement

```text
POST /internal/integrations/payments/deposits
POST /internal/integrations/payments/withdrawals
POST /internal/integrations/payments/refunds
GET  /internal/integrations/payments/operations/{id}
POST /internal/integrations/payments/operations/{id}/refresh
```

---

# 103. API interne communication

```text
POST /internal/integrations/sms/send
POST /internal/integrations/email/send
POST /internal/integrations/push/send
GET  /internal/integrations/deliveries/{id}
```

---

# 104. API interne stockage et média

```text
POST /internal/integrations/storage/uploads
POST /internal/integrations/storage/uploads/{id}/complete
GET  /internal/integrations/storage/assets/{id}/signed-url
POST /internal/integrations/media/transcode
POST /internal/integrations/media/scan
```

---

# 105. API interne streaming

```text
POST /internal/integrations/streaming/sessions
GET  /internal/integrations/streaming/sessions/{id}
POST /internal/integrations/streaming/sessions/{id}/stop
GET  /internal/integrations/streaming/sessions/{id}/metrics
```

---

# 106. API webhooks

```text
POST /webhooks/payments/{provider}
POST /webhooks/messaging/{provider}
POST /webhooks/streaming/{provider}
POST /webhooks/identity/{provider}
POST /webhooks/partners/{provider}
POST /webhooks/institutions/{provider}
```

---

# 107. API administration

```text
GET    /api/admin/integrations/providers
POST   /api/admin/integrations/providers
GET    /api/admin/integrations/providers/{id}
PATCH  /api/admin/integrations/providers/{id}
POST   /api/admin/integrations/providers/{id}/test
POST   /api/admin/integrations/providers/{id}/activate
POST   /api/admin/integrations/providers/{id}/suspend

GET    /api/admin/integrations/routes
POST   /api/admin/integrations/routes
PATCH  /api/admin/integrations/routes/{id}

GET    /api/admin/integrations/webhooks
POST   /api/admin/integrations/webhooks/{id}/replay

GET    /api/admin/integrations/reconciliations
POST   /api/admin/integrations/reconciliations
POST   /api/admin/integrations/reconciliations/{id}/resolve
```

---

# 108. Événements métier

```text
IntegrationProviderActivated
IntegrationProviderDegraded
IntegrationProviderSuspended
IntegrationRouteChanged

ExternalOperationCreated
ExternalOperationPending
ExternalOperationConfirmed
ExternalOperationFailed
ExternalOperationReversed

IntegrationWebhookReceived
IntegrationWebhookVerified
IntegrationWebhookRejected
IntegrationWebhookProcessed
IntegrationWebhookReplayed

IntegrationReconciliationStarted
IntegrationReconciliationMismatchDetected
IntegrationReconciliationCompleted

IntegrationFallbackActivated
IntegrationFallbackDeactivated
IntegrationIncidentCreated
IntegrationIncidentResolved
```

---

# 109. Sécurité

- authentification forte ;
- signatures ;
- TLS ;
- secret manager ;
- rotation ;
- certificats ;
- IP allowlist lorsque pertinent ;
- rate limiting ;
- idempotence ;
- anti-replay ;
- validation stricte ;
- masquage ;
- permissions ;
- audit ;
- séparation des environnements.

---

# 110. Protection des données

Chaque intégration ne reçoit que :

- les données nécessaires ;
- pour la finalité ;
- selon le contrat ;
- avec un identifiant approprié ;
- pendant la durée nécessaire.

Les données Santé, Alertes, Fonds et KYC ne doivent pas être envoyées à des prestataires publicitaires.

---

# 111. Résilience

- files ;
- retries ;
- circuit breakers ;
- fallbacks ;
- dead-letter queues ;
- polling ;
- rapprochement ;
- reprise idempotente ;
- snapshots ;
- procédures manuelles ;
- mode dégradé.

---

# 112. Performance

- appels asynchrones ;
- connexions réutilisées ;
- limites ;
- cache ;
- batch lorsque permis ;
- pagination ;
- compression ;
- streaming ;
- timeouts ;
- file prioritaire.

---

# 113. Accessibilité

Les erreurs d’intégration affichées aux utilisateurs doivent :

- être compréhensibles ;
- indiquer l’état ;
- proposer une prochaine action ;
- éviter les codes techniques seuls ;
- fonctionner sur mobile ;
- ne pas exposer des détails sensibles.

Exemple :

```text
Votre paiement est toujours en cours de vérification.
Aucune nouvelle tentative n’est nécessaire pour le moment.
```

---

# 114. Tests de contrat

Chaque adaptateur doit passer une suite commune :

- succès ;
- pending ;
- échec ;
- timeout ;
- duplicate ;
- reversal ;
- statut inconnu ;
- retry ;
- idempotence ;
- signature ;
- mapping.

---

# 115. Tests Mobile Money

- création dépôt ;
- webhook ;
- duplicata ;
- montant incorrect ;
- devise incorrecte ;
- retrait ;
- statut inconnu ;
- rapprochement ;
- compensation ;
- aucune écriture directe.

---

# 116. Tests SMS, email et push

- envoi ;
- livraison ;
- échec ;
- token invalide ;
- fallback ;
- modèle ;
- canal critique ;
- secret masqué ;
- retry.

---

# 117. Tests stockage et média

- upload ;
- multipart ;
- scan ;
- fichier infecté ;
- URL signée ;
- expiration ;
- transcodage ;
- CDN ;
- fichier Santé privé ;
- suppression temporaire.

---

# 118. Tests streaming

- création session ;
- démarrage ;
- arrêt ;
- webhook ;
- métriques ;
- panne ;
- reprise ;
- blocs validés préservés ;
- remboursement reliquat.

---

# 119. Tests institutionnels et Santé

Institutionnel :

- envoi dossier ;
- accusé ;
- événement ;
- transfert ;
- aucun accès global.

Santé :

- accès ;
- capsule minimale ;
- expiration ;
- aucune publicité ;
- audit.

---

# 120. Tests de sécurité

- signature invalide ;
- webhook rejoué ;
- secret expiré ;
- rotation ;
- certificat ;
- rate limit ;
- injection ;
- payload malformé ;
- environnement incorrect ;
- clé d’un autre prestataire.

---

# 121. Tests de basculement

- prestataire dégradé ;
- circuit ouvert ;
- fallback ;
- retour progressif ;
- aucune duplication ;
- réservations préservées ;
- reporting ;
- audit.

---

# 122. Tests de rapprochement

- match ;
- opération externe manquante ;
- opération interne manquante ;
- montant différent ;
- devise différente ;
- doublon ;
- résolution ;
- rapport.

---

# 123. Tests responsive

## Utilisateur

- choix de moyen de paiement mobile ;
- attente ;
- erreur ;
- reprise ;
- suivi.

## Annonceur et professionnels

- configuration desktop ;
- opérations ;
- rapports ;
- mobile terrain.

## Administration

- dashboard desktop ;
- webhooks ;
- rapprochements ;
- incidents ;
- mobile urgence.

---

# 124. Captures obligatoires

1. choix Mobile Money ;
2. dépôt en attente ;
3. dépôt confirmé ;
4. retrait ;
5. erreur compréhensible ;
6. dashboard prestataires ;
7. fiche prestataire ;
8. routeur ;
9. webhook ;
10. replay ;
11. rapprochement ;
12. divergence ;
13. santé des intégrations ;
14. secret masqué ;
15. rotation ;
16. streaming ;
17. stockage ;
18. incident ;
19. fallback ;
20. audit.

---

# 125. Critères d’acceptation

Le module est accepté lorsque :

1. les modules utilisent des contrats internes ;
2. les SDK fournisseurs ne sont pas dispersés ;
3. les prestataires sont enregistrés ;
4. les routes sont configurables ;
5. les statuts sont normalisés ;
6. les opérations sont idempotentes ;
7. les webhooks sont signés et vérifiés ;
8. les retries sont contrôlés ;
9. les circuit breakers existent ;
10. les dépôts et retraits passent par le Grand Livre ;
11. les statuts inconnus sont traités ;
12. le rapprochement existe ;
13. SMS, email et push sont abstraits ;
14. stockage et streaming sont abstraits ;
15. institutions et Santé utilisent des projections ;
16. les secrets sont protégés et rotatables ;
17. les prestataires sont observables ;
18. les fallbacks sont testables ;
19. les interfaces respectent la doctrine responsive ;
20. les tests critiques passent.

---

# 126. Ordre d’implémentation

## Phase 1 — Socle d’intégration

- contrats ;
- registre ;
- adaptateurs ;
- routeur ;
- statuts ;
- idempotence.

## Phase 2 — Webhooks et reprise

- réception ;
- signatures ;
- files ;
- retries ;
- dead letters ;
- polling.

## Phase 3 — Paiements

- dépôts ;
- retraits ;
- remboursements ;
- rapprochement ;
- Wallet annonceur.

## Phase 4 — Communications

- SMS ;
- email ;
- push ;
- delivery status ;
- fallback.

## Phase 5 — Stockage et média

- uploads ;
- scan ;
- URLs signées ;
- transcodage ;
- CDN.

## Phase 6 — Streaming

- sessions ;
- événements ;
- métriques ;
- replay ;
- incidents.

## Phase 7 — Identité et cartes

- KYC ;
- QR ;
- fabrication ;
- expédition ;
- partenaires.

## Phase 8 — Institutions et Santé

- gateways ;
- projections ;
- accusés ;
- transferts ;
- accès temporaires.

## Phase 9 — Administration et observabilité

- dashboard ;
- secrets ;
- certificats ;
- alertes ;
- coûts ;
- incidents.

## Phase 10 — Stabilisation

- sécurité ;
- performance ;
- résilience ;
- responsive ;
- tests ;
- captures.

---

# 127. Première verticale à livrer

```text
Annonceur ouvre son Wallet annonceur
→ choisit Mobile Money
→ intention créée
→ prestataire externe sollicité
→ webhook signé reçu
→ statut normalisé
→ vérification
→ Grand Livre crédité
→ Wallet annonceur mis à jour
→ notification
→ audit
→ rapprochement quotidien
```

Deuxième verticale :

```text
Utilisateur demande un retrait
→ montant réservé
→ payout externe
→ statut inconnu
→ aucun second retrait
→ polling
→ confirmation
→ Grand Livre
→ notification
```

Troisième verticale :

```text
Live sponsorisé
→ session streaming externe créée
→ playback disponible
→ événements techniques reçus
→ Wasplex valide les blocs d’attention
→ Grand Livre
→ Wallet spectateur
→ incident streaming observable
```

---

# 128. Directive pour Claude Code

1. lire Wallet, Studio Annonceur, Notifications, Live, Carte, Espaces professionnels, Santé, Alertes, Administration et Observabilité ;
2. auditer le nouveau dépôt ;
3. définir des contrats internes avant les SDK ;
4. créer un registre de prestataires ;
5. normaliser les statuts ;
6. implémenter l’idempotence ;
7. traiter les webhooks de manière sécurisée ;
8. créer retries, circuit breakers et dead letters ;
9. connecter les paiements au Grand Livre ;
10. créer le rapprochement ;
11. protéger les secrets et certificats ;
12. rendre toutes les intégrations observables ;
13. fournir simulateurs, migrations, API, tests et captures ;
14. ne pas introduire de couche doctrinale ou de gouvernance bloquante.

---

# 129. Décision finale

La couche d’intégration Wasplex doit fonctionner ainsi :

```text
contrat interne stable
→ adaptateur remplaçable
→ prestataire externe
→ résultat normalisé
→ événement métier
→ audit
→ observabilité
```

> **Wasplex doit pouvoir changer de prestataire, basculer en cas de panne et reprendre une opération sans dupliquer la valeur. Les systèmes externes fournissent des services et des preuves, mais les décisions métier et la vérité interne restent dans Wasplex.**
