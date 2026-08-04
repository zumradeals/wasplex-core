# HOTFIX P003 — CONTRAT GENIUSPAY SANDBOX

**Statut :** `in_progress`  
**Branche :** `codex/hotfix-geniuspay-sandbox-contract`  
**Date d’ouverture :** 4 août 2026  
**Origine :** incident observé sur le Wallet annonceur en production

## 1. Symptôme

Lors de la création d’un dépôt sandbox, Wasplex affiche :

```text
La réponse GeniusPay est incomplète.
```

Le dépôt local est conservé avec le statut `unknown`, sans référence fournisseur ni URL de checkout. L’interface l’affiche alors comme « Référence en préparation » et « À vérifier ».

## 2. Cause racine

L’intégration P003 et ses tests utilisaient un contrat interne qui ne correspond plus au contrat public de l’API Marchand GeniusPay :

- mauvaise base API : `https://pay.genius.ci/api/v1/merchant` au lieu de `https://geniuspay.ci/api/v1/merchant` ;
- domaine de checkout officiel `geniuspay.ci` refusé par l’allowlist ;
- anciens en-têtes `X-GeniusPay-*` au lieu de `X-Webhook-*` ;
- signature calculée sur le payload seul au lieu de `timestamp + "." + payload` ;
- données webhook attendues sous `data.transaction.*` au lieu de `data.*` ;
- environnement attendu sous `data.environment` au lieu de la racine `environment` ;
- timestamp métier interprété comme date ISO alors que le webhook fournit un timestamp Unix et un champ `created_at`.

Les tests étaient cohérents avec les mocks historiques, mais ne prouvaient pas la compatibilité avec le contrat fournisseur réel.

## 3. Correctifs

- utilisation de la base API Marchand documentée ;
- allowlist exacte des domaines GeniusPay de checkout ;
- décodage strict de la réponse de création et de consultation ;
- prise en charge de `expires_at` ;
- journalisation sûre des seuls noms de champs manquants ;
- adoption des en-têtes webhook documentés ;
- vérification HMAC-SHA256 sur `timestamp.payload` ;
- décodage du payload webhook officiel ;
- conservation exclusive des métadonnées Wasplex autorisées ;
- tests fondés sur les exemples documentés de l’API Marchand.

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

## 5. Déploiement requis

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

## 6. Dépôts historiques bloqués

Les tentatives déjà marquées `unknown` sans référence fournisseur ne doivent pas être supprimées. Elles restent des traces d’audit de l’incident. Après validation du correctif, elles pourront être clôturées comme échecs techniques au moyen d’une opération administrative explicite, sans crédit Wallet ni écriture de valeur.

## 7. Critères d’acceptation

1. L’appel compte marchand sandbox répond avec succès.
2. Un nouveau dépôt retourne une référence `MTX-*` et une URL de checkout GeniusPay.
3. La page sandbox s’ouvre dans le navigateur.
4. Un webhook officiel signé est accepté.
5. Un webhook falsifié ou expiré est refusé.
6. Le paiement est revérifié côté serveur avant crédit.
7. Le Wallet n’est crédité qu’une fois.
8. Pint, Larastan, Pest SQLite/PostgreSQL, Prettier, ESLint, TypeScript et Vite restent verts.
