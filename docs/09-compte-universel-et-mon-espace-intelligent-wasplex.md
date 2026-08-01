# WASPLEX — COMPTE UNIVERSEL & MON ESPACE INTELLIGENT

**Fichier cible recommandé :** `docs/08-mon-espace/00-compte-universel-et-mon-espace-intelligent-wasplex.md`  
**Statut :** Spécification produit, fonctionnelle et technique prête au codage  
**Position dans la navigation :** cinquième destination principale  
**Navigation officielle :** Feed — Fonds — Wallet — Alertes — Mon Espace  
**Principe central :** une personne possède un compte Wasplex unique, peut accéder à plusieurs espaces autorisés, contrôle ses informations et construit progressivement un profil intelligent utile au matching sans exposer son identité aux annonceurs  
**Dépendances :** Identité, Abonnements, Wallet, Fonds, Alertes, Santé, Matching publicitaire, Feed, future Carte Wasplex  
**Important :** Mon Espace ne doit pas devenir une simple page de paramètres ni un profil public visible par défaut

---

# 1. OBJET DU DOCUMENT

Ce document définit :

- le compte universel Wasplex ;
- l’identité de connexion ;
- les espaces utilisateur, annonceur, partenaire, institutionnel et professionnel ;
- le profil personnel ;
- le profil publicitaire volontaire ;
- les centres d’intérêt intelligents ;
- les projets, besoins, possessions et usages déclarés ;
- les consentements ;
- la confidentialité ;
- la sécurité du compte ;
- la vérification KYC ;
- les abonnements ;
- le Wallet ;
- le Fonds ;
- les Alertes ;
- Santé ;
- la future Carte Wasplex ;
- les représentants ;
- la suppression et la clôture ;
- l’administration ;
- les API, données, événements et tests.

Mon Espace doit permettre à l’utilisateur de comprendre :

```text
Qui je suis dans Wasplex
Ce que j’ai autorisé
Ce que Wasplex sait de moi
Pourquoi une publicité m’est proposée
Quels services sont actifs
Quels accès ont été utilisés
Comment corriger ou retirer mes informations
```

---

# 2. VISION PRODUIT

L’expérience cible est :

```text
Je crée un seul compte Wasplex
→ je sécurise mon identité
→ je choisis mes intérêts
→ j’indique mes projets et besoins
→ Wasplex améliore progressivement la pertinence du Feed
→ je contrôle chaque consentement
→ je peux activer d’autres espaces sans créer plusieurs comptes
```

Mon Espace est le centre de contrôle personnel de l’écosystème.

---

# 3. COMPTE UNIVERSEL

Un compte universel représente une identité de connexion unique.

Il peut donner accès à plusieurs contextes :

```text
Compte Wasplex
├── Espace utilisateur
├── Espace annonceur
├── Espace partenaire
├── Espace institution
├── Espace professionnel Santé
└── Espace administrateur autorisé
```

L’utilisateur ne doit pas créer un nouveau compte séparé pour chaque rôle.

Les droits dépendent :

- des capacités ;
- de l’organisation ;
- de la vérification ;
- du mandat ;
- de la durée ;
- du territoire ;
- de l’authentification.

---

# 4. DIFFÉRENCE ENTRE COMPTE, PROFIL ET ESPACE

## 4.1. Compte

Contient :

- identifiant de connexion ;
- téléphone ou email ;
- mot de passe ou méthode d’authentification ;
- sessions ;
- appareils ;
- statut ;
- sécurité.

## 4.2. Profil personnel

Contient :

- nom ;
- prénom ;
- photo ;
- pays ;
- langue ;
- préférences ;
- informations personnelles autorisées.

## 4.3. Espace

Un espace représente un contexte fonctionnel.

Exemples :

- utilisateur personnel ;
- entreprise annonceur ;
- partenaire commercial ;
- commissariat ;
- clinique ;
- professionnel.

## 4.4. Rôle et capacité

Un rôle regroupe des capacités.

Une capacité autorise une action précise.

Le nom d’un rôle ne doit jamais suffire à autoriser directement une opération critique.

---

# 5. ESPACES INITIAUX

## 5.1. Espace utilisateur

Accessible par défaut.

Fonctions :

- Feed ;
- abonnement ;
- Wallet ;
- Fonds ;
- Alertes ;
- Santé ;
- profil intelligent ;
- consentements ;
- sécurité ;
- future Carte Wasplex.

## 5.2. Espace annonceur

Activation après création ou rattachement à une organisation annonceur.

Fonctions :

- campagnes ;
- ciblage ;
- devis ;
- financement ;
- médias ;
- reporting ;
- équipe.

## 5.3. Espace partenaire

Activation après vérification.

Fonctions :

- offres ;
- points de vente ;
- opérations ;
- commissions ;
- avantages Carte ;
- règlements.

## 5.4. Espace institutionnel

Activation par affiliation vérifiée.

Fonctions :

- Alertes ;
- dossiers reçus ;
- transmissions ;
- équipes ;
- territoires ;
- capacités ;
- audit.

## 5.5. Espace professionnel Santé

Activation après vérification de l’établissement et du professionnel.

Fonctions :

- capsule d’urgence ;
- patients autorisés ;
- accès ;
- consentements ;
- audit.

## 5.6. Espace administration

Accessible uniquement par capacité explicite.

---

# 6. CHANGEMENT D’ESPACE

Le sélecteur d’espace doit afficher :

- espace actuel ;
- organisation ;
- fonction ;
- statut ;
- éventuelles alertes ;
- bouton changer.

Lors du changement :

- contexte de permissions recalculé ;
- navigation adaptée ;
- session conservée ;
- aucune donnée d’un espace ne fuit dans un autre ;
- l’action est journalisée pour les espaces sensibles.

---

# 7. CRÉATION DU COMPTE

Parcours initial :

```text
Téléphone ou email
→ code de vérification
→ mot de passe ou méthode sécurisée
→ pays
→ langue
→ acceptation des conditions essentielles
→ création
→ onboarding
```

Le parcours doit rester court.

Les informations non nécessaires ne doivent pas bloquer la création.

---

# 8. IDENTIFIANTS DE CONNEXION

Méthodes initiales possibles :

- téléphone ;
- email ;
- mot de passe ;
- code à usage unique ;
- authentification biométrique locale ;
- passkey future.

Règles :

- téléphone et email vérifiables ;
- récupération sécurisée ;
- détection de doublons ;
- journal des changements ;
- délai de sécurité pour modification sensible.

---

# 9. STATUTS DU COMPTE

```text
pending_verification
active
restricted
suspended
locked
closed
deceased_or_unavailable
under_review
```

Un compte suspendu ne doit pas perdre automatiquement ses droits financiers légitimes.

Les opérations sensibles peuvent être restreintes séparément.

---

# 10. PROFIL PERSONNEL MINIMAL

Champs possibles :

- nom ;
- prénom ;
- date de naissance ;
- sexe si réellement utile ;
- téléphone ;
- email ;
- pays ;
- ville ;
- langue ;
- photo ;
- fuseau horaire ;
- nom d’affichage.

Toutes les informations ne sont pas obligatoires.

---

# 11. PROFIL PUBLIC ET PROFIL PRIVÉ

Le profil est privé par défaut.

L’utilisateur choisit les éléments publics autorisés :

- nom d’affichage ;
- photo ;
- bio ;
- ville approximative ;
- activité publique ;
- contenus publiés.

Ne jamais rendre publics automatiquement :

- date de naissance complète ;
- téléphone ;
- email ;
- adresse ;
- KYC ;
- Wallet ;
- abonnement ;
- Santé ;
- Fonds ;
- Alertes sensibles ;
- réseau téléphonique ;
- projets d’achat.

---

# 12. MON ESPACE INTELLIGENT

Mon Espace intelligent permet à l’utilisateur de déclarer volontairement :

```text
Ce que je possède
Ce que j’utilise
Ce qui m’intéresse
Ce que je veux acheter
Ce dont j’ai besoin
Mes projets
Mon métier
Mes compétences
Mes zones utiles
Les publicités que j’accepte
```

Ces informations alimentent le matching publicitaire protégé.

---

# 13. CATÉGORIES DU PROFIL INTELLIGENT

## 13.1. Possessions

Exemples :

- smartphone ;
- véhicule ;
- moto ;
- ordinateur ;
- équipement professionnel ;
- logement.

## 13.2. Usages

Exemples :

- réseau Orange ;
- réseau MTN ;
- banque mobile ;
- transport ;
- Internet fixe ;
- logiciels utilisés.

## 13.3. Centres d’intérêt

Exemples :

- automobile ;
- télécommunications ;
- agriculture ;
- immobilier ;
- formation ;
- mode ;
- technologie ;
- santé préventive non sensible.

## 13.4. Projets

Exemples :

- acheter un téléphone ;
- changer de véhicule ;
- construire ;
- suivre une formation ;
- lancer une entreprise ;
- voyager.

## 13.5. Besoins

Exemples :

- forfait Internet ;
- assurance ;
- matériel ;
- financement commercial non sensible ;
- emploi ;
- prestataire.

## 13.6. Territoires

- résidence ;
- travail ;
- commerce ;
- zones d’intérêt ;
- déplacement fréquent déclaré.

## 13.7. Situation professionnelle

- secteur ;
- métier ;
- niveau d’expérience ;
- type d’activité ;
- compétences.

---

# 14. QUESTIONS INTELLIGENTES

Mon Espace peut poser progressivement des questions courtes.

Exemple :

```text
Quel réseau utilisez-vous principalement ?
[Orange] [MTN] [Moov Africa] [Autre]
```

Chaque carte doit afficher :

- question ;
- choix ;
- caractère facultatif ;
- pourquoi cette information est utile ;
- comment la modifier ;
- si elle influence la publicité ;
- niveau de confidentialité.

---

# 15. CONTEXTE ET MOMENT DES QUESTIONS

Les questions peuvent apparaître :

- pendant l’onboarding ;
- dans Mon Espace ;
- après une action pertinente ;
- sous forme de rappel non intrusif ;
- lors d’une correction de profil.

Ne pas poser :

- trop de questions à la fois ;
- une question sensible sans finalité ;
- une question sans explication ;
- une question déjà répondue récemment.

---

# 16. SCORE DE COMPLÉTUDE

Mon Espace peut afficher une progression :

```text
Profil publicitaire complété à 65 %
```

Le score :

- encourage sans punir ;
- n’empêche pas les fonctions fondamentales ;
- explique les catégories manquantes ;
- ne garantit aucun gain ;
- ne doit pas devenir un score social.

---

# 17. SOURCES DES INFORMATIONS

Chaque fait possède une provenance :

```text
declared_by_user
confirmed_by_user
derived_from_allowed_activity
verified_by_partner
verified_by_institution
expired
contested
```

Une donnée déduite n’est jamais présentée comme vérité certaine.

---

# 18. FRAÎCHEUR DES INFORMATIONS

Chaque réponse peut posséder :

- date de création ;
- date de confirmation ;
- date d’expiration ;
- rappel de mise à jour.

Exemple :

> **Utilisez-vous toujours Orange comme réseau principal ?**

Le moteur de matching peut réduire le poids d’une information ancienne.

---

# 19. CORRECTION ET CONTESTATION

L’utilisateur peut :

- modifier une réponse ;
- supprimer une donnée facultative ;
- contester une déduction ;
- demander une explication ;
- désactiver son utilisation publicitaire.

La correction produit une nouvelle version.

---

# 20. DONNÉES DÉDUITES

Wasplex peut calculer :

- intérêt probable ;
- préférence de format ;
- fréquence optimale ;
- fatigue publicitaire ;
- score de pertinence.

Ces données doivent être :

- explicables ;
- limitées ;
- contestables ;
- séparées des décisions sensibles ;
- jamais vendues nominativement.

---

# 21. DONNÉES INTERDITES AU PROFIL PUBLICITAIRE

Ne jamais alimenter automatiquement le profil commercial avec :

- KYC ;
- Santé ;
- SOS ;
- dossiers policiers ;
- Alertes sensibles ;
- vulnérabilité ;
- dette ;
- difficulté Fonds ;
- pauvreté ;
- religion ;
- politique ;
- origine ethnique ;
- orientation sexuelle ;
- grossesse supposée ;
- historique judiciaire ;
- données de mineur non autorisées.

---

# 22. CONSENTEMENTS

Centre de consentement séparé.

Catégories :

- personnalisation publicitaire ;
- utilisation du profil volontaire ;
- localisation approximative ;
- localisation précise ;
- notifications ;
- communications promotionnelles ;
- partenaires ;
- institution déterminée ;
- Santé ;
- programmes facultatifs.

---

# 23. PREUVE DU CONSENTEMENT

Chaque consentement conserve :

- utilisateur ;
- finalité ;
- version du texte ;
- statut ;
- date ;
- canal ;
- retrait ;
- historique.

États :

```text
granted
denied
withdrawn
expired
superseded
```

---

# 24. RETRAIT DU CONSENTEMENT

Le retrait doit :

- être accessible ;
- être enregistré ;
- bloquer les nouveaux usages ;
- propager aux systèmes ;
- retirer l’utilisateur des segments futurs ;
- expliquer les conséquences.

Il ne supprime pas automatiquement les opérations déjà exécutées légalement.

---

# 25. EXPLICATION « POURQUOI CETTE PUBLICITÉ ? »

Mon Espace doit centraliser les explications reçues depuis le Feed.

Exemple :

```text
Cette campagne vous a été proposée parce que :
- vous avez déclaré utiliser Orange ;
- Cocody fait partie de vos zones utiles ;
- votre classe Gold était ciblée ;
- vous avez autorisé la personnalisation.
```

Actions :

- corriger ;
- retirer un critère ;
- masquer l’annonceur ;
- retirer le consentement.

---

# 26. ABONNEMENT

Mon Espace affiche :

- plan actuel ;
- classe économique ;
- prix ;
- date de début ;
- renouvellement ;
- quota ;
- quota consommé ;
- quota restant ;
- avantages ;
- accès Fonds ;
- bouton changer.

Configuration initiale :

| Plan | Quota |
|---|---:|
| Gratuit | 120 |
| Premium | 300 |
| Gold | 600 |
| Platine | 900 |

---

# 27. CHANGEMENT D’ABONNEMENT

Surclassement :

- effet après paiement ;
- quota recalculé ;
- gains passés inchangés ;
- nouvelle classe pour les futurs matchings.

Déclassement :

- prochain cycle par défaut ;
- historique conservé ;
- Wallet non bloqué.

---

# 28. WALLET DANS MON ESPACE

Résumé seulement :

- disponible ;
- réservé ;
- en attente ;
- Fonds ;
- dernière opération ;
- accès au Wallet complet.

Mon Espace ne reproduit pas toutes les fonctions du Wallet.

---

# 29. FONDS DANS MON ESPACE

Afficher :

- éligibilité ;
- abonnement requis ;
- adhésion ;
- mandat ;
- Solde Fonds ;
- vœux ;
- contributions ;
- régularisation ;
- statut.

Actions :

- compléter profil ;
- alimenter Fonds ;
- consulter programme ;
- gérer mandat.

---

# 30. ALERTES DANS MON ESPACE

Afficher :

- déclarations actives ;
- alertes suivies ;
- correspondances ;
- restitutions ;
- statuts institutionnels ;
- visibilité renforcée ;
- historique.

Les données sensibles restent dans le module Alertes.

---

# 31. SANTÉ DANS MON ESPACE

Afficher :

- accès Santé ;
- capsule d’urgence ;
- représentants ;
- consentements ;
- accès récents ;
- dernière vérification.

Mon Espace ne copie pas le dossier Santé.

---

# 32. CARTE WASPLEX

Réserver une section :

- carte virtuelle ;
- QR ;
- état ;
- support physique ;
- partenaires ;
- avantages ;
- suspension ;
- renouvellement.

La note Carte détaillera les fonctions finales.

---

# 33. IDENTITÉ VÉRIFIÉE ET KYC

Niveaux possibles :

```text
KYC_0 — compte de base
KYC_1 — téléphone/email vérifié
KYC_2 — identité vérifiée
KYC_3 — contrôle renforcé
```

Le nom et les niveaux sont configurables.

Le KYC peut être requis pour :

- retraits ;
- Fonds ;
- alertes sensibles ;
- institution ;
- partenaire ;
- professionnel Santé ;
- plafonds élevés.

---

# 34. DONNÉES KYC

Peuvent comprendre :

- pièce d’identité ;
- photo ;
- vidéo ;
- justificatif ;
- mandat ;
- document professionnel ;
- représentant.

Règles :

- séparées du profil publicitaire ;
- accès restreint ;
- chiffrement ;
- durée ;
- audit ;
- aucun affichage public.

---

# 35. PARCOURS KYC

```text
Choix du niveau
→ informations
→ capture
→ contrôle
→ revue éventuelle
→ décision
→ statut
```

États :

```text
not_started
in_progress
submitted
under_review
verified
rejected
expired
revoked
```

---

# 36. REPRÉSENTANTS

Le compte peut être lié à :

- représentant légal ;
- mandataire ;
- responsable d’organisation ;
- tuteur ;
- représentant professionnel.

Chaque relation possède :

- preuve ;
- périmètre ;
- durée ;
- statut ;
- capacités ;
- révocation.

---

# 37. MINEURS

Protection renforcée :

- âge vérifié selon besoin ;
- fonctions limitées ;
- représentant ;
- ciblage restreint ;
- consentements adaptés ;
- aucune exposition publique par défaut ;
- retrait simplifié.

---

# 38. SÉCURITÉ DU COMPTE

Centre de sécurité :

- mot de passe ;
- MFA ;
- appareils ;
- sessions ;
- connexions ;
- passkeys futures ;
- récupération ;
- codes de secours ;
- alertes de sécurité.

---

# 39. APPAREILS ET SESSIONS

Afficher :

- appareil ;
- localisation approximative ;
- date ;
- dernière activité ;
- session actuelle ;
- bouton déconnecter.

L’utilisateur peut fermer une session distante.

---

# 40. AUTHENTIFICATION RENFORCÉE

Requise pour :

- retrait ;
- changement de téléphone ;
- changement d’email ;
- KYC ;
- espace institutionnel ;
- Santé d’urgence ;
- correction financière ;
- fermeture du compte.

---

# 41. RÉCUPÉRATION DU COMPTE

Parcours sécurisé :

- identifiant ;
- preuve ;
- canal ;
- délai ;
- contrôle de risque ;
- validation.

Éviter une récupération basée sur une seule donnée facilement volée.

---

# 42. NOTIFICATIONS

Préférences séparées :

- Wallet ;
- publicité ;
- Alertes ;
- Santé ;
- Fonds ;
- sécurité ;
- abonnements ;
- partenaires ;
- marketing.

Canaux :

- push ;
- email ;
- SMS ;
- in-app.

---

# 43. LANGUE ET TERRITOIRE

Mon Espace gère :

- langue ;
- pays ;
- ville ;
- fuseau ;
- devise ;
- formats ;
- numéros officiels ;
- règles locales.

Le changement de pays peut nécessiter :

- vérification ;
- recalcul ;
- changement de cycle ;
- migration contrôlée.

---

# 44. PRÉFÉRENCES FEED

- son ;
- autoplay ;
- sous-titres ;
- mode économie ;
- animations ;
- vibration ;
- catégories masquées ;
- annonceurs masqués ;
- fréquence perçue.

---

# 45. PRÉFÉRENCES D’ACCESSIBILITÉ

- taille ;
- contraste ;
- lecteur d’écran ;
- réduction des animations ;
- vibration ;
- sous-titres ;
- simplification visuelle.

---

# 46. HISTORIQUE D’ACTIVITÉ

Mon Espace peut présenter :

- connexions ;
- consentements ;
- changements de profil ;
- changement de plan ;
- accès institutionnels communicables ;
- accès Santé ;
- actions de sécurité.

Ne pas exposer les journaux techniques complets.

---

# 47. EXPORT DES DONNÉES

L’utilisateur peut demander :

- profil ;
- consentements ;
- opérations ;
- données publicitaires volontaires ;
- historiques communicables.

États :

```text
requested
preparing
ready
expired
failed
```

L’export doit être protégé et temporaire.

---

# 48. SUPPRESSION DE DONNÉES FACULTATIVES

L’utilisateur peut supprimer :

- centres d’intérêt ;
- projets ;
- besoins ;
- possessions ;
- préférences ;
- photo ;
- bio.

La suppression doit se propager aux nouveaux matchings.

---

# 49. CLÔTURE DU COMPTE

Parcours :

```text
Demande
→ explication
→ vérification
→ contrôle des opérations ouvertes
→ délai de sécurité
→ clôture
```

Avant clôture :

- retraits en cours ;
- litiges ;
- Fonds ;
- Alertes ;
- abonnements ;
- obligations institutionnelles ;
- données légales.

---

# 50. DONNÉES CONSERVÉES APRÈS CLÔTURE

Peuvent être conservées lorsqu’elles sont nécessaires à :

- comptabilité ;
- paiements ;
- fraude ;
- litige ;
- justice ;
- audit ;
- sécurité.

Elles deviennent :

- limitées ;
- archivées ;
- non commerciales ;
- supprimées à terme selon politique.

---

# 51. COMPTE D’ORGANISATION

Une organisation possède :

- identité légale ;
- type ;
- pays ;
- représentant ;
- statut ;
- documents ;
- équipes ;
- espaces ;
- contrats ;
- audit.

Types :

```text
advertiser
partner
institution
healthcare
administration
```

---

# 52. MEMBRES D’ORGANISATION

Chaque membre possède :

- compte personnel ;
- organisation ;
- fonction ;
- capacités ;
- territoire ;
- date ;
- expiration ;
- statut.

Aucun compte partagé générique.

---

# 53. INVITATIONS

Parcours :

```text
Organisation invite
→ destinataire vérifie
→ accepte
→ capacités accordées
→ journal
```

Une invitation expire.

---

# 54. DÉLÉGATION DE CAPACITÉS

Les capacités peuvent être :

- accordées ;
- limitées ;
- suspendues ;
- expirées ;
- révoquées.

Elles ne sont pas héritées silencieusement.

---

# 55. ADMINISTRATION DE MON ESPACE

Dashboard :

- comptes ;
- vérifications ;
- consentements ;
- organisations ;
- invitations ;
- espaces ;
- suspensions ;
- demandes d’export ;
- clôtures ;
- incidents ;
- profils publicitaires.

---

# 56. ADMINISTRATION DU PROFIL INTELLIGENT

Configurer :

- catégories ;
- questions ;
- options ;
- taxonomies ;
- finalités ;
- durée ;
- fraîcheur ;
- sensibilité ;
- consentement requis ;
- ordre ;
- pays ;
- état.

Chaque modification est versionnée.

---

# 57. MODÈLE DE DONNÉES

Entités recommandées :

```text
accounts
account_identifiers
account_sessions
account_devices
account_security_events
personal_profiles
profile_visibility_settings
user_spaces
space_memberships
organizations
organization_memberships
organization_invitations
capability_grants

smart_profile_categories
smart_profile_questions
smart_profile_options
smart_profile_answers
smart_profile_facts
smart_profile_fact_versions
smart_profile_explanations
smart_profile_completion_scores

consent_purposes
consent_text_versions
user_consents
consent_events

kyc_profiles
kyc_submissions
kyc_documents
kyc_reviews
representative_relationships

user_preferences
notification_preferences
data_export_requests
account_closure_requests
account_audit_events
```

---

# 58. CHAMPS — ACCOUNT

```text
id
status
country_code
language
timezone
created_at
verified_at
restricted_at
closed_at
```

---

# 59. CHAMPS — USER SPACE

```text
id
account_id
space_type
organization_id
status
default_space
created_at
```

---

# 60. CHAMPS — SMART PROFILE FACT

```text
id
account_id
taxonomy_code
value
source
confidence_level
status
declared_at
confirmed_at
expires_at
consent_reference
```

---

# 61. CHAMPS — USER CONSENT

```text
id
account_id
purpose_code
text_version_id
status
granted_at
withdrawn_at
channel
```

---

# 62. API COMPTE

```text
GET    /api/me
PATCH  /api/me
GET    /api/me/spaces
POST   /api/me/spaces/{id}/switch

GET    /api/me/security
GET    /api/me/sessions
DELETE /api/me/sessions/{id}
POST   /api/me/mfa
POST   /api/me/recovery
```

---

# 63. API MON ESPACE INTELLIGENT

```text
GET    /api/me/smart-profile
GET    /api/me/smart-profile/questions
POST   /api/me/smart-profile/answers
PATCH  /api/me/smart-profile/answers/{id}
DELETE /api/me/smart-profile/answers/{id}
GET    /api/me/smart-profile/completion
GET    /api/me/smart-profile/explanations
POST   /api/me/smart-profile/facts/{id}/contest
```

---

# 64. API CONSENTEMENTS

```text
GET    /api/me/consents
POST   /api/me/consents/{purpose}/grant
POST   /api/me/consents/{purpose}/withdraw
GET    /api/me/consents/history
```

---

# 65. API KYC

```text
GET    /api/me/kyc
POST   /api/me/kyc/submissions
POST   /api/me/kyc/submissions/{id}/documents
GET    /api/me/kyc/submissions/{id}
```

---

# 66. API ORGANISATIONS

```text
GET    /api/organizations
POST   /api/organizations
GET    /api/organizations/{id}
PATCH  /api/organizations/{id}

GET    /api/organizations/{id}/members
POST   /api/organizations/{id}/invitations
POST   /api/organizations/{id}/members/{member}/capabilities
DELETE /api/organizations/{id}/members/{member}
```

---

# 67. API EXPORT ET CLÔTURE

```text
POST   /api/me/data-exports
GET    /api/me/data-exports/{id}
POST   /api/me/account-closure
GET    /api/me/account-closure
POST   /api/me/account-closure/cancel
```

---

# 68. API ADMINISTRATION

```text
GET    /api/admin/accounts
GET    /api/admin/accounts/{id}
POST   /api/admin/accounts/{id}/restrict
POST   /api/admin/accounts/{id}/suspend
POST   /api/admin/accounts/{id}/restore

GET    /api/admin/smart-profile/questions
POST   /api/admin/smart-profile/questions
PATCH  /api/admin/smart-profile/questions/{id}
POST   /api/admin/smart-profile/questions/{id}/publish

GET    /api/admin/consents/purposes
POST   /api/admin/consents/purposes
POST   /api/admin/consents/purposes/{id}/publish

GET    /api/admin/kyc/reviews
POST   /api/admin/kyc/reviews/{id}/approve
POST   /api/admin/kyc/reviews/{id}/reject

GET    /api/admin/organizations
POST   /api/admin/organizations/{id}/verify
POST   /api/admin/organizations/{id}/suspend
```

---

# 69. ÉVÉNEMENTS MÉTIER

```text
AccountCreated
AccountVerified
AccountRestricted
AccountSuspended
AccountClosed

UserSpaceCreated
UserSpaceSwitched
OrganizationCreated
OrganizationVerified
OrganizationMemberInvited
CapabilityGranted
CapabilityRevoked

SmartProfileAnswerCreated
SmartProfileAnswerUpdated
SmartProfileFactConfirmed
SmartProfileFactExpired
SmartProfileFactContested
SmartProfileCompletionChanged

ConsentGranted
ConsentWithdrawn
ConsentExpired

KycSubmitted
KycVerified
KycRejected
KycExpired

DataExportRequested
DataExportReady
AccountClosureRequested
AccountClosureCompleted
```

---

# 70. NOTIFICATIONS

- compte vérifié ;
- nouvelle connexion ;
- appareil inconnu ;
- changement de téléphone ;
- consentement modifié ;
- KYC ;
- invitation ;
- capacité ;
- abonnement ;
- quota ;
- export ;
- clôture.

---

# 71. CONFIDENTIALITÉ PAR DÉFAUT

Règles :

- profil privé ;
- données facultatives non publiques ;
- espaces séparés ;
- annonceurs sans accès nominatif ;
- organisations limitées ;
- consentements spécifiques ;
- journal d’accès ;
- aucun enrichissement commercial depuis Santé/Alertes/Fonds.

---

# 72. SÉCURITÉ

- authentification ;
- MFA ;
- sessions ;
- appareils ;
- rate limiting ;
- chiffrement ;
- contrôle d’accès ;
- audit ;
- anti-usurpation ;
- récupération ;
- séparation KYC ;
- fichiers signés ;
- expiration des liens.

---

# 73. PERFORMANCE

- profil agrégé ;
- cache des questions ;
- taxonomies ;
- pagination ;
- index ;
- chargement progressif ;
- aucune récupération de toutes les réponses à chaque ouverture ;
- synchronisation différée pour éléments non critiques.

---

# 74. TESTS COMPTE

- création ;
- vérification ;
- doublon ;
- récupération ;
- session ;
- appareil ;
- suspension ;
- clôture ;
- restauration autorisée ;
- espace par défaut.

---

# 75. TESTS ESPACES

- changement utilisateur → annonceur ;
- permissions recalculées ;
- organisation suspendue ;
- invitation expirée ;
- capacité révoquée ;
- aucune fuite inter-espace ;
- aucun compte générique.

---

# 76. TESTS PROFIL INTELLIGENT

- Orange ;
- Cocody ;
- possession vs intérêt ;
- projet ;
- modification ;
- suppression ;
- expiration ;
- contestation ;
- score ;
- aucune punition ;
- matching mis à jour.

---

# 77. TESTS CONSENTEMENT

- accord ;
- refus ;
- retrait ;
- version ;
- propagation ;
- nouvelle campagne exclue ;
- historique ;
- aucune donnée Santé dans Advertising.

---

# 78. TESTS KYC

- soumission ;
- document ;
- revue ;
- validation ;
- rejet ;
- expiration ;
- accès restreint ;
- aucune utilisation publicitaire.

---

# 79. TESTS ABONNEMENT ET SERVICES

- plan affiché ;
- quota ;
- upgrade ;
- downgrade ;
- Fonds éligible ;
- Wallet accessible ;
- expiration sans perte de WP ;
- Alertes visibles ;
- Santé séparée.

---

# 80. TESTS SÉCURITÉ

- autre compte ;
- session volée ;
- changement sensible sans MFA ;
- export non autorisé ;
- clôture avec retrait en cours ;
- document KYC public refusé ;
- organisation non vérifiée ;
- capacité falsifiée.

---

# 81. TESTS VISUELS

Captures minimales :

1. accueil Mon Espace ;
2. carte du profil ;
3. questions intelligentes ;
4. score de complétude ;
5. centres d’intérêt ;
6. projets ;
7. consentements ;
8. abonnement ;
9. Wallet résumé ;
10. Fonds résumé ;
11. Alertes résumé ;
12. Santé résumé ;
13. sécurité ;
14. appareils ;
15. changement d’espace ;
16. organisation ;
17. KYC ;
18. export ;
19. clôture ;
20. mobile 320/360/390.

---

# 82. STRUCTURE D’ÉCRAN RECOMMANDÉE

```text
Mon Espace
├── En-tête personnel
├── Carte abonnement
├── Progression profil intelligent
├── Mes intérêts
├── Mes projets et besoins
├── Mes services
│   ├── Wallet
│   ├── Fonds
│   ├── Alertes
│   ├── Santé
│   └── Carte Wasplex
├── Consentements
├── Sécurité
├── Préférences
├── Espaces professionnels
└── Données et clôture
```

---

# 83. ONBOARDING RECOMMANDÉ

## Étape 1

Compte minimal.

## Étape 2

Pays et langue.

## Étape 3

Plan ou Gratuit.

## Étape 4

Consentement publicitaire.

## Étape 5

Trois à cinq questions intelligentes maximum.

## Étape 6

Accès au Feed.

Le reste est complété progressivement.

---

# 84. CRITÈRES D’ACCEPTATION

Le module est accepté lorsque :

1. un seul compte peut avoir plusieurs espaces ;
2. les espaces sont séparés ;
3. l’utilisateur contrôle son profil ;
4. Mon Espace alimente le matching ;
5. les questions sont volontaires ;
6. les réponses sont explicables ;
7. les consentements sont séparés ;
8. le retrait est appliqué ;
9. KYC est séparé d’Advertising ;
10. Santé, Alertes et Fonds n’enrichissent pas le ciblage ;
11. l’abonnement et le quota sont visibles ;
12. le Wallet est résumé ;
13. le Fonds est résumé ;
14. Alertes et Santé sont accessibles ;
15. Carte Wasplex est réservée ;
16. les organisations disposent de membres nominatifs ;
17. les capacités sont explicites ;
18. la sécurité du compte est complète ;
19. l’export existe ;
20. la clôture est contrôlée ;
21. les tests critiques passent.

---

# 85. ORDRE D’IMPLÉMENTATION

## Phase 1 — Compte et profil minimal

- compte ;
- identifiants ;
- sessions ;
- profil ;
- pays ;
- langue.

## Phase 2 — Espaces et organisations

- espaces ;
- sélecteur ;
- organisations ;
- membres ;
- capacités.

## Phase 3 — Mon Espace utilisateur

- écran ;
- services ;
- abonnement ;
- sécurité ;
- préférences.

## Phase 4 — Profil intelligent

- taxonomies ;
- questions ;
- réponses ;
- provenance ;
- fraîcheur ;
- score.

## Phase 5 — Consentements

- finalités ;
- versions ;
- accord ;
- retrait ;
- propagation.

## Phase 6 — KYC

- soumission ;
- revue ;
- statut ;
- capacités.

## Phase 7 — Intégrations

- Matching ;
- Feed ;
- Wallet ;
- Fonds ;
- Alertes ;
- Santé.

## Phase 8 — Données personnelles

- export ;
- suppression facultative ;
- clôture ;
- archivage.

## Phase 9 — Administration

- questions ;
- consentements ;
- comptes ;
- organisations ;
- audit.

## Phase 10 — Stabilisation

- sécurité ;
- performance ;
- accessibilité ;
- tests ;
- captures.

---

# 86. PREMIÈRE VERTICALE À LIVRER

```text
Utilisateur crée son compte
→ choisit Gold
→ accepte la personnalisation
→ indique Orange
→ indique Cocody
→ indique un projet d’achat de téléphone
→ Feed reçoit une campagne compatible
→ « Pourquoi cette publicité ? » explique le matching
→ utilisateur corrige une réponse
→ les futurs matchings changent
```

Cette verticale doit démontrer que Mon Espace influence réellement le Feed sans exposer l’utilisateur.

---

# 87. DIRECTIVE POUR CLAUDE CODE

1. lire les notes Feed, Matching, Abonnements, Wallet, Fonds, Alertes et Santé ;
2. auditer le nouveau dépôt ;
3. séparer compte, profil, espace, organisation et capacité ;
4. ne pas créer plusieurs comptes pour une personne ;
5. coder la confidentialité par défaut ;
6. construire les taxonomies avant les questions ;
7. versionner les réponses et consentements ;
8. brancher le matching par contrat ;
9. ne jamais copier les données Santé, Alertes ou Fonds dans Advertising ;
10. fournir les migrations ;
11. fournir les tests ;
12. produire les captures.

---

# 88. DÉCISION FINALE

Mon Espace doit devenir l’endroit où l’utilisateur comprend, construit et contrôle sa relation avec Wasplex.

Le principe final est :

```text
Un compte
→ plusieurs espaces autorisés
→ un profil personnel protégé
→ un profil intelligent volontaire
→ des consentements séparés
→ un matching plus pertinent
→ aucune identité remise aux annonceurs
```

> **Wasplex peut devenir intelligent sur les intérêts, projets et besoins de l’utilisateur uniquement avec une finalité claire, un contrôle réel et une séparation stricte entre profil commercial, identité, Santé, Alertes, Fonds et KYC.**
