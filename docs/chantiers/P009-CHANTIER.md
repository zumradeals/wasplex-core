# P009 — Super Moteur, Feed, attention et crédit automatique

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `c424e3b` (P008 fusionné)
**Dépendances :** P002-P008 (Grand Livre, Wallet annonceur, Abonnements, Campagnes, Revue, SmartProfile/Matching)
**Spécifications :** `docs/07-super-moteur-unifie-valeur-temps-reel-wasplex.md`,
`docs/08-feed-principal-wasplex.md`, `docs/00-identite-visuelle-wasplex.md`

## Décision fondatrice (rappel `docs/ROADMAP-INDEX.md`)

```text
Matching éligible → livraison Feed → gain annoncé et réservé
→ attention validée côté serveur → transaction Grand Livre
→ Wallet utilisateur crédité automatiquement
```

C'est la promesse produit de Wasplex. Elle doit fonctionner réellement, de bout en bout, avant
d'ajouter quoi que ce soit d'autre.

## 1. Objectif

Livrer la première verticale complète (`docs/07` §55, `docs/08` §85) :

```text
utilisateur éligible (Matching, P008)
→ campagne approuvée sélectionnée
→ gain annoncé avant lecture
→ enveloppe de campagne réservée
→ session d'attention réelle (barre, heartbeats)
→ complétion prouvée côté serveur
→ Grand Livre écrit (débit budget réservé annonceur → crédit Wallet utilisateur)
→ Wallet utilisateur crédité, animation, historique
→ quota publicitaire consommé
→ budget campagne diminué
```

Aucun crédit ne doit jamais être décidé côté client.

## 2. Décisions de réduction explicites (à documenter, pas à cacher)

Les deux spécifications (`docs/07`, `docs/08`) couvrent un périmètre bien plus large que ce
chantier (Fonds, Alertes, Live, Explorer, Carte, modération complète, reprise après panne
distribuée, accessibilité). Conformément à la méthode de ce dépôt (chaque chantier précédent a
réduit son périmètre explicitement plutôt que d'inventer), P009 se limite à la verticale
publicitaire complète et documente le reste comme hors périmètre :

1. **Pas de « Super Moteur » générique séparé.** `docs/07` décrit une couche d'orchestration
   réutilisable pour Fonds/Alertes/Partenaires/Carte/Live — aucun de ces modules n'existe encore.
   Construire l'abstraction générique maintenant serait une microservice prématurée
   (`docs/CLAUDE.md` §5/§25). La chaîne devis→réservation→preuve→transaction est implémentée
   directement et uniquement pour la publicité, dans le module `Feed`, avec des noms de méthodes
   qui pourront être extraits vers un moteur commun le jour où un deuxième domaine (Fonds ou
   Alertes) en aura réellement besoin.
2. **Pas d'abstraction `FeedItem` polymorphe.** `docs/08` §63 recommande une table
   `feed_items`/`feed_item_sources` pour unifier publicité, alerte, avis officiel, contenu
   institutionnel. Un seul type de contenu existe dans ce dépôt (la publicité, via les campagnes
   P006/P007) — introduire le polymorphisme maintenant pour un type unique serait une abstraction
   sans deuxième cas d'usage réel. Le Feed lit les campagnes approuvées directement via
   `ApprovedCampaignAudienceContract` (P008) et les évalue via `MatchingContract` (P008).
   L'abstraction sera introduite quand Alertes (P015) ou le contenu institutionnel deviendra réel.
3. **Pas d'onglets Alertes/Explorer fonctionnels.** Les onglets existent visuellement (structure
   de navigation conforme à `docs/08` §5) mais restent des emplacements réservés désactivés —
   Alertes est P015, Explorer catalyse des contenus qui n'existent pas encore.
4. **Pas de heartbeats persistés individuellement.** `docs/07` §18/§25 décrit un journal de
   heartbeats avec détection de rejeu, vitesse impossible, multi-appareil — c'est le cœur de
   l'antifraude renforcée (P010, chantier dédié). Ce chantier valide la progression de façon
   simple et honnête : le serveur borne la durée visible déclarée par le temps réel écoulé depuis
   le début de la session (jamais plus que l'horloge serveur ne l'autorise) et exige une
   séquence de progression monotone, sans conserver un journal détaillé de chaque heartbeat.
   Documenté comme limite explicite, pas comme une antifraude complète.
5. **Livraison = session d'attention, une seule table.** `docs/08` §63 sépare
   `feed_deliveries`/`feed_attention_sessions`/`feed_attention_proofs`. Ce chantier les fusionne
   en une seule table `feed_ad_deliveries` (statuts `reserved → started → completed/abandoned/
   expired`) — la preuve est une transition d'état auditée, pas un document séparé, suffisant pour
   ce périmètre.
6. **Commentaires minimaux, sans modération.** L'utilisateur (fondateur) a explicitement demandé
   que le rail social affiche j'aime/commenter/partager/favoris. Les commentaires sont donc
   réels (texte, liste plate, compteur) mais sans fil de réponses, sans modération, sans
   signalement — ces éléments (`docs/08` §33/§55) demandent une conception dédiée hors budget de
   ce chantier.
7. **Pas de temps réel WebSocket/Reverb.** Aucun chantier précédent n'a câblé Reverb malgré sa
   présence dans la stack officielle. Le crédit Wallet est confirmé par la réponse HTTP synchrone
   de `POST /api/feed/deliveries/{id}/complete` — l'animation démarre sur cette réponse, pas sur un
   événement poussé. `wallet.balance.changed` en temps réel reste un chantier de durcissement
   futur (aucun changement de schéma requis pour l'ajouter ensuite).
8. **Expiration des réservations : commande manuelle, pas de worker planifié.** Une commande
   `feed:release-expired-deliveries` existe et peut être planifiée ; ce chantier ne met pas en
   place Horizon/Scheduler (cohérent avec P002-P008, tous synchrones).
9. **Aucune part Wasplex distincte capturée par événement.** Le devis P006 calcule déjà
   `gain_unitaire_minor` ≈ `cost_per_event_minor` (l'enveloppe utilisateur divisée par le nombre
   d'événements qu'elle finance élimine presque tout l'écart — vérifié arithmétiquement). La
   capture débite `advertiser.budget.reserved` du montant exact crédité à l'utilisateur ; le
   reliquat et la part Wasplex implicite restent dans la réservation, non comptabilisés
   individuellement ici — leur reconnaissance comptable précise est un chantier de reporting
   financier distinct (P012), non requis par les critères d'acceptation de `docs/07` §53.
10. **Pas de mode réseau faible, accessibilité avancée, Explorer, Live.** Hors périmètre
    (`docs/08` Phases 8-10), non requis par la première verticale.

## 3. Modèle de données

### Module `Wallet` (nouveau, projection utilisateur — miroir d'`AdvertiserWallet`)

- `user_wallets` (compte, devise, statut) — ancre, comme `advertiser_wallets`.

### Module `Campaigns` (étendu)

- `campaign_envelope_consumptions` (devis de campagne, classe économique, statut
  `reserved/captured/released/expired`, expiration) — suit la capacité de l'enveloppe par classe
  sans connaître l'identité du compte (anonyme, propriété Campagnes).

### Module `Feed` (nouveau)

- `feed_sessions` (compte, début, fin).
- `feed_ad_deliveries` (session, compte, campagne, `campaign_envelope_consumption_id`, classe,
  gain, durée requise, durée visible, statut, horodatages) — livraison + session d'attention
  fusionnées (décision §2.5).
- `feed_ad_interactions` (compte, campagne, type `like/save/share`, unique par compte+campagne+type
  pour like/save — bascule ; plusieurs lignes autorisées pour share).
- `feed_ad_comments` (compte, campagne, texte, horodatage) — append-only, sans modération.

## 4. Contrats internes (nouveaux ou étendus)

- `Wallet\Application\Contracts\UserWalletContract` (nouveau) : `availableAccountReference()`,
  `balanceMinor()`, `getOrCreate()`.
- `AdvertiserWallet\Application\Contracts\AdvertiserWalletReservationContract` étendu :
  `capture(organizationId, campaignId, amountMinor, LedgerAccountReference $destination,
  idempotencyKey): string` — débite `advertiser.budget.reserved`, crédite la référence fournie par
  l'appelant (jamais construite en dur pour un autre module).
- `Campaigns\Application\Contracts\CampaignEnvelopeContract` (nouveau) : `reserveSlot()`,
  `captureSlot()`, `releaseSlot()` — seule façon dont Feed consomme la capacité d'une campagne.
- `Matching\Application\Contracts\MatchingContract` (P008, réutilisé sans modification) : sélection
  et explication.
- `Subscriptions\Application\Services\SubscriptionQuotaContract` (P004, premier appelant réel) :
  `consume()` à l'exposition réelle (`start`), pas à la simple sélection.

## 5. Décision de composition du Feed (fréquence/fatigue enfin appliquées)

`docs/chantiers/P008-CHANTIER.md` §3.2 avait explicitement différé l'application des seuils de
`matching_configurations` : « P009 branchera les compteurs réels sur ces mêmes seuils sans
changement de schéma ». Ce chantier tient cette promesse :

```text
campagnes approuvées éligibles (MatchingContract)
→ exclure les campagnes dont l'enveloppe de la classe est épuisée
→ exclure les campagnes déjà livrées ≥ frequency_max_per_window sur frequency_window_hours
→ exclure les campagnes ayant atteint fatigue_threshold (compteur vie du compte)
→ choisir une candidate restante
```

Sans candidate : le Feed retourne un état vide honnête (aucune publicité disponible), jamais une
fausse candidate.

## 6. API

### Utilisateur (self-service)

```text
POST /api/feed/sessions
GET  /api/feed/next
POST /api/feed/deliveries/{id}/start
POST /api/feed/deliveries/{id}/heartbeat
POST /api/feed/deliveries/{id}/complete
POST /api/feed/deliveries/{id}/abandon
POST /api/feed/deliveries/{id}/like
POST /api/feed/deliveries/{id}/save
POST /api/feed/deliveries/{id}/share
GET  /api/feed/deliveries/{id}/comments
POST /api/feed/deliveries/{id}/comments
GET  /api/feed/why/{id}
GET  /api/me/wallet
```

### Administration

```text
GET /api/admin/feed/dashboard   (admin.feed.dashboard.view)
```

## 7. UI (exigence explicite du fondateur : soignée, non-TikTok, cohérente sur les 5 destinations)

- **Feed** : en-tête immersif superposé à la vidéo (logo, onglets Pour toi/Alertes/Explorer,
  pastille solde Wallet), bannière de gain avant lecture, barre de progression réelle, rail social
  droit (j'aime/commenter/favoris/partager, cercles), rail gauche réservé aux futures Alertes
  (cercles désactivés, visuellement présents), navigation basse en cercles d'icônes avec le Wallet
  central mis en avant (dégradé « valeur gagnée », `docs/00` §6.3).
- **Wallet** : carte de solde en dégradé, historique réel, animation de crédit après confirmation
  serveur (`motion.reward`, 900-1400 ms, `docs/00` §15.1).
- **Fonds / Alertes** : emplacements réservés restylés pour rester visuellement cohérents avec le
  reste (mêmes cercles, mêmes tons), sans logique fonctionnelle nouvelle.

## 8. Tests obligatoires

Verticale complète (Gold/Côte d'Ivoire) ; gain connu avant lecture ; réservation d'enveloppe ;
abandon libère l'enveloppe sans gain ; complétion crédite le Wallet exactement une fois (double
complete impossible) ; quota consommé à l'exposition réelle, jamais avant ; épuisement
d'enveloppe exclut la campagne ; fréquence/fatigue excluent une campagne sur-livrée ; aucune
publicité sans réservation valide ; j'aime/favoris bascule, partage s'accumule, commentaire
persiste ; capacité admin requise pour le tableau de bord.

## 9. Critères de fin

Migrations + rollback propre, tests Pest verts (concurrence incluse), Pint vert, qualité frontend
verte, captures Playwright de la verticale réelle, rapport, `docs/ROADMAP-INDEX.md` mis à jour, PR
en brouillon, CI verte, merge, resynchronisation de branche.
