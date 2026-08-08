# P015 — Refonte visuelle Studio Annonceur (5 écrans)

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `b51b64f` (P014 mergé — refonte visuelle mobile)
**Dépendances :** P003 (Wallet annonceur), P005 (Marques), P006 (Campagnes), P007 (Revue), P012
(reporting Campagnes).
**Statut :** proposed

---

## 1. Origine

Le fondateur a fourni un second dossier de handoff (« Claude Design ») couvrant le Studio
Annonceur (web, desktop-first) : 5 écrans — Tableau de bord, Marques, Campagnes, Wallet
annonceur, Équipe — avec une direction visuelle claire (fond clair, sidebar sombre) distincte
de l'app mobile.

## 2. Constat de départ

Contrairement à P014 (mobile), la quasi-totalité du Studio Annonceur est **déjà fonctionnelle** :
`AdvertiserShell.vue` a déjà la bonne structure de navigation (5 sections, sidebar + contenu
clair) ; Marques, Campagnes et Wallet annonceur sont déjà des composants réels connectés à de
vrais services backend testés (P003/P005/P006). Ce chantier est donc majoritairement un
**restylage** de composants existants, plus deux constructions réellement neuves :

- **Tableau de bord** : n'existe pas du tout aujourd'hui (placeholder "bientôt disponible").
- **Équipe** : liste en lecture seule inline dans le shell ; aucune UI d'invitation n'existe,
  bien que l'API d'invitation soit complète côté backend.

## 3. Décisions explicites

### 3.1. Graphique « Vues — 14 derniers jours »

Aucune donnée quotidienne n'existe côté backend (`CampaignsReportingContract` n'expose que des
compteurs cumulés par campagne, jamais ventilés par jour).

**Décision du fondateur** : remplacer le graphique en barres par un total réel (vues cumulées,
dérivées de `feed.completed` sommé sur `GET /advertiser/campaigns/report`), avec une mention
explicite que la ventilation quotidienne n'existe pas encore. Aucun chiffre par jour n'est
inventé.

### 3.2. Wallet annonceur — « Dépensé ce mois »

La maquette montre une statistique « Dépensé ce mois ». Aucune donnée d'historique de
consommation horodatée par mois n'existe (`budget_captured_minor` est un cumul total par
campagne, sans ventilation temporelle). Renommé en **« Dépensé au total »** — valeur réelle
(somme de `budget_captured_minor` sur toutes les campagnes), pas de faux chiffre mensuel.

### 3.3. Équipe — invitation sans canal de livraison

Le backend (`OrganizationInvitationsController::store`) renvoie directement le jeton
d'invitation dans la réponse HTTP — il n'existe aucun adaptateur d'envoi d'email/SMS
(documenté dès P001). Il n'existe non plus **aucun écran d'acceptation d'invitation** côté
frontend (recherché explicitement, absent). L'écran Équipe affiche donc le code d'invitation
généré avec un bouton « Copier » et une note explicite indiquant qu'il doit être transmis
manuellement — pas de simulation d'un envoi qui n'a pas lieu. **Limite documentée** : la
personne invitée n'a aujourd'hui aucun moyen de saisir ce code dans l'interface (aucun écran
d'acceptation n'existe) ; ce jeton n'est donc utilisable que via l'API directement pour
l'instant.

### 3.4. Noms de campagne affichés

Le modèle `Campaign` n'a pas de champ `name` — le libellé provient du titre créatif
(`creative_configuration.title`) ou, à défaut, du libellé de l'objectif (déjà le comportement
de `CampaignsPanel.vue`). Le tableau de bord réutilise la même règle (factorisée dans
`resources/js/lib/campaignObjectives.ts` pour éviter la duplication).

## 4. Périmètre inclus

- Sidebar et en-tête `AdvertiserShell.vue` : icônes SVG ligne (au lieu des emoji), logo
  transparent, sélecteur d'espace en pilule.
- Marques : restylage des cartes existantes (badges de statut, zone de dépôt de fichiers en
  vrai glisser-déposer plutôt qu'un simple `&lt;input type="file"&gt;`) — aucune logique changée.
- Campagnes : stepper à cercles numérotés reliés par des traits (remplace la rangée de pilules),
  aperçu téléphone avec ligne de progression réelle (dérivée de l'étape courante du formulaire),
  restylage des cartes — logique du formulaire/autosauvegarde inchangée.
- Wallet annonceur : ajout d'une carte de statistiques réelles (dépensé au total, campagnes
  actives — dérivées de `GET /advertiser/campaigns/report`), état vide explicite pour
  l'historique des dépôts.
- **Nouveau** `AdvertiserDashboardPanel.vue` : 4 cartes de métriques réelles, répartition du
  budget par campagne (réel), tableau des campagnes (réel) — remplace le placeholder.
- **Nouveau** `TeamPanel.vue` : formulaire d'invitation réel (auto-détection email/téléphone),
  affichage du code d'invitation généré, liste des membres réels avec rôle (`title`).

## 5. Périmètre exclu

- Tout nouveau module backend (suivi quotidien des vues, historique de dépense mensuel, canal
  d'envoi d'invitation par email/SMS, écran d'acceptation d'invitation).
- Les typographies et guidelines de marque (`PUT /advertiser/brands/{id}/typographies|guidelines`)
  — endpoints réels mais non montrés dans la maquette fournie, laissés hors périmètre.
- Le catalogue de prix admin, la file de revue admin — non concernés par cette maquette.

## 6. Tests

- `npm run types:check`, `npx eslint .`, `npm run format:check`.
- `php artisan test` (non-régression — aucun changement backend attendu).
- Captures Playwright desktop (1280×800) des 5 écrans.

## 7. Chantier suivant recommandé

Selon décision du fondateur : construire le suivi quotidien des vues et/ou l'écran
d'acceptation d'invitation (les deux limites documentées ci-dessus).
