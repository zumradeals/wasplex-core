# WASPLEX — GRAPHE INITIAL DES DÉPENDANCES

## Chemin critique proposé

```text
P000 Socle
→ Identity / Espaces / Capacités
→ Ledger
→ Wallet et Wallet annonceur
→ Configurations économiques et abonnements
→ Marques et campagnes
→ Revue administrative
→ Profil volontaire et Matching
→ Feed publicitaire
→ Attention et moteur de valeur
→ Crédit Wallet après commit
→ Notification temps réel
→ Reporting et audit
```

## Dépendances transversales

| Capacité transversale | Consommateurs principaux |
|---|---|
| Identity / espaces | Tous les modules |
| Capacités / politiques | Administration, Finance, Alertes, Santé, partenaires |
| Ledger | Wallet, publicité, Fonds, Carte, Live |
| Wallet | publicité, Fonds, Carte, Live, partenaires |
| Consentements / projections | Matching, Santé, Alertes, partenaires |
| Outbox / queues | notifications, temps réel, intégrations, reporting |
| Audit | finance, administration, sécurité, Santé, Alertes |
| Configuration versionnée | économie, quotas, frais, plans, risques |

## Modules à différer après la première verticale

Fonds, Alertes, Santé, Carte, Live et espaces professionnels doivent être préparés par les contrats du socle, mais ne doivent pas élargir P000 ni retarder la première verticale économique publicitaire.
