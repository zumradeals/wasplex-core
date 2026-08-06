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
| P007 | Revue administrative | P006 | Annonceur | En attente (réinitialisé) |
| P008 | SmartProfile, consentements et Matching | P004, P007 | Distribution | En attente (réinitialisé) |
| P009 | Super Moteur, Feed, attention et crédit automatique | P002-P008 | Distribution et valeur | En attente (réinitialisé) |
| P010 | Antifraude, preuves renforcées et reprise | P009 | Valeur et confiance | En attente |
| P011 | Temps réel, rapprochement et retraits utilisateur | P003, P009, P010 | Valeur | En attente |
| P012 | Reporting et dashboards | P007, P009, P011 | Pilotage | En attente |
| P013 | Stabilisation première verticale | P000-P012 | Stabilisation | En attente |
| P014 | Fonds | P003, P004, P011, P012 | Extension | En attente |
| P015 | Alertes | P001, P009, P011, P012, P019 | Extension | En attente |
| P016 | Santé | P001, P012, P015, P019 | Extension | En attente |
| P017 | Carte et partenaires | P003, P011, P012, P019 | Extension | En attente |
| P018 | Live | P006-P012, P020 | Extension | En attente |
| P019 | Espaces professionnels/institutionnels | P001, P012 | Extension | En attente |
| P020 | Communication, modération et risques | P001, P011, P012 | Extension | En attente |
| P021 | Intégrations et production | modules stabilisés | Production | En attente |

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
