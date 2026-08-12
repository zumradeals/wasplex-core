# P015-A — Alertes citoyennes et intégration Feed

**Branche :** `feat/p015-a-alertes-citoyennes-feed`  
**Base :** `main` après PR #72 (`4924328`)  
**Source produit :** `docs/02-module-alertes-sante-wasplex.md`  
**Statut :** in_progress

## Objectif

Livrer le cœur citoyen d’Alertes avant les portails institutionnels : créer une déclaration une seule fois, protéger les données privées, suivre son état et projeter uniquement une version sûre dans les surfaces publiques et le Feed.

## Décisions fondateur du 2026-08-12

1. La navigation mobile officielle est restaurée à cinq entrées : `Feed — Fonds — Wallet — Alertes — Mon Espace`, Wallet restant au centre.
2. Dans le Feed, toucher `Alertes` ouvre des cercles d’alertes actives récentes, dans l’esprit des cercles Live.
3. Le rail d’alertes est placé **à gauche**, en miroir des actions sociales situées à droite. C’est un écart volontaire avec la première rédaction de `docs/02`, qui positionnait le rail à droite.
4. Le Feed peut insérer un contenu utile après une cadence configurable de publicités (valeur initiale : 5). Les contenus utiles peuvent être une alerte publiée, une astuce Wasplex, puis plus tard un avis officiel ou un contenu partenaire approuvé.
5. Un contenu utile est économiquement neutre : aucun WP, aucune consommation de quota, aucune impression publicitaire et aucun budget annonceur.
6. Santé apparaît à l’intérieur d’Alertes mais ses fonctions médicales réelles attendent P016.
7. Les états institutionnels réels (transmis, reçu, pris en charge par police/gendarmerie/secours) attendent P019. P015-A ne simule aucune prise en charge.

## Périmètre P015-A

### Citoyen
- catégories : objet, document, véhicule, personne, SOS ;
- situations : perdu, trouvé, volé, disparu, urgence, autre ;
- titre, description, ville, zone publique approximative ;
- position exacte facultative, chiffrée et jamais projetée publiquement ;
- statut initial `submitted` ;
- historique `Mes déclarations`.

### Publication
- aucune déclaration citoyenne n’est publique automatiquement ;
- une publication Wasplex produit une projection publique minimale ;
- expiration et résolution retirent l’alerte des surfaces publiques.

### Feed
- cercles Alertes en haut ;
- rail discret à gauche ;
- fiche compacte au toucher ;
- contenu utile plein écran après N publicités réellement complétées ;
- le compteur ne progresse pas sur replay, abandon ou livraison mise en vérification ;
- le contenu utile est présenté après la fin économique de la publicité précédente et avant le chargement de la suivante.

### Configuration
- intervalle de contenu utile ;
- activation/désactivation des cercles ;
- activation/désactivation du rail gauche ;
- activation/désactivation du plein écran ;
- liste d’astuces Wasplex.

## Hors périmètre P015-A

- portail police/gendarmerie/secours ;
- accusé institutionnel réel ;
- interopérabilité commissariats/brigades ;
- correspondances et restitutions protégées avancées ;
- capsule médicale d’urgence et dossier Santé (P016) ;
- visibilité renforcée payante ;
- pièces jointes et preuves multimédia avancées ;
- géolocalisation temps réel.

## Sécurité

- position exacte chiffrée au repos ;
- projection publique sans position exacte ni coordonnées privées ;
- publication séparée de la soumission ;
- capacités admin dédiées : `admin.alerts.review`, `admin.alerts.configuration.manage` ;
- MFA récent exigé pour la revue/configuration admin.

## Critères de validation

- migration PostgreSQL réversible ;
- formatage frontend et PHP verts ;
- ESLint, Vue/TypeScript et build verts ;
- tests Laravel/PostgreSQL/Redis verts ;
- test explicite : une déclaration `submitted` n’apparaît jamais dans `/alerts/public` ;
- test explicite : la position exacte est chiffrée et absente des réponses API ;
- test explicite : un SOS est P0 sans champ financier ;
- aucune régression du cycle d’attention publicitaire.
