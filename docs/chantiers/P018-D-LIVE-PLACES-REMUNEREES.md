# P018-D — Places rémunérées, capacité et liste d’attente

## Objet

P018-D transforme le budget spectateurs réservé par P018-C en un **plan de places rémunérées limitées**, sans encore valider l’attention et sans encore créditer de WP.

Chaîne couverte :

```text
budget réservé
→ plan de places
→ éligibilité membre
→ place rémunérée ou visionnage non rémunéré
→ liste d’attente facultative
→ offre temporaire
→ acceptation / libération
```

La suite reste P018-E :

```text
place active
→ attention vérifiée
→ bloc validé
→ capture Grand Livre
→ Wallet
```

## Garanties

- le nombre de places actives + offertes ne dépasse jamais la capacité du devis ;
- PostgreSQL sérialise les admissions par campagne financée ;
- un membre sans place peut toujours regarder le Live ;
- aucun texte ni état ne promet de WP à un spectateur sans place active ;
- une offre de liste d’attente réserve temporairement la capacité ;
- l’expiration ou le refus propose la place au suivant ;
- quitter le Live libère la place ;
- une pause conserve la place ;
- la fin du Live ferme les places et la liste d’attente ;
- aucun crédit Wallet ou écriture de récompense n’est créé par P018-D ;
- l’API membre ne révèle jamais budget annonceur, segment privé ou références Ledger.

## Plan économique

Le devis P018-C est enrichi avec :

- `rewarded_seat_capacity` ;
- `max_blocks_per_viewer` ;
- `funded_blocks` ;
- `reward_per_block_minor` ;
- `max_reward_per_viewer_minor` ;
- `spectator_envelope_remainder_minor`.

Calcul :

```text
blocs financés = places × blocs max par spectateur
gain/bloc = plancher(enveloppe spectateurs ÷ blocs financés)
plafond spectateur = gain/bloc × blocs max
reliquat = enveloppe - (gain/bloc × blocs financés)
```

Le devis est refusé si l’enveloppe ne permet pas au moins 1 WP par bloc financé.

## Données

### `live_reward_seats`

Une ligne unique par Live + compte. Elle peut être réactivée après une sortie, mais une seule place active existe par compte.

### `live_reward_waitlist_entries`

Une ligne unique par Live + compte avec états :

```text
waiting
offered
accepted
declined
expired
cancelled
```

L’ordre est FIFO par `queued_at`.

### `live_viewer_sessions`

Ajout de :

- `rewarded_status` ;
- `economic_class`.

Les états économiques sont indépendants de l’état audiovisuel de la session.

## Temps réel

Le canal privé :

```text
live-reward.{accountId}
```

est accessible uniquement au compte concerné. L’événement :

```text
.live.reward.seat.changed
```

indique qu’une offre ou un changement d’état est disponible. PostgreSQL reste source de vérité et le client recharge l’état HTTP après l’événement.

## Hors périmètre

- heartbeats ;
- visibilité de l’application ;
- contrôles de présence ;
- validation d’un bloc ;
- prorata ;
- capture du budget spectateurs ;
- crédit Wallet ;
- animation `+WP` ;
- compensation réseau.

Ces éléments appartiennent à P018-E.
