# P015-A — Rapport de chantier : Alertes citoyennes et intégration Feed

**Branche :** `feat/p015-a-alertes-citoyennes-feed`  
**Base :** `main` après la finalisation de Mon Espace (PR #72)  
**Périmètre :** cœur citoyen Alertes, surfaces Feed, revue administrative et configuration de diffusion utile.

## Décisions produit fondatrices

1. La navigation mobile redevient : **Feed — Fonds — Wallet — Alertes — Mon Espace**, avec Wallet au centre.
2. Santé reste un sous-espace d’Alertes (`Pour vous / Communauté / SOS / Santé / Mes déclarations`) et sera implémenté dans P016.
3. Le rail Alertes du Feed est placé **à gauche**, par décision du fondateur, afin de faire face aux actions sociales déjà présentes à droite.
4. Aucun faux accusé de réception police/gendarmerie/secours n’est créé avant P019 (espaces institutionnels).
5. Une déclaration citoyenne constitue le dossier source ; sa projection publique ne contient jamais la position exacte privée.
6. Une alerte ou une astuce intégrée au Feed est **économiquement neutre** : 0 WP, aucun quota publicitaire, aucun budget annonceur, aucune impression publicitaire comptabilisée.

## Domaine Alerts

Deux tables sont ajoutées :

- `alert_declarations` : dossier citoyen, catégorie, situation, priorité, état de revue, résumé public, zone publique, position exacte chiffrée, publication/expiration/résolution et traçabilité de la revue ;
- `alert_feed_settings` : cadence des contenus utiles et activation des surfaces Feed, plus les astuces Wasplex.

Catégories P015-A : objet, document, véhicule, personne, SOS.

Priorités initiales :

- SOS → P0 ;
- personne → P2 ;
- autres déclarations communautaires → P3.

Toute nouvelle déclaration part en `submitted` et `public_visibility=false`. Elle n’est jamais publiée automatiquement.

## Expérience citoyenne

Le panneau Alertes est conçu mobile-first et en langage simple :

- `Pour vous` : accès rapide aux catégories et alertes publiques récentes ;
- `Communauté` : déclaration guidée ;
- `SOS` : chemin visuellement prioritaire, indépendant de tout paiement ;
- `Santé` : emplacement réservé à P016, sans simuler de fonction médicale inexistante ;
- `Mes déclarations` : suivi des statuts et motif explicite lorsqu’une correction est demandée.

La position exacte est facultative, chiffrée en base et exclue de la projection publique.

## Intégration Feed

Trois surfaces sont mises en place :

1. **Cercles Alertes** sous l’onglet Alertes en haut du Feed ;
2. **Rail discret à gauche**, face aux actions sociales à droite ;
3. **Contenu utile plein écran** après une cadence configurable de publicités réellement complétées.

Le compteur de cadence n’avance pas sur une publicité abandonnée, un replay ou une livraison mise en vérification. Le contenu utile n’est proposé qu’après la validation complète d’une publicité, afin de ne jamais interrompre son horloge d’attention.

La cadence est administrable (ex. 3, 5, 10 ou 15 publicités). Le contenu plein écran alterne entre alertes publiques et astuces Wasplex lorsque disponibles et affiche explicitement qu’il ne génère aucun WP.

## Administration

Une section Alertes est intégrée à la console fondateur :

- file de déclarations à vérifier ;
- aperçu de la projection publique ;
- modification du résumé public ;
- choix de la durée de visibilité ;
- publication ou refus ;
- motif obligatoire en cas de refus, visible au citoyen ;
- configuration de la cadence Feed ;
- activation/désactivation des cercles, du rail gauche et du plein écran ;
- gestion des astuces Wasplex.

Les endpoints sensibles exigent une session valide, un MFA récent et les capacités dédiées :

- `admin.alerts.review` ;
- `admin.alerts.configuration.manage`.

Les publications, refus et changements de configuration Feed sont inscrits dans l’audit Identity avec l’acteur et la ressource concernés.

## Sécurité et confidentialité

- position exacte : cast Laravel `encrypted`, masquée par le modèle ;
- aucune position exacte dans `/alerts/public` ;
- aucune publication automatique ;
- décisions admin protégées par capacité + MFA récent ;
- revue administrative tracée (`reviewed_by_account_id`, `reviewed_at`) ;
- refus accompagné d’un motif ;
- aucun raccord institutionnel fictif avant P019.

## Tests ajoutés

- création d’une déclaration avec position exacte chiffrée ;
- absence de publication automatique ;
- priorité P0 pour un SOS ;
- publication admin et projection publique sans donnée privée ;
- obligation d’un motif pour le refus et restitution de ce motif au citoyen ;
- audit des publications/refus ;
- configuration de cadence protégée par capacité et auditée ;
- refus d’accès admin sans capacité dédiée.

## Déploiement

Après fusion :

```bash
cd /root/wasplex-core/apps/platform
php8.4 artisan migrate --force
php8.4 artisan identity:bootstrap-founder admin@wasplex.com
```

Le bootstrap fondateur resynchronise les nouvelles capacités Alertes de manière idempotente.

## Hors périmètre volontaire de P015-A

- capsule médicale et consentements Santé → P016 ;
- portails police, gendarmerie, secours, établissements et professionnels → P019 ;
- accusés de réception et prises en charge institutionnelles réelles → raccord final P015/P016 après P019 ;
- contenus partenaires structurés avec workflow éditorial dédié → évolution ultérieure de la diffusion utile.
