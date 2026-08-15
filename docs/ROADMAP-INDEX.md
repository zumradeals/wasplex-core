# WASPLEX — INDEX DES CHANTIERS

**Roadmap :** [IMPLEMENTATION-ROADMAP-WASPLEX.md](./IMPLEMENTATION-ROADMAP-WASPLEX.md)

**Réinitialisation du 2026-08-05 :** le code applicatif précédent (`apps/platform`, CI, infra) a été retiré du dépôt sur décision du fondateur pour une reconstruction intégrale à partir du corpus `docs/`, traité comme jamais codé. L'historique complet reste consultable au commit `aa6cb1a60c2e43308066fb267cad76b38d689e1c` de la branche `claude/wasplex-reconstruction-7ujym7`. Les statuts ci-dessous sont réinitialisés en conséquence ; les rapports historiques dans `docs/chantiers/` restent conservés comme trace d'audit mais ne décrivent plus l'état réel du dépôt.

| Chantier | Titre | Dépendances | Phase | Statut actuel |
|---|---|---|---|---|
| P000 | Socle du dépôt et stack | — | Noyau | ready_for_review |
| P001 | Compte, espaces, capacités et shells | P000 | Noyau | ready_for_review |
| P002 | Grand Livre minimal | P001 | Noyau | ready_for_review |
| P003 | Wallet annonceur (dépôt GeniusPay) | P002 | Noyau | ready_for_review |
| P004 | Configurations, plans et classes | P001, P003 | Noyau | ready_for_review |
| P005 | Studio Annonceur, marques et financement | P001, P003, P004 | Annonceur | ready_for_review |
| P006 | Campagne, audience, devis et budget | P004, P005 | Annonceur | ready_for_review |
| P007 | Revue administrative | P006 | Annonceur | ready_for_review |
| P008 | SmartProfile, consentements et Matching | P004, P007 | Distribution | ready_for_review |
| P009 | Super Moteur, Feed, attention et crédit automatique | P002-P008 | Distribution et valeur | ready_for_review |
| P010 | Antifraude, preuves renforcées et reprise | P009 | Valeur et confiance | ready_for_review |
| P011 | Temps réel, rapprochement et retraits utilisateur | P003, P009, P010 | Valeur | ready_for_review (temps réel P011 + rapprochement P011-B ; retraits en P011-C, attend un chantier KYC) |
| P012 | Reporting et dashboards | P007, P009, P011 | Pilotage | ready_for_review (première verticale — reporting Studio + dashboard fondateur) |
| P013 | Stabilisation première verticale | P000-P012 | Stabilisation | ready_for_review (verticale rejouée en conditions réelles — dataset GamaDeals/Orange, durcissement, captures, guide opérateur) |
| P014 | Fonds | P003, P004, P011, P012, P019 | Extension | in_progress (P014-A : programmes, adhésion, mandat, grâce, catégories, vœux et revue admin) |
| P015 | Alertes | P001, P009, P011, P012, P019 | Extension | En attente |
| P016 | Santé | P001, P012, P015, P019 | Extension | En attente |
| P017 | Carte et partenaires | P003, P011, P012, P019 | Extension | ready_for_review (P017-A identité/QR + P017-B paiement QR validés en production ; P017-B.1 clôture ; avantages partenaires conservés en roadmap) |
| P018 | Live | P006-P012, P020 | Extension | in_progress (P018-A fondation sans WP ; P018-A.1 création/pilotage exclusivement Studio annonceur, espace membre spectateur ; transport média externe différé) |
| P019 | Espaces professionnels/institutionnels | P001, P012 | Extension | En attente |
| P020 | Communication, modération et risques | P001, P011, P012 | Extension | En attente |
| P021 | Intégrations et production | modules stabilisés | Production | En attente |

## Décision produit du 2026-08-15 — Carte Wasplex

Le rôle produit prioritaire de la Carte Wasplex est l'identification du membre et l'accès à des **réductions, offres et avantages chez les partenaires Wasplex**. Le paiement QR P017-B reste une capacité secondaire déjà livrée ; il n'est pas le cœur de Wasplex et ne doit pas bloquer les chantiers prioritaires.

La suite fonctionnelle Carte (réseau partenaires, validation d'avantages, cashback éventuel, Carte Pro/Marchand, carte physique/NFC, remboursements et durcissements avancés) est documentée dans [`chantiers/P017-ROADMAP.md`](./chantiers/P017-ROADMAP.md) et n'est pas prioritaire à ce stade.

## Décision d'ordre du 2026-08-15 — démarrage P018-A

P018-A peut démarrer avant l'achèvement complet de P020 à condition de rester strictement limité au **Live standard non rémunéré**, sans sponsorisation, sans crédit Wallet, sans commentaires publics et sans mécanismes de valeur. Le contrôle d'accès, l'audit et les transitions de cycle de vie sont inclus dès P018-A ; la modération sociale avancée demeure une dépendance des lots Live ultérieurs.

## Décision produit du 2026-08-15 — P018-A.1 Live Annonceur

La création, la programmation et le pilotage d'un Live Wasplex sont réservés au **Studio annonceur** dans un espace annonceur actif. L'espace membre et le bouton Live du Feed sont une surface spectateur uniquement. Les Lives portent désormais leur organisation annonceur afin d'éviter tout mélange entre plusieurs espaces annonceurs d'un même compte. Cette décision est détaillée dans [`chantiers/P018-A1-LIVE-ANNONCEUR.md`](./chantiers/P018-A1-LIVE-ANNONCEUR.md).

## Décision fondatrice P009

La première verticale publicitaire ne sépare plus artificiellement le Feed, l’attention et le premier crédit Wallet en trois produits indépendants. P009 est livré par sous-phases, mais doit démontrer un seul parcours cohérent :

```text
Matching éligible
→ livraison Feed
→ gain annoncé et réservé
→ attention validée côté serveur
→ transaction Grand Livre
→ Wallet utilisateur crédité automatiquement
```

Le navigateur ne crée jamais de WP. P010 renforce ensuite l’antifraude et la reprise ; P011 industrialise le temps réel, le rapprochement et les sorties de valeur.

## Statuts autorisés

```text
proposed
validated
in_progress
blocked
ready_for_review
accepted
merged
deployed
```

Un changement d'ordre, de dépendance ou de périmètre doit être enregistré dans la roadmap et dans le rapport du chantier concerné.
