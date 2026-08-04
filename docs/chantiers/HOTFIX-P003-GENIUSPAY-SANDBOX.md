# HOTFIX P003 — CONTRAT GENIUSPAY SANDBOX

**Statut :** `ready_for_review`  
**Branche :** `codex/hotfix-geniuspay-sandbox-contract`  
**Pull Request :** `#11`  
**Commit final :** `8001d1686ab4e1964812691e4c7d7cf875502495`  
**Date d’ouverture :** 4 août 2026  
**Origine :** incident observé sur le Wallet annonceur en production

## 1. Symptôme

Lors de la création d’un dépôt sandbox, Wasplex affiche :

```text
La réponse GeniusPay est incomplète.
```

Le dépôt local est conservé avec le statut `unknown`, sans référence fournisseur ni URL de checkout. L’interface l’affiche alors comme « Référence en préparation » et « À vérifier ».

## 2. Cause racine

L’intégration P003 et ses tests utilisaient un contrat interne qui ne correspond plus au contrat réel de l’API Marchand GeniusPay :

- mauvaise base API : `https://pay.genius.ci/api/v1/merchant` au lieu de `https://geniuspay.ci/api/v1/merchant` ;
- domaine de checkout officiel `geniuspay.ci` refusé par l’allowlist ;
- anciens en-têtes `X-GeniusPay-*` au lieu de `X-Webhook-*` ;
- signature calculée sur le payload seul au lieu de `timestamp + "." + payload` ;
- données webhook attendues sous `data.transaction.*` au lieu de `data.*` ;
- environnement attendu sous `data.environment` au lieu de la racine `environment` ;
- timestamp métier interprété comme date ISO alors que le webhook fournit un timestamp Unix et un champ `created_at` ;
- statut initial supposé obligatoire alors que la création sandbox réelle retourne `status: null` avec une URL de checkout valide.

Les tests étaient cohérents avec les mocks historiques, mais ne prouvaient pas la compatibilité avec le fournisseur réel.

## 3. Correctifs

- utilisation de la base API Marchand correcte ;
- allowlist exacte des domaines GeniusPay de checkout ;
- décodage strict de la réponse de création et de consultation ;
- normalisation de `status: null` en `pending` uniquement lorsqu’une URL de checkout non vide accompagne la réponse réussie ;
- prise en charge de `expires_at` ;
- journalisation sûre des seuls noms de champs manquants ;
- adoption des en-têtes webhook documentés ;
- vérification HMAC-SHA256 sur `timestamp.payload` ;
- décodage du payload webhook officiel ;
- conservation exclusive des métadonnées Wasplex autorisées ;
- tests reproduisant la forme réellement observée sur le VPS.

## 4. Sécurité

Le hotfix conserve les verrous suivants :

- mode `sandbox` obligatoire ;
- refus des clés contenant `live` ;
- HTTPS obligatoire ;
- base API exacte ;
- domaines de checkout explicitement autorisés ;
- aucun crédit issu de la redirection navigateur ;
- crédit uniquement après webhook signé et revérification serveur ;
- idempotence du webhook et du Ledger ;
- aucune donnée client GeniusPay stockée dans l’événement sûr.

## 5. Validation réelle sur le VPS

Le 4 août 2026, les clés présentes en production ont été testées sans être affichées.

### Compte marchand

```text
HTTP                 200
success              true
account_status       active
environment          sandbox
```

### Création de paiement sandbox

```text
HTTP                 201
success              true
id                   14498
reference            SANDBOX_*
amount               1000 XOF
status fournisseur   null
checkout             https://geniuspay.ci/checkout/SANDBOX_*
environment          sandbox
message              Sandbox payment initiated successfully
```

Cette réponse confirme que les identifiants sont valides et que l’échec Wasplex provenait du contrat de décodage. Le statut `null` initial est désormais interprété comme `pending` seulement en présence du checkout sécurisé.

## 6. Validation CI finale

**Commit validé :** `8001d1686ab4e1964812691e4c7d7cf875502495`  
**Workflow :** `ci`  
**Run :** `30906777581`  
**Job :** `91983592027`  
**Conclusion :** `success`

Validations réussies :

- PHP 8.4 ;
- Pint ;
- Larastan niveau 8 ;
- Pest SQLite ;
- Pest PostgreSQL 17 ;
- tests du checkout réel reproduit ;
- tests de webhook signé, falsifié et expiré ;
- tests d’idempotence et de crédit unique ;
- Prettier ;
- ESLint ;
- TypeScript/Vue ;
- build Vite.

## 7. Déploiement requis

La production devra remplacer :

```text
GENIUSPAY_BASE_URL=https://pay.genius.ci/api/v1/merchant
```

par :

```text
GENIUSPAY_BASE_URL=https://geniuspay.ci/api/v1/merchant
GENIUSPAY_CHECKOUT_HOSTS=geniuspay.ci,pay.genius.ci
```

Les clés sandbox et le secret webhook ne doivent jamais être affichés ni enregistrés dans Git.

## 8. Dépôts historiques bloqués

Les tentatives déjà marquées `unknown` sans référence fournisseur ne doivent pas être supprimées. Elles restent des traces d’audit de l’incident. Après déploiement du correctif, elles pourront être clôturées comme échecs techniques au moyen d’une opération administrative explicite, sans crédit Wallet ni écriture de valeur.

## 9. Critères d’acceptation

1. L’appel compte marchand sandbox répond avec succès — **validé**.
2. Un nouveau paiement retourne une référence `SANDBOX_*` et une URL de checkout GeniusPay — **validé**.
3. La réponse initiale `status: null` est normalisée sans masquer une réponse réellement incomplète — **validé par test**.
4. La page sandbox s’ouvre depuis le parcours Wallet — **à vérifier après déploiement**.
5. Un webhook officiel signé est accepté — **validé en CI, à confirmer après paiement sandbox**.
6. Un webhook falsifié ou expiré est refusé — **validé en CI**.
7. Le paiement est revérifié côté serveur avant crédit — **validé en CI**.
8. Le Wallet n’est crédité qu’une fois — **validé en CI**.
9. Toute la chaîne qualité reste verte — **validé**.
