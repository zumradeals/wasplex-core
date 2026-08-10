# Audit de la verticale publicitaire — 10 août 2026

## Résultat

Le parcours financier et la diffusion existaient déjà, mais le ciblage visible dans le Studio ne couvrait que la classe économique et un code pays libre. Les taxonomies volontaires du Profil intelligent étaient collectées sans jamais influencer le Matching. Ce décalage expliquait pourquoi une campagne paraissait incomplète malgré une verticale Feed/Wallet déjà fonctionnelle.

## Annonceur

- Remplacement du code pays libre par une sélection explicite de pays.
- Ajout des critères volontaires actifs : genre déclaré, centres d’intérêt, usages, équipement, projets, situation et zone approximative.
- Autosauvegarde de `profile_taxonomies` dans la version de campagne.
- Catalogue exposé en lecture seule ; aucun utilisateur ni aucune identité n’est révélé.
- Une seule valeur de genre peut être choisie par campagne.

## Administration

- Les classes, le pays et les taxonomies ciblées sont visibles dans le dossier de revue.
- Une taxonomie inexistante ou suspendue rend la configuration invalide.
- Santé, Alertes, Fonds et KYC restent structurellement exclus du catalogue publicitaire.

## Utilisateur et Matching

- Le pays et la classe restent des exclusions dures.
- Une campagne avec critères de profil exige le consentement `smart_profile_usage`.
- Une zone approximative exige en plus `approximate_location_targeting`.
- Tous les critères sélectionnés doivent correspondre à des déclarations actives de l’utilisateur.
- Sans consentement, la décision est `withheld`, jamais implicitement acceptée.
- Le Feed ne reçoit que les campagnes approuvées, financées et éligibles.

## Finance

Le scénario de référence couvre : financement de la campagne, réservation de l’enveloppe, livraison Feed, capture de la consommation, débit de l’enveloppe annonceur et crédit unique du Wallet utilisateur. Le rejeu de la complétion ne recrédite pas le Wallet.

## Preuves automatisées

- `CampaignWizardTest` : catalogue de ciblage et persistance d’un intérêt.
- `MatchingEligibilityTest` : consentement explicite, intérêt déclaré et décision éligible.
- `FeedVerticalTest` : solde annonceur après financement, livraison, capture et crédit utilisateur idempotent.

## Limites conservées

- L’estimation de portée reste fondée sur pays + classes économiques. Les taxonomies affinent réellement le Matching mais ne réduisent pas encore l’intervalle agrégé affiché ; l’interface doit donc présenter ce chiffre comme une estimation haute tant qu’un estimateur d’intersection anonymisé n’est pas livré.
- La liste pays proposée couvre les marchés principaux actuels et peut être élargie sans changer le modèle métier.
