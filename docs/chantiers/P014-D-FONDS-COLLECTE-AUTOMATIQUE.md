# P014-D — Fonds : collecte automatique

## Objectif

Transformer un vœu validé, chiffré par un prestataire P019 et dont l’apport personnel est constitué en une collecte solidaire réellement exécutable, traçable et limitée par le mandat de chaque membre.

La complexité reste dans le moteur. Le membre doit seulement comprendre : **combien**, **pourquoi**, **quand**, **ce qui a été prélevé** et **ce qu’il reste éventuellement à régulariser**.

## Préconditions

Une collecte ne peut être préparée que si :

- le vœu est validé ;
- un coût réel a été validé à partir du parcours devis P014-C ;
- un prestataire est sélectionné ;
- l’apport personnel requis est réellement constitué dans le Grand Livre ;
- la devise est XOF dans la V1, avec la règle `1 WP = 1 FCFA` ;
- le programme possède de la capacité pour une nouvelle collecte.

## Snapshot immuable

Avant tout prélèvement, Wasplex crée un snapshot unique pour le vœu. Il fige notamment :

- vœu, programme et version du programme ;
- pays et devise ;
- coût validé ;
- apport personnel réellement disponible ;
- montant collectif restant ;
- liste ordonnée des participants ;
- mandat et plafond applicables à chaque participant ;
- plafonds quotidien, mensuel et annuel encore disponibles ;
- contribution solidaire due ;
- frais Wasplex applicables ;
- durée de préavis ;
- version de règle ;
- hash SHA-256 du contenu économique du snapshot.

Une nouvelle exécution ne recalcule pas la population à partir de l’état courant. Elle reprend les obligations figées par le snapshot.

## Participants éligibles V1

Le participant doit, au moment de la constitution du snapshot :

- être membre du même programme Fonds ;
- avoir une adhésion Fonds active ;
- avoir un abonnement Wasplex payant actif et l’entitlement Fonds ;
- avoir un mandat actif ;
- appartenir au même pays et à la même devise ;
- avoir un compte actif ;
- ne pas être le bénéficiaire du vœu ;
- ne pas avoir d’arriéré grave arrivé au-delà de sa période de grâce ;
- pouvoir supporter la contribution théorique dans les limites de son mandat et des plafonds applicables.

Un membre hors plafond est exclu avant constitution de l’engagement. Le moteur ne crée jamais volontairement une obligation hors mandat.

## Calcul et arrondi

Montant collectif :

`coût validé - apport personnel - contribution partenaire - réserve autorisée`

En P014-D, les contributions partenaire/réserve sont prévues dans le snapshot mais restent à zéro tant que leurs lots dédiés ne les alimentent pas.

Le montant collectif est réparti entre les participants éligibles. L’arrondi est déterministe :

1. division entière par le nombre de participants ;
2. le reste est distribué à raison de 1 WP, dans l’ordre stable des comptes ;
3. la somme des contributions solidaires doit être exactement égale au montant collectif ;
4. aucune surcollecte n’est autorisée.

Si la contribution recalculée dépasse le plafond d’un participant, ce participant est retiré et le calcul recommence jusqu’à stabilisation.

## Frais Wasplex

Le frais fixe Wasplex reste distinct de la solidarité :

- il est enregistré sur un compte de revenu séparé ;
- il n’est jamais inclus dans l’argent destiné au vœu ;
- il n’est prélevé que lorsqu’un montant de solidarité positif est réellement débité ;
- il n’est prélevé qu’une seule fois par participant pour le même vœu, même en cas de régularisation ultérieure ;
- si le débit est nul, le frais est nul.

## Grand Livre

Un débit réussi produit une transaction équilibrée :

- débit du `fund.balance.wp` du membre ;
- crédit du pool de collecte du snapshot pour la solidarité ;
- crédit du compte de revenu Wasplex pour le frais, uniquement lorsqu’il est dû et réellement prélevé.

Aucun solde utilisateur ne devient négatif.

## Débit partiel et échec

Si le Solde Fonds ne couvre pas toute l’obligation :

- Wasplex ne force jamais un solde négatif ;
- un prélèvement partiel est possible ;
- le frais fixe n’est payé qu’une fois et uniquement si une solidarité positive est effectivement payée ;
- le reste de solidarité devient un arriéré ;
- un solde nul produit un arriéré sans frais Wasplex.

Une nouvelle exécution peut régulariser l’arriéré après alimentation du Solde Fonds sans doubler les écritures déjà réalisées.

## Arriérés

Un arriéré Fonds :

- n’est pas un Wallet négatif ;
- ne porte pas d’intérêt ;
- possède une période de grâce configurable, initialement 7 jours ;
- reste rattaché au snapshot et à l’engagement d’origine ;
- peut empêcher l’inclusion du membre dans de nouveaux snapshots une fois la grâce dépassée ;
- est soldé dès que la solidarité due est entièrement régularisée.

## Préavis

Le snapshot est créé avant l’exécution. La date d’exécution est calculée à partir du préavis applicable. Aucun débit automatique n’est exécuté avant la fin du préavis.

Le membre voit dans son espace Fonds :

- la référence de la collecte ;
- la catégorie générale, sans identité du bénéficiaire ;
- sa contribution solidaire ;
- le frais fixe ;
- la date prévue ;
- ce qui a été payé ;
- l’éventuel montant à régulariser et sa date de grâce.

## Confidentialité

Le participant finance une obligation Fonds, pas une personne exposée publiquement. La projection utilisateur n’affiche pas :

- identité du bénéficiaire ;
- documents privés ;
- adresse précise ;
- données Santé ;
- Wallet ou informations financières du bénéficiaire ;
- détails internes des autres participants.

## Pilotage admin

L’admin Fonds peut :

- voir les vœux prêts à être transformés en collecte ;
- créer le snapshot ;
- voir le nombre de participants et le hash du snapshot ;
- suivre solidarité collectée, frais Wasplex et arriérés ;
- déclencher/réessayer une collecte après le préavis ;
- auditer les résultats.

Il ne modifie pas manuellement les écritures du Grand Livre.

## Paramètres configurables

Chaque version de programme peut configurer notamment :

- montant minimum/maximum de débit ;
- plafond quotidien ;
- plafond mensuel ;
- plafond annuel ;
- frais Wasplex ;
- préavis ;
- délai de grâce des arriérés ;
- nombre maximal de collectes simultanées.

## Critères d’acceptation P014-D

- [ ] un vœu sans coût validé/prestataire/apport complet ne crée pas de snapshot ;
- [ ] un seul snapshot économique existe par vœu ;
- [ ] le bénéficiaire est exclu ;
- [ ] les participants sont réellement éligibles au moment du snapshot ;
- [ ] les mandats/plafonds sont respectés avant engagement ;
- [ ] la somme solidaire est exactement égale au montant collectif ;
- [ ] aucun débit ne se produit avant le préavis ;
- [ ] aucun Solde Fonds/Wallet ne devient négatif ;
- [ ] frais Wasplex séparé, une seule fois, jamais sur débit nul ;
- [ ] débit partiel et arriéré fonctionnent ;
- [ ] la régularisation ne double pas le frais ni la solidarité déjà payée ;
- [ ] toutes les écritures sont idempotentes et équilibrées ;
- [ ] l’interface membre reste grand public et protège le bénéficiaire ;
- [ ] la console admin expose le suivi sans permettre de modifier le Ledger à la main ;
- [ ] tests P014-D ciblés + suite Laravel + frontend complets passent.
