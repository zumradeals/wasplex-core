# P013 — Stabilisation et démonstration de la première verticale

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `5e12f4b` (P012 fusionné)
**Dépendances :** P000 à P012 (toutes fusionnées)
**Spécifications :** `docs/IMPLEMENTATION-ROADMAP-WASPLEX.md` §P013 (lignes 439-454)

## 1. Objectif

Prouver la verticale complète avant d'ouvrir les grands modules suivants (Fonds, Alertes, Santé,
Carte, Live — Phase E).

```text
compte Gold à Cocody
→ annonceur GamaDeals
→ dépôt 100 000 FCFA
→ vidéo
→ devis
→ réservation
→ approbation
→ Matching
→ Feed
→ attention
→ partage 50/50
→ Wallet
→ notification
→ reporting
→ audit
```

## 2. Périmètre

**Inclus :** jeu de données GamaDeals/Orange, parcours E2E rejoué de bout en bout, sécurité,
concurrence, reprise, réseau faible, accessibilité, performance ciblée, captures, documentation
opérateur, plan de rollback, rapport de preuve, correction des défauts trouvés — strictement
limitée à cette verticale.

**Exclus explicitement (par la spécification elle-même) :** Fonds, Alertes, Santé, Carte, Live.

**Réduction supplémentaire assumée pour ce premier passage :**

- Pas de campagne de tests de charge dédiée (outillage k6/Gatling) — la performance ciblée se
  limite à mesurer les temps de réponse réels des points de la verticale sous un scénario normal,
  pas un test de charge en volume.
- « Réseau faible » testé via la simulation réseau de Playwright (throttling), pas une
  infrastructure de test réseau dédiée.
- « Accessibilité » vérifiée sur les écrans de la verticale (attributs ARIA de base, contraste,
  navigation clavier des points d'interaction critiques), pas un audit WCAG complet du produit.

## 3. Méthode

1. Auditer l'état réel : relire chaque rapport P000-P012 pour la liste des limites déjà
   documentées (ne pas retester ce qui est déjà connu et accepté).
2. Rejouer le scénario obligatoire de bout en bout avec un jeu de données réaliste
   (GamaDeals/Orange, Cocody, Gold), en conditions réelles (serveur + Vite + Reverb, navigateur
   réel) — pas seulement via la suite Pest.
3. Vérifier chaque point d'articulation critique :
   - concurrence (double clic, deux workers, rejeu webhook — déjà couvert par les tests
     dédiés de P002/P003/P009/P010/P011 : vérifier qu'ils passent toujours ensemble, pas les
     réécrire) ;
   - reprise après incident (`feed:release-expired-deliveries`, orphelins Grand Livre) ;
   - réseau faible côté Feed (vidéo, heartbeats) ;
   - sécurité (CSRF, capacités, MFA, injection) sur les points d'entrée de la verticale ;
   - accessibilité de base des écrans traversés.
4. Documenter tout défaut trouvé et le corriger — seulement dans le périmètre de la verticale.
5. Captures réelles de chaque étape (utilisateur, annonceur, admin).
6. Rapport de preuve + documentation opérateur (comment rejouer le scénario, comment revenir en
   arrière si besoin) + mise à jour de `docs/ROADMAP-INDEX.md`.

## 4. Critères d'acceptation

Scénario rejouable sur environnement de démonstration, tests critiques verts, aucune divergence
Ledger/Wallet/reporting, captures utilisateur/annonceur/admin, incidents connus documentés.

## 5. Tests

Suite Pest complète (aucune régression), plus toute correction de défaut trouvée accompagnée de
son test. Pas de nouvelle infrastructure de test générique au-delà de ce qui existe déjà.
