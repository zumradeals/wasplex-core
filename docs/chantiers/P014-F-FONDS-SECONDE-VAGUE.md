# P014-F — Fonds : seconde vague de collecte

## Décision d’architecture

P014-F conserve le **snapshot économique unique** créé par P014-D pour chaque vœu. Une seconde vague ne crée donc pas un nouveau pool économique et ne remplace pas le snapshot racine.

Les vagues complémentaires sont portées par les obligations `fund_collection_participants` avec :

- `wave_number` ;
- `wave_target_minor` ;
- `wave_hash` ;
- `wave_notice_at` ;
- `wave_scheduled_at` ;
- `wave_status`.

Cette approche préserve l’intégration P014-E : toute solidarité encaissée, quelle que soit la vague, continue de créditer le même compte `fund.collection.pool.<snapshot_id>.wp`.

## Déclenchement

Une vague complémentaire ne peut être préparée qu’après l’exécution de la première vague et uniquement s’il reste un déficit réel.

Le montant de la nouvelle vague est toujours :

`objectif collectif du snapshot - solidarité réellement encaissée sur toutes les vagues`

Le moteur prend un verrou transactionnel sur le snapshot avant le calcul.

## Remplacement des arriérés

Créer une nouvelle vague signifie que le déficit non honoré des vagues précédentes est remplacé par une nouvelle population débitable.

Les arriérés ouverts du snapshot sont donc :

- passés à `waived` ;
- sortis du montant à régulariser du membre ;
- conservés dans l’historique ;
- audités par `FundCollectionArrearsReplacedByWave`.

Les anciennes obligations non honorées passent à `skipped` afin d’éviter qu’un ancien débit soit exécuté en parallèle de la vague de remplacement.

Pendant une vague complémentaire active, le snapshot racine reste en état `cancelled` pour neutraliser l’ancien exécuteur P014-D. Lorsque le besoin global est entièrement couvert, le snapshot repasse à `funded`.

## Frais Wasplex

Le frais fixe reste séparé de la solidarité.

Pour un même snapshot, donc pour un même vœu :

- un compte déjà présent dans une vague avec un frais Wasplex positif ne reçoit plus de nouveau frais dans les vagues suivantes ;
- un nouveau compte peut recevoir le frais normal du programme ;
- aucun frais n’est prélevé lorsqu’aucune solidarité positive n’est débitée.

La règle est ainsi **un seul frais Wasplex attribuable par membre et par vœu**, même si le membre réapparaît dans plusieurs vagues.

## Préavis et immutabilité de vague

Chaque vague complémentaire possède :

- sa date de notification ;
- sa date d’exécution ;
- sa cible de solidarité ;
- sa liste de participants ;
- son hash SHA-256 ;
- son état d’exécution.

Un débit de vague complémentaire est refusé avant `wave_scheduled_at`.

## Protection anti-surcollecte

P014-F ajoute deux protections complémentaires.

### Moteur

Avant chaque débit de vague, le service recalcule le reste global du snapshot sous verrou. Le débit de solidarité d’un participant est plafonné par ce reste.

### PostgreSQL

Un trigger `fund_collection_target_guard` sérialise les écritures de débit par snapshot et refuse toute insertion dans `fund_collection_debits` qui ferait dépasser `collective_amount_minor`.

Cette garde protège aussi contre une régression future ou une concurrence entre deux chemins d’exécution.

## API admin

- `POST /api/admin/funds/collections/{snapshot}/waves`
  - crée la prochaine vague complémentaire ;
- `POST /api/admin/funds/collections/{snapshot}/waves/{waveNumber}/execute`
  - exécute ou réessaie une vague après son préavis.

Les deux actions nécessitent MFA récent et la capacité `admin.funds.review`.

## Projection membre

L’endpoint des obligations Fonds expose désormais :

- numéro de vague ;
- statut de vague ;
- hash de vague ;
- préavis propre à la vague ;
- date d’exécution propre à la vague.

L’identité du bénéficiaire reste protégée comme en P014-D.

## Critères d’acceptation

- [x] snapshot économique racine unique conservé ;
- [x] seconde vague calculée uniquement sur le déficit réel ;
- [x] nouveau préavis obligatoire ;
- [x] hash propre à la vague ;
- [x] mêmes règles d’éligibilité, mandat et plafonds que la collecte initiale ;
- [x] frais Wasplex non répété pour un membre déjà engagé sur le vœu ;
- [x] arriérés remplacés clôturés avant la nouvelle vague ;
- [x] ancien exécuteur neutralisé pendant une vague de remplacement ;
- [x] garde PostgreSQL contre toute surcollecte ;
- [x] toutes les vagues créditent le pool Ledger racine utilisé par P014-E ;
- [x] projection membre compatible avec les préavis de vagues.
