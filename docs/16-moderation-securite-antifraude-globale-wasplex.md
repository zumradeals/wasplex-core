# WASPLEX — MODÉRATION, SÉCURITÉ & ANTIFRAUDE GLOBALE

**Fichier cible recommandé :** `docs/15-confiance-securite/00-moderation-securite-antifraude-globale-wasplex.md`  
**Statut :** spécification produit, fonctionnelle et technique prête au codage  
**Nature :** module transversal de protection opérationnelle, détection, enquête, décision, sanction, reprise et audit  
**Interfaces officielles :**
- espace utilisateur : mobile-first strict ;
- Studio Annonceur, partenaires, professionnels et institutions : desktop complet + mobile opérationnel ;
- administration : desktop-first, mobile limité aux alertes critiques et actions urgentes.  
**Dépendances :** Compte universel, Wallet & Grand Livre, Super moteur de valeur, Feed, Fonds, Alertes, Santé, Carte Wasplex, Live, Studio Annonceur, Espaces professionnels, Notifications, Administration centrale  
**Principe central :** Wasplex doit détecter les comportements anormaux, protéger les utilisateurs et les flux économiques, appliquer des décisions ciblées et traçables, puis permettre une reprise propre lorsqu’un risque est levé  
**Important :** ce module ne doit pas devenir un mécanisme général empêchant le codage. Il transforme les risques concrets en signaux, règles, décisions, états, actions, preuves et tests.

---

# 1. Objet

Ce document définit :

- la modération des contenus ;
- la sécurité des comptes ;
- l’antifraude publicitaire ;
- l’antifraude Wallet ;
- l’antifraude Fonds ;
- l’antifraude Carte et partenaires ;
- l’antifraude Live ;
- la protection des espaces professionnels ;
- la détection des multi-comptes ;
- la détection des appareils et sessions anormales ;
- les files d’enquête ;
- les preuves ;
- les sanctions ciblées ;
- les blocages temporaires ;
- les retenues financières ;
- les réexamens ;
- les incidents ;
- les alertes internes ;
- le reporting ;
- l’administration ;
- les API ;
- les modèles de données ;
- les événements ;
- les tests.

---

# 2. Vision produit

La chaîne cible est :

```text
événement
→ signal
→ score de risque
→ décision automatique ou revue humaine
→ action ciblée
→ notification
→ possibilité de réexamen
→ clôture
→ audit
```

Exemple publicitaire :

```text
heartbeats impossibles
→ score élevé
→ tentative placée en attente
→ aucun crédit
→ revue
→ validation ou rejet
```

Exemple Wallet :

```text
retrait inhabituel
→ authentification renforcée
→ réservation maintenue
→ vérification
→ confirmation ou libération
```

---

# 3. Ce que le module doit protéger

Le module protège :

- les personnes ;
- les comptes ;
- les appareils ;
- les identités ;
- les Wallets ;
- le Grand Livre ;
- les budgets annonceurs ;
- les récompenses ;
- les campagnes ;
- les partenaires ;
- les opérations Fonds ;
- les dossiers Alertes ;
- les accès Santé ;
- les Lives ;
- les contenus ;
- les équipes professionnelles ;
- les intégrations ;
- la réputation de Wasplex.

---

# 4. Ce que le module ne doit pas faire

Il ne doit pas :

- modifier directement les soldes ;
- supprimer des écritures ;
- effacer les preuves ;
- suspendre tout un compte lorsqu’une restriction ciblée suffit ;
- exposer les règles antifraude détaillées aux fraudeurs ;
- permettre une sanction anonyme ;
- utiliser Santé, Alertes ou Fonds comme source de ciblage publicitaire ;
- attribuer automatiquement une culpabilité définitive à partir d’un seul signal ;
- bloquer une fonctionnalité sans état, motif et possibilité de reprise ;
- introduire une constitution ou une couche textuelle supérieure aux modules.

---

# 5. Architecture générale

```text
Collecte de signaux
→ normalisation
→ moteur de risque
→ règle de décision
→ action
→ dossier d’enquête éventuel
→ résolution
→ audit
```

Composants recommandés :

```text
Risk Signal Registry
Risk Scoring Engine
Decision Engine
Moderation Queue
Fraud Case Manager
Sanction Service
Financial Hold Service
Incident Service
Appeal Review Service
Audit Trail
```

---

# 6. Registre des signaux

Chaque signal possède :

```text
code
domain
severity
source
description
default_weight
status
version
```

Exemples :

```text
ACCOUNT_MULTIPLE_IDENTITIES
DEVICE_SHARED_EXCESSIVELY
AD_HEARTBEAT_IMPOSSIBLE
LIVE_MULTI_SESSION
WALLET_DEPOSIT_REFERENCE_REUSED
WITHDRAWAL_VELOCITY_HIGH
PARTNER_OPERATION_DUPLICATED
CARD_QR_REPLAY
HEALTH_ACCESS_ANOMALOUS
ALERT_IDENTITY_INCONSISTENT
```

---

# 7. Sources de signaux

Sources techniques :

- authentification ;
- appareil ;
- session ;
- IP ;
- géographie approximative ;
- navigateur ;
- version d’application ;
- fréquence ;
- temps ;
- événements ;
- intégrations ;
- webhooks ;
- rapprochements ;
- signalements ;
- modération ;
- historique d’opérations.

Les signaux ne sont pas des verdicts.

---

# 8. Niveaux de risque

```text
low
moderate
high
critical
```

Interprétation :

## Low

- surveillance passive ;
- aucune interruption.

## Moderate

- contrôle supplémentaire ;
- limitation légère ;
- MFA.

## High

- mise en attente ;
- revue humaine ;
- restriction ciblée.

## Critical

- interruption immédiate ;
- kill switch local ;
- suspension ;
- incident ;
- escalade.

---

# 9. Décisions normalisées

```text
allow
allow_with_monitoring
challenge
hold
review
restrict
deny
suspend
terminate
compensate
```

---

# 10. Règles de décision

Une décision peut tenir compte de :

- signal ;
- score ;
- module ;
- montant ;
- pays ;
- classe ;
- ancienneté ;
- appareil ;
- historique ;
- répétition ;
- organisation ;
- capacité ;
- seuil ;
- contexte.

Chaque règle est :

- versionnée ;
- configurable ;
- simulable ;
- activable ;
- suspendable ;
- auditée.

---

# 11. Score de risque

Le score ne doit pas être l’unique source de décision.

Il sert à :

- classer ;
- prioriser ;
- déclencher un contrôle ;
- orienter une file ;
- comparer des événements.

Il ne doit pas devenir un score social général.

---

# 12. Confiance du compte

Le système peut calculer des indicateurs techniques :

- téléphone vérifié ;
- email vérifié ;
- KYC ;
- ancienneté ;
- appareils connus ;
- incidents ;
- récupérations ;
- opérations réussies ;
- sanctions actives.

Ces indicateurs ne sont pas publics.

---

# 13. Sécurité des comptes

Contrôles :

- mot de passe ;
- OTP ;
- MFA ;
- passkey future ;
- appareil ;
- session ;
- récupération ;
- changement de téléphone ;
- changement d’email ;
- détection de connexion inhabituelle ;
- fermeture de sessions ;
- historique.

---

# 14. Connexion inhabituelle

Signaux possibles :

- nouvel appareil ;
- pays différent ;
- vitesse géographique impossible ;
- adresse IP à risque ;
- nombreuses tentatives ;
- changement de SIM ;
- récupération récente ;
- appareil compromis.

Actions :

- MFA ;
- blocage temporaire ;
- notification ;
- fermeture de sessions ;
- revue.

---

# 15. Appareils

Chaque appareil peut posséder :

```text
trusted
known
new
restricted
blocked
```

L’utilisateur peut :

- voir ses appareils ;
- déconnecter ;
- marquer un appareil inconnu ;
- signaler une compromission.

---

# 16. Multi-comptes

Le système peut détecter :

- même appareil ;
- même téléphone ;
- même document ;
- même moyen de paiement ;
- mêmes références ;
- comportements synchronisés ;
- réseaux communs ;
- transferts circulaires.

Un signal de proximité ne suffit pas à suspendre automatiquement.

Exemples légitimes :

- famille ;
- cybercafé ;
- entreprise ;
- appareil partagé ;
- établissement.

---

# 17. Sessions simultanées

Règles configurables par module :

- Feed rémunéré : session unique de gain ;
- Live rémunéré : une seule session récompensée ;
- Wallet : plusieurs sessions possibles avec protections ;
- administration : limites renforcées ;
- Santé : session courte et MFA.

---

# 18. Fraude publicitaire

Signaux :

- vidéo accélérée ;
- heartbeats impossibles ;
- automatisation ;
- lecture hors écran ;
- répétition artificielle ;
- multi-appareils ;
- émulateur ;
- manipulation de l’horloge ;
- sessions partagées ;
- événements dupliqués ;
- boucle de récompenses.

---

# 19. Fermes de visionnage

Détection possible :

- volumes identiques ;
- appareils similaires ;
- horaires synchronisés ;
- comportement trop régulier ;
- réseau commun massif ;
- progression parfaite répétée ;
- mêmes campagnes ;
- transferts liés ;
- création de comptes en série.

Actions :

- mise en attente ;
- limitation ;
- revue ;
- suspension de récompenses ;
- investigation groupée.

---

# 20. Décision sur une récompense publicitaire

```text
preuve valide
→ capture
→ crédit

preuve douteuse
→ hold
→ revue

preuve invalide
→ libération
→ rejet
```

Aucun crédit définitif ne doit précéder la validation.

---

# 21. Fraude Live

Signaux :

- sessions multiples ;
- audience artificielle ;
- heartbeats synthétiques ;
- comptes liés ;
- collusion créateur-spectateur ;
- blocs validés trop vite ;
- interactions automatisées ;
- création de places fictives ;
- utilisation coordonnée d’émulateurs.

---

# 22. Fraude Wallet

Signaux :

- dépôts falsifiés ;
- référence réutilisée ;
- retrait inhabituel ;
- transferts circulaires ;
- fractionnement ;
- vitesse excessive ;
- destinations multiples ;
- compte récemment récupéré ;
- appareil inconnu ;
- changement d’identité ;
- rétrofacturation externe.

---

# 23. Retenue financière

Une retenue peut viser :

- dépôt ;
- retrait ;
- récompense ;
- remboursement ;
- cashback ;
- paiement partenaire ;
- gain Live.

Elle doit préciser :

- montant ;
- motif ;
- durée ;
- dossier ;
- statut ;
- règle ;
- issue.

La retenue ne modifie pas le Grand Livre de manière opaque.

---

# 24. États d’une retenue

```text
created
active
under_review
released
captured
rejected
compensated
expired
```

---

# 25. Fraude aux dépôts

Contrôles :

- référence externe ;
- webhook signé ;
- montant ;
- devise ;
- bénéficiaire ;
- date ;
- duplicata ;
- preuve ;
- rapprochement ;
- statut prestataire.

Aucun crédit sur capture d’écran seule sans procédure de vérification.

---

# 26. Fraude aux retraits

Contrôles :

- MFA ;
- appareil ;
- historique ;
- montant ;
- vitesse ;
- destination ;
- KYC ;
- incident ;
- solde disponible ;
- réservation ;
- prestataire.

---

# 27. Fraude Fonds

Signaux :

- faux vœu ;
- faux prestataire ;
- documents dupliqués ;
- collusion ;
- prestations fictives ;
- montants incohérents ;
- contributions contournées ;
- bénéficiaire inclus dans son propre débit ;
- remboursements suspects.

---

# 28. Fraude partenaires

Signaux :

- opération inventée ;
- preuve réutilisée ;
- cashback répété ;
- annulation après avantage ;
- point de vente fictif ;
- montant gonflé ;
- collusion ;
- règlement incohérent ;
- même Carte utilisée anormalement.

---

# 29. Fraude Carte Wasplex

Signaux :

- QR rejoué ;
- identifiant testé en masse ;
- support cloné ;
- scans impossibles ;
- paiement répété ;
- carte déclarée perdue utilisée ;
- tentative d’accès Santé abusive.

---

# 30. Fraude Alertes

Signaux :

- fausse disparition ;
- fausse propriété ;
- fausse restitution ;
- documents falsifiés ;
- récompense organisée ;
- harcèlement ;
- signalement malveillant ;
- données incohérentes ;
- comptes liés.

Les alertes vitales doivent être protégées contre les retards injustifiés : la vérification doit être proportionnée à l’urgence.

---

# 31. Sécurité Santé

Signaux :

- accès d’urgence répétés ;
- professionnel non vérifié ;
- établissement incohérent ;
- volume anormal ;
- MFA absente ;
- accès hors périmètre ;
- consultation après expiration ;
- tentative d’export.

Actions :

- refus ;
- alerte ;
- fermeture d’accès ;
- incident ;
- suspension de capacité ;
- revue.

---

# 32. Fraude annonceur

Signaux :

- faux paiement ;
- destination dangereuse ;
- marque usurpée ;
- média trompeur ;
- campagnes dupliquées ;
- budget incohérent ;
- redirection frauduleuse ;
- agence non autorisée ;
- manipulation de reporting ;
- tentative de réidentification.

---

# 33. Fraude institutionnelle ou professionnelle

Signaux :

- compte partagé ;
- capacité détournée ;
- export massif ;
- dossier consulté sans mission ;
- statut falsifié ;
- preuve altérée ;
- opération hors territoire ;
- utilisation après révocation ;
- session inhabituelle.

---

# 34. Modération des contenus

Contenus concernés :

- Feed ;
- publicités ;
- Live ;
- commentaires ;
- profils publics ;
- médias annonceurs ;
- offres partenaires ;
- Alertes publiques ;
- messages signalés ;
- replays ;
- pièces communicables.

---

# 35. Catégories de modération

Exemples :

```text
fraud
spam
harassment
violence
dangerous_content
impersonation
privacy_violation
prohibited_product
sexual_content
copyright
misleading_claim
malware_or_phishing
other
```

---

# 36. Files de modération

Files recommandées :

```text
critical_safety
financial_risk
live_now
advertising_review
alerts_review
partner_review
user_reports
copyright
appeals
```

---

# 37. Priorisation

La priorité dépend de :

- risque de vie ;
- montant ;
- diffusion en direct ;
- nombre de personnes ;
- vitesse de propagation ;
- contenu ;
- statut institutionnel ;
- récidive ;
- signalement multiple.

---

# 38. Décisions de modération

```text
approve
request_changes
limit_visibility
remove
mute
restrict
suspend
terminate
escalate
restore
```

---

# 39. Sanctions ciblées

Une sanction peut viser uniquement :

- commentaire ;
- publication ;
- campagne ;
- Live ;
- gain ;
- retrait ;
- transfert ;
- Carte ;
- espace professionnel ;
- capacité ;
- organisation ;
- appareil ;
- compte entier.

Principe :

> utiliser la restriction la plus précise capable de réduire le risque.

---

# 40. Durée des sanctions

```text
temporary
until_review
fixed_period
indefinite
permanent
```

Une sanction temporaire possède une date de fin.

---

# 41. États d’une sanction

```text
draft
active
paused
expired
revoked
completed
appealed
replaced
```

---

# 42. Notification d’une sanction

L’utilisateur ou l’organisation doit recevoir une information compréhensible :

- action concernée ;
- restriction ;
- durée ;
- motif général ;
- prochaine étape ;
- possibilité de réexamen si disponible.

Les détails techniques antifraude sensibles ne sont pas exposés.

---

# 43. Réexamen

Nom interface recommandé :

```text
Demander un réexamen
```

Le réexamen permet :

- explication ;
- pièce ;
- commentaire ;
- correction ;
- nouvelle preuve ;
- suivi ;
- décision.

Il ne doit pas être utilisé pour bloquer indéfiniment l’exécution d’une sanction urgente.

---

# 44. États d’un réexamen

```text
submitted
under_review
information_requested
approved
partially_approved
rejected
closed
```

---

# 45. Séparation entre détection et décision

Le service de détection produit :

- signaux ;
- scores ;
- recommandations.

Le service de décision produit :

- action ;
- motif ;
- durée ;
- dossier ;
- audit.

Cela évite qu’un simple détecteur modifie directement les droits ou les finances.

---

# 46. Dossier d’enquête

Un dossier contient :

- sujet ;
- domaine ;
- signaux ;
- événements ;
- preuves ;
- comptes liés ;
- opérations ;
- décisions ;
- analystes ;
- notes ;
- historique ;
- issue.

---

# 47. États d’un dossier

```text
open
triaged
investigating
waiting_information
actioned
monitoring
resolved
closed
reopened
```

---

# 48. Assignation des dossiers

Un dossier peut être assigné à :

- analyste ;
- équipe ;
- domaine ;
- superviseur ;
- incident ;
- fondateur.

---

# 49. Preuves

Types :

- événements système ;
- logs ;
- références externes ;
- documents ;
- captures ;
- médias ;
- signatures ;
- webhooks ;
- rapprochements ;
- résultats d’intégration ;
- déclarations ;
- audit.

Une preuve possède :

- source ;
- intégrité ;
- date ;
- lien au dossier ;
- niveau de confiance ;
- accès.

---

# 50. Intégrité des preuves

Mesures possibles :

- hash ;
- signature ;
- stockage immuable ;
- horodatage ;
- journal ;
- contrôle d’accès ;
- version ;
- chaîne de possession technique.

---

# 51. Notes d’enquête

Les notes internes :

- sont nominatives ;
- sont datées ;
- ne modifient pas les événements sources ;
- peuvent être corrigées par nouvelle entrée ;
- restent dans le dossier.

---

# 52. Groupes liés

Le système peut regrouper des comptes, appareils ou organisations dans un cluster d’enquête.

Le regroupement sert à l’analyse, pas à une suspension automatique collective.

---

# 53. Action coordonnée multi-modules

Exemple :

```text
fraude confirmée
→ suspendre récompenses Feed
→ bloquer retraits
→ maintenir accès au support
→ préserver consultation du Wallet
→ notifier
→ ouvrir réexamen
```

Les actions restent ciblées.

---

# 54. Mode surveillance

Un sujet peut être placé en surveillance sans notification visible lorsque :

- aucun droit n’est retiré ;
- aucune donnée n’est exposée ;
- seuls les signaux sont renforcés.

Le mode surveillance ne doit pas devenir permanent sans réévaluation.

---

# 55. Restrictions silencieuses interdites

Interdit de :

- réduire secrètement un gain promis ;
- modifier secrètement un quota ;
- cacher une sanction active ;
- confisquer une valeur sans transaction ;
- supprimer un contenu sans statut.

---

# 56. Incidents de sécurité

Types :

- compromission de compte ;
- fuite ;
- fraude massive ;
- prestataire compromis ;
- webhook falsifié ;
- clés exposées ;
- attaque ;
- interruption ;
- comportement interne anormal.

---

# 57. États d’un incident

```text
detected
triaged
investigating
contained
mitigating
monitoring
resolved
postmortem
```

---

# 58. Réponse à incident

```text
détecter
→ contenir
→ protéger
→ préserver les preuves
→ restaurer
→ rapprocher les opérations
→ notifier les parties concernées
→ clôturer
```

---

# 59. Kill switches

Exemples :

```text
disable_all_withdrawals
disable_new_deposits
disable_ad_rewards
disable_live_rewards
disable_partner_cashback
disable_card_payments
disable_sensitive_health_access
disable_new_campaigns
```

Ils sont activés avec :

- motif ;
- acteur ;
- périmètre ;
- durée ;
- audit ;
- plan de réactivation.

---

# 60. Fondateur et intervention exceptionnelle

Le fondateur peut :

- suspendre une règle ;
- arrêter un Live ;
- bloquer un prestataire ;
- libérer une opération après preuve ;
- imposer un remboursement ;
- déclencher une compensation ;
- restaurer une capacité ;
- annuler une sanction incorrecte ;
- activer un kill switch.

Toute intervention produit un audit complet.

---

# 61. Limites de l’intervention

Même le fondateur ne doit pas :

- supprimer une écriture ;
- effacer une preuve ;
- modifier directement un solde ;
- effacer une sanction passée ;
- rendre une action invisible ;
- falsifier un événement.

---

# 62. Équipe de modération et antifraude

Rôles possibles :

```text
moderator
senior_moderator
fraud_analyst
financial_risk_analyst
security_operator
incident_manager
investigation_supervisor
auditor
```

Les droits sont définis par capacités.

---

# 63. Capacités

```text
risk.signals.view
risk.rules.manage
risk.cases.view
risk.cases.assign
risk.cases.resolve
risk.holds.create
risk.holds.release
moderation.content.review
moderation.content.remove
moderation.sanctions.apply
moderation.appeals.review
security.incidents.manage
security.kill_switch.activate
security.audit.view
```

---

# 64. Séparation des fonctions

Exemples :

- l’analyste propose une correction financière ;
- un autre acteur l’approuve ;
- le modérateur retire un contenu ;
- l’auditeur ne modifie pas la décision ;
- le gestionnaire de règles ne clôture pas seul un dossier critique.

Le fondateur peut utiliser son override tracé.

---

# 65. Administration du moteur de risque

Le back-office permet de :

- créer un signal ;
- définir un poids ;
- créer une règle ;
- simuler ;
- activer ;
- suspendre ;
- voir les résultats ;
- analyser les faux positifs ;
- comparer les versions ;
- restaurer une règle précédente par nouvelle version.

---

# 66. Simulation

Une règle peut être testée sur :

- événements historiques ;
- données de test ;
- environnement de staging ;
- échantillon ;
- période ;
- module.

La simulation ne doit pas appliquer de sanction réelle.

---

# 67. Mode observation

Une nouvelle règle peut fonctionner en :

```text
observe_only
```

Elle produit des résultats sans agir.

Après évaluation :

```text
active
```

---

# 68. Mesure des faux positifs

Le tableau de bord suit :

- décisions annulées ;
- réexamens acceptés ;
- dossiers sans fraude ;
- temps de résolution ;
- impact financier ;
- groupes affectés ;
- règles trop agressives.

---

# 69. Reporting

Indicateurs :

- signaux générés ;
- dossiers ouverts ;
- fraude confirmée ;
- valeur protégée ;
- valeur libérée ;
- sanctions ;
- réexamens ;
- faux positifs ;
- incidents ;
- temps moyen ;
- modules ;
- pays ;
- organisations ;
- appareils ;
- tendances.

---

# 70. Notifications internes

Exemples :

- pic de fraude ;
- prestataire anormal ;
- retrait élevé ;
- Live suspect ;
- QR rejoué ;
- accès Santé inhabituel ;
- référence de dépôt dupliquée ;
- campagne dangereuse ;
- cluster multi-comptes ;
- kill switch activé.

---

# 71. Notifications externes

L’utilisateur reçoit une notification lorsque :

- action requise ;
- compte sécurisé ;
- opération retenue ;
- restriction appliquée ;
- réexamen mis à jour ;
- session fermée ;
- appareil bloqué ;
- remboursement ou libération.

---

# 72. Modèle de données

Entités recommandées :

```text
risk_signal_definitions
risk_signals
risk_rule_sets
risk_rule_versions
risk_evaluations
risk_decisions
risk_scores

fraud_cases
fraud_case_subjects
fraud_case_events
fraud_case_evidence
fraud_case_notes
fraud_case_assignments
fraud_clusters

moderation_cases
moderation_items
moderation_decisions
moderation_sanctions
moderation_appeals

financial_holds
financial_hold_events
security_incidents
security_incident_events
security_kill_switches

device_risk_profiles
session_risk_profiles
account_risk_profiles
organization_risk_profiles
risk_audit_events
```

---

# 73. Champs — Risk Signal

```text
id
definition_code
domain
subject_type
subject_id
source_event_id
severity
weight
status
observed_at
expires_at
metadata
```

---

# 74. Champs — Risk Decision

```text
id
evaluation_id
decision
reason_code
rule_version_id
action_type
action_target
status
decided_by
decided_at
```

---

# 75. Champs — Fraud Case

```text
id
domain
priority
status
primary_subject_type
primary_subject_id
risk_score
assigned_team
opened_at
resolved_at
resolution_code
```

---

# 76. Champs — Sanction

```text
id
subject_type
subject_id
scope
action
reason_code
starts_at
ends_at
status
case_id
created_by
```

---

# 77. Champs — Financial Hold

```text
id
account_id
operation_type
operation_id
amount
currency
reason_code
status
created_at
released_at
captured_at
ledger_transaction_id
```

---

# 78. API d’évaluation interne

```text
POST   /internal/risk/evaluate
POST   /internal/risk/signals
GET    /internal/risk/evaluations/{id}
POST   /internal/risk/decisions/{id}/execute
```

Ces API ne sont pas publiques.

---

# 79. API administration du risque

```text
GET    /api/admin/risk/dashboard
GET    /api/admin/risk/signals
GET    /api/admin/risk/rules
POST   /api/admin/risk/rules
POST   /api/admin/risk/rules/{id}/simulate
POST   /api/admin/risk/rules/{id}/publish
POST   /api/admin/risk/rules/{id}/suspend
```

---

# 80. API dossiers

```text
GET    /api/admin/fraud-cases
GET    /api/admin/fraud-cases/{id}
POST   /api/admin/fraud-cases/{id}/assign
POST   /api/admin/fraud-cases/{id}/evidence
POST   /api/admin/fraud-cases/{id}/notes
POST   /api/admin/fraud-cases/{id}/resolve
POST   /api/admin/fraud-cases/{id}/reopen
```

---

# 81. API modération

```text
GET    /api/admin/moderation/cases
GET    /api/admin/moderation/cases/{id}
POST   /api/admin/moderation/cases/{id}/approve
POST   /api/admin/moderation/cases/{id}/request-changes
POST   /api/admin/moderation/cases/{id}/remove
POST   /api/admin/moderation/cases/{id}/sanctions
```

---

# 82. API réexamen

```text
POST   /api/me/reviews
GET    /api/me/reviews
GET    /api/me/reviews/{id}
POST   /api/me/reviews/{id}/evidence

GET    /api/admin/reviews
POST   /api/admin/reviews/{id}/request-information
POST   /api/admin/reviews/{id}/approve
POST   /api/admin/reviews/{id}/reject
```

---

# 83. API retenues

```text
GET    /api/admin/financial-holds
GET    /api/admin/financial-holds/{id}
POST   /api/admin/financial-holds/{id}/release
POST   /api/admin/financial-holds/{id}/capture
POST   /api/admin/financial-holds/{id}/compensate
```

---

# 84. API incidents

```text
GET    /api/admin/security/incidents
POST   /api/admin/security/incidents
GET    /api/admin/security/incidents/{id}
POST   /api/admin/security/incidents/{id}/actions
POST   /api/admin/security/incidents/{id}/resolve
```

---

# 85. Événements métier

```text
RiskSignalRecorded
RiskEvaluationCompleted
RiskDecisionCreated
RiskDecisionExecuted

FraudCaseOpened
FraudCaseAssigned
FraudEvidenceAdded
FraudCaseResolved
FraudCaseReopened

ContentModerationRequested
ContentApproved
ContentRestricted
ContentRemoved

SanctionApplied
SanctionExpired
SanctionRevoked

FinancialHoldCreated
FinancialHoldReleased
FinancialHoldCaptured
FinancialHoldCompensated

ReviewRequested
ReviewApproved
ReviewRejected

SecurityIncidentCreated
SecurityIncidentContained
SecurityIncidentResolved
KillSwitchActivated
KillSwitchDeactivated
```

---

# 86. Journal et audit

Chaque action conserve :

```text
actor
space
organization
capability
case
decision
before
after
reason
session
device
trace
timestamp
```

Le journal est append-only.

---

# 87. Données sensibles

Accès limité pour :

- Santé ;
- Alertes ;
- KYC ;
- preuves financières ;
- sécurité ;
- appareils ;
- documents.

Un analyste publicitaire ne reçoit pas automatiquement un accès Santé ou KYC.

---

# 88. Conservation technique

Chaque type de signal, preuve, dossier ou sanction possède une durée configurable.

Le module doit permettre :

- expiration ;
- archivage ;
- suppression des éléments non nécessaires ;
- conservation des écritures et audits indispensables ;
- séparation entre données actives et archives.

---

# 89. Performance

- traitement asynchrone ;
- files prioritaires ;
- règles mises en cache ;
- évaluations idempotentes ;
- partitionnement ;
- index ;
- agrégats ;
- stockage séparé des preuves lourdes ;
- mode observation ;
- reprise.

---

# 90. Résilience

En cas d’indisponibilité du moteur de risque :

- opérations faibles : politique configurable ;
- opérations critiques : mise en attente ;
- aucun crédit ou retrait irréversible non protégé ;
- reprise idempotente ;
- file de secours ;
- alerte interne.

---

# 91. Accessibilité

Les notifications de sécurité et interfaces de réexamen doivent :

- utiliser un langage clair ;
- proposer une action identifiable ;
- fonctionner au clavier ;
- être compatibles lecteur d’écran ;
- éviter les motifs uniquement colorés ;
- être utilisables sur mobile.

---

# 92. Tests du moteur de risque

- signal ;
- pondération ;
- score ;
- règle ;
- simulation ;
- mode observation ;
- décision ;
- version ;
- suspension ;
- idempotence.

---

# 93. Tests des comptes

- connexion inhabituelle ;
- nouvel appareil ;
- MFA ;
- fermeture de session ;
- multi-compte légitime ;
- multi-compte frauduleux ;
- récupération ;
- faux positif.

---

# 94. Tests publicitaires

- heartbeat impossible ;
- accélération ;
- session multiple ;
- ferme de visionnage ;
- hold ;
- rejet ;
- libération ;
- aucun double crédit.

---

# 95. Tests Wallet

- dépôt dupliqué ;
- retrait inhabituel ;
- retenue ;
- libération ;
- capture ;
- compensation ;
- Grand Livre ;
- aucune modification directe.

---

# 96. Tests Fonds, Carte et partenaires

- faux prestataire ;
- opération partenaire dupliquée ;
- cashback abusif ;
- QR rejoué ;
- carte perdue ;
- preuve ;
- remboursement ;
- cluster.

---

# 97. Tests Alertes et Santé

Alertes :

- fausse restitution ;
- urgence réelle non retardée ;
- contact malveillant ;
- preuve.

Santé :

- accès hors périmètre ;
- professionnel non vérifié ;
- accès répété ;
- incident ;
- fermeture immédiate.

---

# 98. Tests Live

- multi-session ;
- audience artificielle ;
- bloc trop rapide ;
- collusion ;
- récompense retenue ;
- créateur suspendu ;
- Live arrêté ;
- blocs validés préservés.

---

# 99. Tests de modération

- signalement ;
- file ;
- décision ;
- sanction ciblée ;
- durée ;
- expiration ;
- restauration ;
- audit ;
- réexamen.

---

# 100. Tests du fondateur

- override ;
- kill switch ;
- libération ;
- remboursement ;
- restauration ;
- audit ;
- impossibilité d’effacer une preuve ;
- impossibilité de modifier une écriture.

---

# 101. Tests responsive

## Utilisateur

- alerte sécurité mobile ;
- retenue ;
- réexamen ;
- appareil ;
- sanction.

## Professionnels

- dossiers desktop ;
- preuve mobile ;
- incident tablette ;
- file d’enquête.

## Administration

- dashboard desktop ;
- dossier ;
- graphe de comptes liés ;
- règles ;
- simulation ;
- mobile urgence.

---

# 102. Captures obligatoires

1. dashboard risque ;
2. file de modération ;
3. dossier de fraude ;
4. signaux ;
5. score ;
6. preuve ;
7. sanction ciblée ;
8. retenue financière ;
9. réexamen utilisateur ;
10. appareils ;
11. cluster de comptes ;
12. fraude publicitaire ;
13. fraude Live ;
14. fraude partenaire ;
15. incident Santé ;
16. kill switch ;
17. override fondateur ;
18. audit.

---

# 103. Critères d’acceptation

Le module est accepté lorsque :

1. les signaux sont normalisés ;
2. les règles sont versionnées ;
3. le mode observation existe ;
4. les décisions sont séparées de la détection ;
5. les sanctions sont ciblées ;
6. les retenues financières sont traçables ;
7. aucun solde n’est modifié directement ;
8. les dossiers d’enquête existent ;
9. les preuves sont protégées ;
10. les multi-comptes ne sont pas automatiquement condamnés ;
11. la fraude publicitaire est détectable ;
12. la fraude Live est détectable ;
13. la fraude Wallet est détectable ;
14. les partenaires et la Carte sont protégés ;
15. Santé et Alertes disposent de contrôles propres ;
16. les utilisateurs peuvent demander un réexamen ;
17. le fondateur peut intervenir ;
18. toute intervention est auditée ;
19. les kill switches existent ;
20. les faux positifs sont mesurés ;
21. les interfaces respectent la doctrine responsive ;
22. les tests critiques passent.

---

# 104. Ordre d’implémentation

## Phase 1 — Socle risque

- signaux ;
- évaluations ;
- décisions ;
- règles ;
- audit.

## Phase 2 — Sécurité des comptes

- appareils ;
- sessions ;
- connexions ;
- MFA ;
- récupération.

## Phase 3 — Wallet

- dépôts ;
- retraits ;
- retenues ;
- compensations.

## Phase 4 — Publicité et Live

- attention ;
- multi-session ;
- automatisation ;
- fermes ;
- collusion.

## Phase 5 — Modération des contenus

- files ;
- signalements ;
- décisions ;
- sanctions.

## Phase 6 — Fonds, Carte et partenaires

- preuves ;
- cashback ;
- QR ;
- prestataires.

## Phase 7 — Alertes et Santé

- accès ;
- restitution ;
- incidents ;
- urgence.

## Phase 8 — Réexamens

- demandes ;
- preuves ;
- décisions ;
- restauration.

## Phase 9 — Incidents et kill switches

- centre d’incidents ;
- actions ;
- reprise ;
- rapports.

## Phase 10 — Stabilisation

- performance ;
- faux positifs ;
- sécurité ;
- responsive ;
- tests ;
- captures.

---

# 105. Première verticale à livrer

```text
Utilisateur ouvre une publicité
→ heartbeats impossibles détectés
→ signal enregistré
→ score élevé
→ récompense placée en attente
→ dossier automatique
→ analyste consulte la preuve
→ tentative rejetée
→ réservation libérée
→ quota traité selon la cause
→ utilisateur notifié
→ audit complet
```

Deuxième verticale :

```text
Retrait inhabituel
→ réservation du montant
→ MFA
→ retenue temporaire
→ vérification
→ retrait autorisé
→ prestataire payé
→ Grand Livre
→ notification
```

Troisième verticale :

```text
Live rémunéré
→ sessions multiples
→ place récompensée suspendue
→ blocs suivants bloqués
→ blocs validés conservés
→ revue
→ décision
→ reporting corrigé
```

---

# 106. Directive pour Claude Code

1. lire toutes les notes de domaine ;
2. auditer le nouveau dépôt ;
3. créer un registre de signaux ;
4. séparer détection, décision et exécution ;
5. versionner les règles ;
6. livrer un mode observation ;
7. ne jamais modifier directement un solde ;
8. créer des retenues financières explicites ;
9. appliquer des sanctions ciblées ;
10. créer les dossiers et preuves ;
11. fournir le réexamen ;
12. intégrer le fondateur et les kill switches ;
13. mesurer les faux positifs ;
14. fournir migrations, API, tests et captures ;
15. ne pas créer de couche doctrinale ou de gouvernance bloquante.

---

# 107. Décision finale

La sécurité Wasplex doit fonctionner ainsi :

```text
détecter
→ mesurer
→ vérifier
→ agir précisément
→ protéger la valeur
→ expliquer l’action
→ permettre le réexamen
→ auditer
```

> **Wasplex ne doit ni laisser passer une fraude évidente ni bloquer aveuglément un utilisateur légitime. Chaque risque doit devenir un signal mesurable, chaque décision une action ciblée, et chaque action une trace vérifiable.**
