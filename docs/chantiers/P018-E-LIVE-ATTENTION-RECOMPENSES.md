# P018-E — Live sponsorisé : attention vérifiée, capture et crédit WP

**Statut :** implémentation préparée après P018-D  
**Base :** places rémunérées + liste d’attente FIFO + budget Live réservé  
**Impact économique :** capture progressive du budget réservé, crédit Wallet membre et reconnaissance Wasplex au Grand Livre

## 1. Objectif

P018-E transforme une **place rémunérée** de P018-D en attention réellement payable.

La chaîne serveur devient :

```text
place rémunérée active
→ connexion au direct
→ heartbeats de présence
→ bloc complet vérifié
→ capture idempotente du budget Live réservé
→ crédit Wallet membre
→ reconnaissance de la part Wasplex
```

Le navigateur ne fournit jamais une durée à payer et ne crédite jamais un Wallet.

## 2. Invariants économiques

Le devis P018-D a déjà fixé :

```text
reward_per_block_minor = R
funded_blocks = N
```

Chaque bloc capturé consomme exactement :

```text
2R WP du budget Live réservé
├── R WP → Wallet membre
└── R WP → revenu Wasplex Live
```

Le partage 50/50 reste donc strict.

Deux limites sont verrouillées côté PostgreSQL :

- un membre ne peut jamais dépasser `max_blocks_per_viewer` ;
- l'ensemble des membres, y compris après rotation des places via la liste d'attente, ne peut jamais dépasser `funded_blocks`.

La clé d'idempotence financière est déterministe :

```text
live-reward-block:{live_id}:{account_id}:{block_index}
```

La table des blocs possède en plus une unicité `(live_id, account_id, block_index)`.

## 3. Attention vérifiée

Le client envoie uniquement :

```json
{
  "visible": true,
  "media_connected": true
}
```

La durée qualifiée est calculée uniquement avec l'horloge serveur.

Un intervalle n'est compté que si :

- le Live est réellement `live` ;
- le membre possède encore une place rémunérée active ;
- sa session spectateur est encore active ;
- le heartbeat précédent était lui aussi qualifié ;
- la page est déclarée visible ;
- la salle média est déclarée connectée ;
- l'écart entre heartbeats reste dans la fenêtre technique autorisée.

Une pause de l'hôte conserve la progression partielle mais ne compte aucun temps.  
Une page cachée, une déconnexion média ou une sortie du Live remet le bloc partiel à zéro.

Après toute interruption, le premier heartbeat qualifié ne compte pas le temps précédent : il rétablit seulement une nouvelle base temporelle.

## 4. Données

### `live_reward_attention_states 

Projection technique par couple Live + membre :

- place et session courantes ;
- nombre de blocs validés ;
- progression du bloc courant ;
- état du heartbeat précédent ;
- dernier heartbeat qualifié ;
- signaux de risque.

Ce n'est pas une source financière.

### `live_reward_attention_blocks`

Preuve append-only d'un bloc payé :

- campagne et devis ;
- Live et membre ;
- place/session ;
- numéro du bloc ;
- attention validée ;
- récompense membre ;
- consommation brute ;
- transaction Grand Livre ;
- date de capture.

## 5. Grand Livre

`AdvertiserLiveBudgetReservationContract` reçoit une frontière supplémentaire :

```text
captureReward(...)
```

Transaction :

```text
LIVE_REWARD_BLOCK_CAPTURED
source_module = live.attention

DEBIT  advertiser.live.budget.reserved     2R
CREDIT user.available.wp                    R
CREDIT wasplex.live.advertising.revenue     R
```

Le Wallet membre reste une projection du Grand Livre.

Après commit, `wallet.balance.changed` est diffusé via le contrat Wallet existant.

## 6. Clôture économique

À la fin du Live :

1. les blocs déjà capturés restent immuables ;
2. la consommation brute est recalculée depuis les blocs capturés ;
3. le reliquat exact du budget réservé est libéré vers le Wallet annonceur ;
4. la réservation passe à `released` ;
5. l'opération est idempotente.

Ainsi un Live terminé ne laisse aucun budget fantôme dans `advertiser.live.budget.reserved`.

## 7. API membre

Sous `/api/lives/{live}` :

```text
GET  /reward-attention
POST /reward-attention/heartbeat
```

La réponse membre expose uniquement :

- état de suivi ;
- blocs validés / plafond ;
- progression du bloc courant ;
- gain déjà acquis ;
- gain maximal ;
- solde Wallet courant ;
- état global du financement.

Aucune donnée annonceur sensible n'est renvoyée.

## 8. API Studio annonceur

Sous `/api/advertiser/lives/{live}` :

```text
GET /reward-report
```

Le rapport reste agrégé :

- blocs financés ;
- blocs capturés ;
- nombre de spectateurs rémunérés ;
- WP distribués ;
- revenu Wasplex reconnu ;
- consommation brute ;
- reliquat réservé ou libéré.

Aucune identité de bénéficiaire n'est exposée au Studio.

## 9. Protections concurrence / fraude

- verrou campagne pendant la capture ;
- unicité SQL du bloc ;
- clé Ledger idempotente ;
- aucun temps déclaré par le client ;
- heartbeat trop rapide journalisé ;
- trou de heartbeat trop long : bloc partiel annulé ;
- transition caché → visible non rétroactive ;
- plafond global financé vérifié sous verrou.

## 10. Tests P018-E

Le lot couvre notamment :

- capture d'un bloc complet et partage Ledger 50/50 ;
- aucun paiement avant bloc complet ;
- onglet caché / média déconnecté ;
- pause / reprise sans comptage de la pause ;
- plafond global malgré rotation de la place rémunérée ;
- crédit Wallet exact ;
- unicité des captures ;
- clôture et libération idempotente du budget inutilisé.

## 11. Hors périmètre

P018-E ne transforme pas encore les interactions sociales en rémunération supplémentaire. Les sondages, quiz, CTA sponsorisés, replay rémunéré et mécanismes avancés de preuve comportementale appartiennent à des lots ultérieurs.
