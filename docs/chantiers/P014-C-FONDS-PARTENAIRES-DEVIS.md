# P014-C — Fonds : partenaires P019, devis et sélection fournisseur

## Objectif

Raccorder Fonds au socle professionnel P019 sans créer un second système de comptes partenaires. Un vœu validé peut être transmis explicitement à plusieurs organisations vérifiées afin d’obtenir des devis réels, comparables et auditables, puis sélectionner un fournisseur.

## Doctrine

- P019 reste la racine d’identité, de vérification, de rôles, de capacités et de territoire.
- Fonds reste propriétaire des demandes de devis, devis, sélection et futurs ordres/paiements.
- Aucun prestataire ne recherche librement les membres ou les vœux Wasplex.
- Un prestataire ne voit que les dossiers explicitement adressés à son organisation.
- La projection partenaire exclut l’identité du bénéficiaire, ses documents privés, son Wallet, son Solde Fonds et ses données Santé.
- Une sélection de devis valide un coût réel mais ne déclenche encore ni collecte automatique ni paiement fournisseur.

## Parcours admin

1. Vœu approuvé.
2. Wasplex affiche les espaces P019 vérifiés et éligibles dans le même pays.
3. L’admin choisit un ou plusieurs prestataires.
4. Une demande de devis horodatée est créée pour chaque organisation.
5. Les réponses sont comparées par prix, délai, validité et conditions.
6. Un seul devis est sélectionné.
7. Le coût du vœu devient `validated_amount_minor` et le fournisseur devient `provider_organization_id`.
8. L’apport personnel requis est recalculé selon la version du programme acceptée, sans effacer un apport déjà constitué.

## Parcours partenaire

Dans Wasplex Pro, les rôles autorisés voient l’onglet Fonds :

- liste des demandes reçues par leur organisation ;
- projection minimale du besoin ;
- budget indicatif et localisation non sensible ;
- formulaire de devis ;
- prix, délai, date de validité, conditions et note ;
- possibilité de décliner une demande encore ouverte ;
- statut final sélectionné / non retenu.

## Espaces P019 éligibles V1

- `partner`
- `merchant`
- `service_provider`
- `healthcare_institution`
- `financial_operator`

Les espaces doivent être `verified`, appartenir à une organisation active et être dans le même pays que le vœu pour P014-C.

## Capacités P019

- `professional.funds.quote.view`
- `professional.funds.quote.respond`

Les capacités sont scopées à l’organisation. Les rôles déjà existants reçoivent un backfill idempotent ; les nouvelles attributions passent par `ProfessionalWorkspaceController`.

## Modèle de données

### `fund_quote_requests`

Lie un vœu à un espace professionnel et à son organisation. États : `requested`, `responded`, `declined`, `expired`, `selected`, `not_selected`.

### `fund_wish_quotes`

Un devis par demande : montant, devise, délai, validité, conditions, notes, auteur et statut.

### Extension `fund_wishes`

- `selected_quote_id`
- `provider_organization_id`
- `validated_amount_minor`
- `cost_validated_at`

## Hors périmètre

P014-C ne crée pas encore : commande, acompte, paiement par jalon, preuve de livraison, garantie, litige, collecte solidaire automatique ou réserve. Ces fonctions restent dans les lots suivants.

## Critères d’acceptation

- aucun devis d’une organisation non vérifiée ;
- aucune consultation cross-organization ;
- aucune identité bénéficiaire dans la projection partenaire ;
- plusieurs demandes possibles, mais une seule sélection finale ;
- devis expiré non sélectionnable ;
- coût validé et fournisseur persistés ;
- apport requis recalculé sans réduire en dessous de l’apport déjà constitué ;
- capacités P019 effectives pour rôles existants et futurs ;
- UI Wasplex Pro et admin intégrées à la famille visuelle existante ;
- CI complète verte.
