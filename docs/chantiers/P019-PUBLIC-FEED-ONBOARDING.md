# P019 — Feed public invité et onboarding d'acquisition

## Objectif

Faire de `wasplex.com` une expérience produit immédiate pour le visiteur non connecté :

1. afficher le Feed des campagnes explicitement rendues publiques par l'annonceur ;
2. simuler un gain WP pour expliquer la proposition de valeur sans créer d'écriture économique ;
3. convertir vers la connexion ou l'inscription ;
4. demander quelques centres d'intérêt et consentements volontaires ;
5. faire découvrir l'Espace Annonceur sans créer un second système d'organisation.

## Invariants économiques

Le Feed invité réutilise exclusivement les campagnes `members_public`, approuvées et dans leur période de diffusion.

Une vue invitée :

- ne crée aucune récompense réelle ;
- ne réserve et ne consomme aucun budget du Feed rémunéré ;
- ne crée aucune écriture Ledger ;
- ne consomme aucun quota membre ;
- peut uniquement alimenter la télémétrie de portée publique déjà existante.

Le solde présenté à l'invité est stocké dans `sessionStorage` et porte explicitement le libellé de simulation. Sa valeur par défaut est configurée par `FEED_GUEST_SIMULATED_REWARD_MINOR`.

## Parcours public

`/` affiche `Identity/GuestFeed`.

Le Feed public reprend les campagnes visibles fournies par `PublicCampaignService::feed()`. La complétion d'une vidéo ou trois secondes visibles sur une image peuvent créditer une seule fois par campagne le compteur simulé de la session navigateur.

Les CTA renvoient vers :

- `/login` pour un compte existant ;
- `/register` pour commencer l'inscription.

## Parcours d'authentification

La belle identité visuelle historique de `Identity/Landing` est conservée.

- `/login` rend `Identity/Landing` en mode `login`.
- `/register` rend `Identity/Landing` en mode `register`.

Le composant `PhoneQuickConnect` accepte téléphone ou email.

Après création du compte et ouverture de session, l'inscription continue sans rupture :

1. intention : découvrir/gagner, promouvoir son activité, ou les deux ;
2. centres d'intérêt issus des vraies taxonomies `interest.*` du Profil intelligent ;
3. consentements publiés liés à la personnalisation publicitaire et à l'usage du Profil intelligent ;
4. entrée dans le Feed ou découverte de l'Espace Annonceur.

Les consentements restent explicites, facultatifs et révocables. Ils ne sont jamais accordés automatiquement.

## Espace annonceur

P019 ne crée aucune nouvelle logique annonceur.

L'utilisateur qui manifeste une intention annonceur est renvoyé vers `Mon Espace`, où le composant existant `BecomeAdvertiserPanel` reste la source de vérité pour créer l'organisation et ouvrir le Studio.

## Tests attendus

- `/` rend le Feed invité pour un visiteur ;
- `/login` et `/register` utilisent la même identité visuelle avec le mode demandé ;
- un membre authentifié qui visite `/` est redirigé vers son espace ;
- `PublicCampaignService::feed()` n'expose ni campagne privée ni campagne suspendue ;
- les routes publiques de visite conservent leur invariant : aucune récompense Feed et aucune consommation d'enveloppe.
