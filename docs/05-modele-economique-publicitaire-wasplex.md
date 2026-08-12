# WASPLEX — MODÈLE ÉCONOMIQUE PUBLICITAIRE V1

**Statut :** modèle économique de référence, aligné sur l’implémentation après les PR #60 et #67  
**Usage :** référence produit, technique et future base de rédaction du business plan Wasplex  
**Monnaie interne :** 1 WP = 1 FCFA  
**Principe de partage :** 50 % membre / 50 % Wasplex sur chaque vue complète qualifiée

---

## 1. Principe général

Wasplex transforme une attention publicitaire réellement fournie et vérifiée en valeur économique traçable.

Le modèle V1 repose sur cinq règles simples :

1. l’annonceur finance une campagne depuis son solde publicitaire ;
2. le membre reçoit un montant fixe selon sa classe économique et la durée réelle de la vidéo ;
3. Wasplex reconnaît une valeur équivalente à celle du membre ;
4. une vidéo ne produit de valeur qu’après une complétion qualifiée ;
5. le temps, le quota, la récompense et le coût annonceur utilisent la même unité économique de 15 secondes commencées.

Le modèle n’est donc plus fondé sur une répartition générale 10 % / 20 % / 35 % / 35 % d’une enveloppe utilisateurs. Cette ancienne règle est obsolète pour la V1 actuelle.

---

## 2. Unité d’attention

L’unité économique de référence est :

```text
1 crédit d’attention = 15 secondes commencées
```

Le nombre de crédits d’une vidéo est calculé ainsi :

```text
crédits = plafond(durée réelle en millisecondes / 15 000)
```

Exemples :

```text
1 à 15 s       = 1 crédit
15,001 à 30 s  = 2 crédits
60 s           = 4 crédits
5 min          = 20 crédits
```

La durée vidéo réelle est mesurée côté serveur avec `ffprobe`, enregistrée en millisecondes et arrondie vers le haut afin qu’une tranche commencée ne soit jamais sous-comptée.

La V1 publicitaire est vidéo uniquement, avec une durée maximale de 5 minutes.

---

## 3. Classes économiques et récompense de base

La récompense de base correspond à un crédit de 15 secondes.

| Classe | Récompense de base | Crédits mensuels | Plafond théorique mensuel |
|---|---:|---:|---:|
| Gratuit | 30 WP | 120 | 3 600 WP |
| Premium | 40 WP | 300 | 12 000 WP |
| Gold | 50 WP | 600 | 30 000 WP |
| Platine | 60 WP | 900 | 54 000 WP |

Le plafond théorique est une capacité économique maximale. Il ne constitue jamais une promesse de revenu : il dépend notamment de la disponibilité des campagnes, du matching, de l’éligibilité, des complétions réelles et des règles de sécurité.

---

## 4. Abonnements V1

Prix de lancement prévus sur des cycles de 30 jours :

```text
Gratuit  : 0 FCFA
Premium  : 1 500 FCFA
Gold     : 4 000 FCFA
Platine  : 7 500 FCFA
```

Les offres payantes ne deviennent visibles aux membres qu’après publication explicite par l’administration.

La valeur de l’abonnement ne vient pas d’un rendement financier promis. L’abonnement donne accès à une classe économique avec une récompense de base et une capacité mensuelle d’attention supérieures, en plus des autres avantages produit qui pourront être associés au plan.

---

## 5. Récompense selon la durée réelle

La récompense membre est :

```text
récompense membre
= récompense de base de la classe × crédits d’attention de la vidéo
```

Exemple Gold, vidéo de 60 secondes :

```text
Durée                 : 60 s
Crédits               : 4
Base Gold             : 50 WP
Récompense membre     : 200 WP
Part Wasplex          : 200 FCFA
Coût économique total : 400 FCFA
```

Une vidéo plus longue consomme donc davantage de crédits mais rémunère proportionnellement davantage. Comme le gain et le quota sont multipliés par le même nombre de crédits, les plafonds théoriques mensuels des classes restent inchangés.

---

## 6. Partage 50 / 50

Pour chaque vue complète qualifiée :

```text
Valeur économique totale
├── 50 % membre
└── 50 % Wasplex
```

Si un membre Gold reçoit 50 WP pour une unité de 15 secondes, le coût économique correspondant pour l’annonceur est de 100 FCFA :

```text
50 WP membre + 50 FCFA Wasplex = 100 FCFA
```

Pour quatre unités :

```text
200 WP membre + 200 FCFA Wasplex = 400 FCFA
```

Le partage est appliqué au moment de la vue complète validée, pas au simple chargement de la publicité.

---

## 7. Ce que paie l’annonceur

L’annonceur finance une campagne publicitaire et achète notamment :

- une diffusion ciblée ;
- une audience agrégée ;
- une attention qualifiée ;
- une mesure de performance ;
- une capacité de ciblage protégée ;
- un nombre d’événements finançables déterminé par le budget disponible.

Le devis tient compte de la classe économique ciblée, de la récompense applicable et du nombre de crédits liés à la durée réelle de la vidéo.

Le moteur ne peut jamais promettre davantage de vues rémunérées que l’enveloppe réellement financée.

---

## 8. Exemple de budget campagne

Hypothèse : campagne Gold avec vidéos de 60 secondes.

```text
Budget campagne              : 100 000 FCFA
Crédits par vue              : 4
Récompense membre / vue      : 200 WP
Part Wasplex / vue           : 200 FCFA
Coût total / vue qualifiée   : 400 FCFA
Vues complètes finançables   : 250
```

À chaque vue complète qualifiée, 400 FCFA de valeur économique sont consommés : 200 WP sont crédités au membre et 200 FCFA correspondent à la part Wasplex.

Les reliquats éventuels restent traçables ; aucune valeur résiduelle ne devient silencieusement un revenu caché.

---

## 9. Quand une vue devient facturable et rémunérable

Une vidéo préchargée ou simplement affichée ne suffit pas.

Pour une livraison rémunérée, Wasplex exige notamment :

- le démarrage réel de la livraison ;
- une progression d’attention enregistrée côté serveur ;
- une durée visible suffisante ;
- l’événement réel de fin du média lorsque celui-ci est requis ;
- l’absence d’un blocage économique ou de sécurité.

Un appel artificiel à la fin de vidéo sans preuve d’attention préalable ne qualifie pas la vue.

La valeur n’est capturée qu’après validation de la complétion.

---

## 10. Quotas et crédits d’attention

Les quotas mensuels représentent désormais des crédits d’attention et non un simple nombre brut de vidéos.

Une vidéo de 60 secondes consomme 4 crédits. Une vidéo de 5 minutes en consomme 20.

Le quota est consommé au démarrage réel d’une livraison rémunérée selon le nombre de crédits requis.

Une livraison abandonnée ou invalidée suit les mécanismes de libération/restauration prévus par le moteur afin de ne pas créer de consommation financière injustifiée.

---

## 11. Replay

Une campagne déjà récompensée peut être revue sans produire une deuxième rémunération.

```text
Replay
= 0 WP supplémentaire
= 0 crédit d’attention supplémentaire
= 0 consommation supplémentaire du budget campagne
```

La règle de référence est : au maximum une récompense par membre et par campagne.

---

## 12. Diffusion publique hors connexion

Une campagne approuvée peut disposer d’une portée publique lorsque cette option est activée.

Une vue publique anonyme :

```text
0 WP
0 crédit d’attention membre
0 réservation de récompense
0 capture du budget Feed rémunéré
```

La portée publique sert à l’acquisition et à la visibilité ; elle est économiquement séparée du Feed membre rémunéré.

---

## 13. Auto-rémunération interdite

Un annonceur, propriétaire ou membre actif de l’organisation qui possède une campagne ne peut pas recevoir de WP sur sa propre publicité.

Cette exclusion est appliquée avant la diffusion et revalidée dans le parcours de paiement afin qu’une campagne ne puisse pas servir à transformer artificiellement un budget publicitaire en récompense personnelle.

---

## 14. Préchargement et neutralité économique

Le préchargement technique d’une prochaine vidéo doit rester économiquement neutre.

Précharger signifie uniquement préparer les données et le média afin de réduire le buffering et le temps de transition.

Le préchargement ne doit jamais, à lui seul :

- démarrer la prochaine livraison ;
- consommer des crédits d’attention ;
- réserver ou capturer une nouvelle valeur financière comme si la vidéo était regardée ;
- produire une récompense ;
- déclencher le chronomètre d’attention.

La livraison ne devient active que lorsque le membre arrive réellement sur la vidéo et que sa lecture démarre conformément aux règles du Feed.

Cette règle est un invariant économique important pour toute optimisation du lecteur, notamment le préchargement anticipé du Feed.

---

## 15. Grand Livre et traçabilité

Les flux financiers réels doivent rester traçables dans le Grand Livre.

Les événements importants couvrent notamment :

- financement de campagne ;
- montant disponible ;
- réservation ;
- libération ;
- capture ;
- part Wasplex ;
- récompense membre ;
- remboursement ou correction lorsqu’applicable.

Les opérations financières critiques doivent être atomiques et idempotentes : une même vue qualifiée ne peut produire ni double débit annonceur ni double crédit membre.

---

## 16. Confidentialité commerciale et données membres

L’annonceur reçoit des informations agrégées utiles au pilotage de sa campagne, sans acheter l’identité des membres.

Il n’achète pas :

- les numéros de téléphone ;
- les emails ;
- les profils individuels ;
- les données KYC ;
- les données Santé ;
- les Alertes privées ;
- une extraction des réponses individuelles du Profil intelligent.

L’administration Wasplex peut superviser les règles économiques, les campagnes, les anomalies et le Grand Livre selon les permissions prévues.

---

## 17. Formules de référence

```text
attention_units = ceil(duration_ms / 15_000)

member_reward = base_reward(class) × attention_units

wasplex_share = member_reward

advertiser_cost_per_qualified_view
= member_reward + wasplex_share
= 2 × member_reward
```

Valeurs de base :

```text
Gratuit  = 30 WP / unité
Premium  = 40 WP / unité
Gold     = 50 WP / unité
Platine  = 60 WP / unité
```

Capacités mensuelles :

```text
Gratuit  = 120 crédits
Premium  = 300 crédits
Gold     = 600 crédits
Platine  = 900 crédits
```

---

## 18. Indicateurs utiles au futur business plan

Le futur business plan pourra dériver de ce modèle les indicateurs suivants :

- chiffre d’affaires publicitaire brut ;
- part Wasplex reconnue ;
- valeur distribuée aux membres ;
- revenu d’abonnement par classe ;
- crédits d’attention disponibles et consommés ;
- coût moyen par vue complète qualifiée ;
- durée moyenne des vidéos ;
- taux de complétion ;
- taux de replay ;
- budget réservé, consommé et restant ;
- portée membre et portée publique ;
- revenu moyen par campagne et par annonceur ;
- marge opérationnelle après coûts de paiement, hébergement, stockage, diffusion vidéo, support, fraude et conformité.

Les projections de business plan devront distinguer clairement :

1. la valeur économique théorique maximale ;
2. la consommation réelle d’attention ;
3. le revenu réellement reconnu ;
4. la trésorerie encaissée ;
5. les coûts opérationnels.

---

## 19. Décision économique V1

Le modèle officiel à retenir est :

```text
1 WP = 1 FCFA
1 crédit d’attention = 15 secondes commencées
vidéo V1 = maximum 5 minutes
récompense = base de classe × crédits vidéo
partage d’une vue qualifiée = 50 % membre / 50 % Wasplex
replay = 0 nouvelle valeur
préchargement = 0 consommation économique
```

Les anciennes répartitions générales d’enveloppe 10 % / 20 % / 35 % / 35 % ne doivent plus être utilisées comme règle V1.

Ce document constitue la référence économique fonctionnelle à utiliser pour les évolutions produit, les simulations financières et la future rédaction du business plan Wasplex.
