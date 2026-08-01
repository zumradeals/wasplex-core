# WASPLEX — IDENTITÉ VISUELLE FONDATRICE

**Fichier cible dans le nouveau dépôt :** `docs/00-fondations/00-identite-visuelle-wasplex.md`  
**Statut :** Spécification produit et design à appliquer dès l’initialisation du dépôt  
**Portée :** Application utilisateur, Feed, Wallet, espace annonceur, administration, institutions et futurs modules  
**Priorité :** Fondatrice  
**Principe :** Aucun module Wasplex ne crée sa propre identité visuelle en dehors de ce socle

---

# 1. RÔLE DE CE DOCUMENT

Ce document définit l’identité visuelle commune de la nouvelle construction Wasplex.

Il ne constitue pas une loi documentaire générale. Il fournit les choix concrets dont le code, les composants et les écrans ont besoin :

- logo et mascotte ;
- palette de couleurs ;
- thèmes sombre et clair ;
- typographie ;
- espacements ;
- formes ;
- bordures ;
- ombres ;
- icônes ;
- langage de mouvement ;
- langage sonore ;
- affichage des montants ;
- états fonctionnels ;
- règles d’héritage entre les modules ;
- structure des assets ;
- tokens à exposer dans le code ;
- critères de validation.

Cette identité doit être intégrée avant le développement du noyau, afin que le noyau, le Feed, le Wallet et tous les futurs tableaux de bord héritent du même langage.

---

# 2. SOURCE DE L’IDENTITÉ

La nouvelle identité ne doit pas être inventée arbitrairement.

Elle reprend et consolide les meilleurs éléments déjà présents dans Wasplex :

1. le logo historique Wasplex ;
2. la mascotte martin-pêcheur ;
3. le bleu nuit des interfaces mobiles ;
4. le bleu technologique ;
5. le cyan de navigation ;
6. l’orange lié au Wallet et à l’action ;
7. l’or lié à la valeur gagnée ;
8. l’interface immersive de l’ancienne application ;
9. le système de couleurs déjà introduit dans le dépôt récent ;
10. l’animation de l’oiseau qui transporte une valeur vers le Wallet.

La nouvelle construction doit toutefois corriger :

- les incohérences de couleurs entre écrans ;
- les valeurs codées directement dans les composants ;
- les interfaces professionnelles trop génériques ;
- les cartes sans profondeur ;
- les contrastes insuffisants ;
- les effets trop longs ou bloquants ;
- les logos trop petits ;
- les usages décoratifs non reliés à une fonction ;
- les différences de style non justifiées entre utilisateur, annonceur, administration et institutions.

---

# 3. ESSENCE DE MARQUE

Wasplex doit être perçu comme :

- vivant ;
- technologique ;
- accessible ;
- africain sans être folklorique ;
- fiable ;
- rapide ;
- gratifiant ;
- humain ;
- transparent ;
- moderne ;
- utile ;
- capable de transformer le temps et l’attention en valeur visible.

## 3.1. Impression recherchée

L’utilisateur doit ressentir :

> « Je suis dans une application moderne, fluide et vivante. Ce que je fais produit un résultat visible. Je comprends où va la valeur et je contrôle mon espace. »

L’annonceur doit ressentir :

> « Je peux créer, financer et suivre une campagne sans compétence technique. L’outil est clair, puissant et rassurant. »

L’administrateur doit ressentir :

> « Je pilote réellement le système. Les risques, les opérations et les décisions importantes sont visibles. »

L’institution doit ressentir :

> « Je travaille dans un espace sérieux, rapide et opérationnel, clairement distinct du divertissement publicitaire. »

---

# 4. SYSTÈME DE LOGO

## 4.1. Symbole officiel

Le symbole principal de Wasplex est un **martin-pêcheur**.

La version officielle actuelle représente :

- un martin-pêcheur perché ;
- un poisson dans le bec ;
- le nom « WasPlex » intégré ou associé au visuel selon la variante.

Le martin-pêcheur n’est pas une illustration secondaire. Il joue plusieurs rôles :

- identité de marque ;
- mascotte ;
- repère visuel dans l’application ;
- acteur de certaines micro-interactions ;
- messager de valeur entre une action et le Wallet ;
- symbole de rapidité, de précision et de capture de valeur.

## 4.2. Signification produit

Le martin-pêcheur représente :

- l’attention ;
- la précision ;
- la rapidité ;
- la capacité à saisir une opportunité ;
- le passage d’une action à une récompense ;
- la circulation de la valeur dans l’écosystème.

Le poisson ne doit pas être interprété comme un simple détail décoratif. Il symbolise la valeur captée ou transportée.

## 4.3. Variantes à préparer

Le nouveau dépôt doit contenir les variantes suivantes :

```text
public/brand/
├── wasplex-logo-full.svg
├── wasplex-logo-full.png
├── wasplex-logo-horizontal.svg
├── wasplex-logo-horizontal.png
├── wasplex-mascot.svg
├── wasplex-mascot.png
├── wasplex-mascot-monochrome-light.svg
├── wasplex-mascot-monochrome-dark.svg
├── wasplex-wordmark-light.svg
├── wasplex-wordmark-dark.svg
├── wasplex-app-icon-512.png
├── wasplex-app-icon-192.png
├── wasplex-favicon.svg
└── README.md
```

Les fichiers vectoriels sont prioritaires.

Une image PNG ne doit être utilisée que lorsque le contexte technique ne permet pas le SVG.

## 4.4. Usages

### Logo complet

À utiliser pour :

- écran de lancement ;
- inscription ;
- connexion ;
- page publique ;
- documents de marque ;
- espace de présentation ;
- page de maintenance.

### Logo horizontal

À utiliser pour :

- en-têtes desktop ;
- espace annonceur ;
- administration ;
- institutions ;
- navigation latérale ;
- documents commerciaux.

### Mascotte seule

À utiliser pour :

- Feed ;
- barre supérieure mobile ;
- icône de chargement spécifique ;
- micro-interaction de crédit ;
- notifications de valeur ;
- espaces compacts.

### Icône d’application

À utiliser pour :

- PWA ;
- écran d’accueil ;
- favicon ;
- notifications ;
- raccourcis.

## 4.5. Zone de protection

Autour du logo, conserver une marge minimale égale à :

- 25 % de la largeur de la mascotte pour les petits usages ;
- 50 % de la largeur de la mascotte pour les pages de présentation.

Aucun texte, bouton, bordure ou autre logo ne doit entrer dans cette zone.

## 4.6. Tailles minimales

- Mascotte dans la navigation mobile : `36 px`
- Mascotte dans un en-tête standard : `40 px`
- Logo horizontal dans un portail professionnel : hauteur minimale `32 px`
- Logo complet sur l’authentification : hauteur minimale `88 px`
- Icône PWA : sources haute définition obligatoires

## 4.7. Interdictions

Il est interdit de :

- étirer le logo ;
- modifier son ratio ;
- découper l’oiseau ;
- appliquer une couleur arbitraire ;
- reproduire le logo à partir d’une capture d’écran ;
- ajouter un fond blanc non prévu autour d’un PNG ;
- mélanger plusieurs variantes du logo dans le même en-tête ;
- utiliser la mascotte comme simple décoration répétée ;
- faire jouer l’animation de l’oiseau sans résultat fonctionnel réel ;
- utiliser l’oiseau pour annoncer un crédit non confirmé.

---

# 5. PALETTE PRINCIPALE

La palette officielle est organisée autour de cinq couleurs de marque :

1. bleu nuit ;
2. bleu technologique ;
3. cyan ;
4. orange ;
5. or.

Le thème sombre constitue l’identité principale de l’application utilisateur.

Le thème clair reste disponible pour les surfaces professionnelles, les tableaux, les formulaires longs, l’impression et certaines interfaces administratives.

---

# 6. THÈME SOMBRE — IDENTITÉ PRINCIPALE

## 6.1. Couleurs de base

| Token | Valeur | Usage |
|---|---:|---|
| `brand.navy.950` | `#07182D` | Fond principal, Feed, écran immersif |
| `brand.navy.850` | `#0E2542` | Cartes, navigation inférieure, surfaces |
| `brand.navy.750` | `#173251` | Surface élevée, champ actif, carte secondaire |
| `brand.border.dark` | `#35506D` | Bordures et séparateurs |
| `brand.white.soft` | `#F5F8FC` | Texte principal |
| `brand.text.muted.dark` | `#A9B7C8` | Texte secondaire |
| `brand.blue.dark` | `#4FA3FF` | Action, lien, statut actif |
| `brand.cyan.dark` | `#2BC4DE` | Navigation active, technologie |
| `brand.orange.dark` | `#FF9A3D` | Wallet, action de valeur, attention |
| `brand.gold.dark` | `#F2C14E` | Gain, récompense, niveau, montant confirmé |

## 6.2. Hiérarchie des surfaces

```text
Fond immersif      #07182D
Surface principale #0E2542
Surface élevée     #173251
Bordure            #35506D
Texte principal    #F5F8FC
Texte secondaire   #A9B7C8
```

Une carte sombre ne doit pas être créée avec un bleu choisi au hasard. Elle doit utiliser l’un de ces niveaux.

## 6.3. Dégradés autorisés

### Action technologique

```css
linear-gradient(135deg, #4FA3FF 0%, #2BC4DE 100%)
```

### Valeur gagnée

```css
linear-gradient(135deg, #FF9A3D 0%, #F2C14E 100%)
```

### Progression Feed

```css
linear-gradient(90deg, #4FA3FF 0%, #F2C14E 100%)
```

### Carte premium

```css
linear-gradient(145deg, #173251 0%, #0E2542 55%, #07182D 100%)
```

Les dégradés ne doivent pas être utilisés sur toutes les cartes.

Ils servent aux éléments importants :

- progression ;
- récompense ;
- niveau ;
- action principale ;
- état actif ;
- mise en valeur exceptionnelle.

---

# 7. THÈME CLAIR — SURFACES PROFESSIONNELLES

## 7.1. Couleurs de base

| Token | Valeur | Usage |
|---|---:|---|
| `light.canvas` | `#F5F7FA` | Fond général |
| `light.surface` | `#FFFFFF` | Carte, panneau, table |
| `light.raised` | `#F8FAFC` | Surface secondaire |
| `light.text.primary` | `#10233F` | Texte principal |
| `light.text.secondary` | `#53657D` | Texte secondaire |
| `light.border` | `#CBD5E1` | Bordures |
| `brand.blue.light` | `#075CCF` | Action principale |
| `brand.cyan.light` | `#007F9F` | Navigation active |
| `brand.orange.light` | `#C75100` | Wallet et action de valeur |
| `brand.gold.light` | `#936800` | Valeur et statut premium |

## 7.2. Règle des portails professionnels

L’espace annonceur, l’administration et les institutions peuvent utiliser un thème clair pour les données denses, mais ils doivent conserver :

- un en-tête ou une navigation bleu nuit ;
- le logo Wasplex clairement visible ;
- les accents de marque ;
- des cartes avec profondeur ;
- des chiffres importants valorisés ;
- des états colorés cohérents ;
- un mode sombre disponible à terme.

Une interface professionnelle ne doit jamais ressembler à un tableau HTML générique sans identité.

---

# 8. COULEURS SÉMANTIQUES

| État | Sombre | Clair | Signification |
|---|---:|---:|---|
| Succès | `#42D392` | `#137A50` | Action confirmée |
| Avertissement | `#F4B942` | `#9A5B00` | Attention nécessaire |
| Erreur | `#FF6B61` | `#B42318` | Échec ou blocage |
| Information | `#70B7FF` | `#075CCF` | Information utile |
| En attente | `#E7CF61` | `#6B5B00` | Traitement en cours |
| Inconnu | `#A9B7C8` | `#53657D` | Résultat non disponible |

## 8.1. Différence entre succès et gain

Le vert signifie :

- opération réussie ;
- validation ;
- confirmation.

L’or signifie :

- valeur gagnée ;
- récompense ;
- montant crédité ;
- statut premium.

Un crédit Wallet peut associer :

- vert pour le statut confirmé ;
- or pour le montant ;
- orange pour l’icône ou la destination Wallet.

---

# 9. RÈGLES D’UTILISATION DES COULEURS

## 9.1. Bleu

Utiliser pour :

- action principale générale ;
- liens ;
- bouton de confirmation standard ;
- état actif ;
- technologie ;
- progression fonctionnelle.

## 9.2. Cyan

Utiliser pour :

- onglet actif ;
- navigation ;
- repère contextuel ;
- information technologique ;
- éléments sociaux ou communautaires.

## 9.3. Orange

Utiliser pour :

- Wallet ;
- dépôt ;
- retrait ;
- circulation de valeur ;
- bouton central Wallet ;
- action financière importante.

L’orange ne doit pas devenir la couleur de tous les boutons.

## 9.4. Or

Utiliser pour :

- récompense confirmée ;
- gain ;
- niveau premium ;
- badge ;
- progression arrivée à valeur ;
- animation de crédit.

L’or ne doit pas servir à signaler une opération seulement probable.

## 9.5. Rouge

Utiliser uniquement pour :

- suppression ;
- refus ;
- fraude ;
- erreur critique ;
- danger ;
- déconnexion lorsqu’elle est confirmée.

## 9.6. Vert

Utiliser uniquement pour :

- succès ;
- confirmation ;
- transaction validée ;
- état sûr ;
- objectif atteint.

---

# 10. TYPOGRAPHIE

## 10.1. Police principale

La police principale de Wasplex est :

```css
font-family:
  Inter,
  ui-sans-serif,
  system-ui,
  -apple-system,
  "Segoe UI",
  Roboto,
  Arial,
  sans-serif;
```

Inter doit être chargée de manière performante.

Le système doit conserver une pile de secours correcte lorsque la police n’est pas disponible.

## 10.2. Échelle typographique mobile

| Style | Taille | Interligne | Graisse |
|---|---:|---:|---:|
| Display | 32 px | 38 px | 750 |
| H1 | 28 px | 34 px | 700 |
| H2 | 22 px | 28 px | 700 |
| H3 | 18 px | 24 px | 650 |
| Corps principal | 16 px | 24 px | 400 |
| Corps compact | 14 px | 20 px | 400 |
| Libellé | 13 px | 18 px | 600 |
| Légende | 12 px | 16 px | 500 |
| Micro | 10 px | 14 px | 600 |

## 10.3. Montants

Les montants doivent utiliser :

```css
font-variant-numeric: tabular-nums;
```

Règles :

- montant principal : graisse 700 ou 750 ;
- devise plus petite mais lisible ;
- séparateur de milliers localisé ;
- signe positif affiché seulement pour un gain ;
- jamais plus de précision que la monnaie ne l’exige ;
- valeur inconnue affichée comme inconnue, jamais comme zéro.

## 10.4. Casse

- titres : casse naturelle ;
- sur-titres courts : capitales espacées ;
- boutons : casse naturelle ;
- montants : jamais en capitales ;
- labels techniques internes : jamais exposés à l’utilisateur.

---

# 11. ESPACEMENTS

Le système utilise une grille de base de `4 px`.

Tokens recommandés :

```text
space.1  = 4 px
space.2  = 8 px
space.3  = 12 px
space.4  = 16 px
space.5  = 20 px
space.6  = 24 px
space.8  = 32 px
space.10 = 40 px
space.12 = 48 px
space.16 = 64 px
```

## 11.1. Marges mobiles

- marge horizontale minimale : `16 px`
- écran très étroit : `12 px`
- distance entre grandes sections : `24 à 32 px`
- distance entre cartes : `12 à 16 px`
- contenu interne d’une carte : `16 à 24 px`

## 11.2. Zones tactiles

Aucune action importante ne doit avoir une zone tactile inférieure à :

```text
44 × 44 px
```

La cible recommandée est :

```text
48 × 48 px
```

---

# 12. FORMES ET RAYONS

Tokens :

```text
radius.xs   = 8 px
radius.sm   = 10 px
radius.md   = 14 px
radius.lg   = 18 px
radius.xl   = 24 px
radius.full = 999 px
```

Usages :

- bouton compact : `10 à 14 px`
- champ : `14 px`
- carte standard : `18 px`
- grande carte utilisateur : `24 px`
- badge : rayon complet
- avatar : cercle ou carré fortement arrondi

Les formes trop carrées donnent une impression administrative froide.

Les formes excessivement arrondies sur toutes les surfaces donnent une impression enfantine.

---

# 13. BORDURES ET ÉLÉVATION

## 13.1. Bordures

- épaisseur standard : `1 px`
- carte sélectionnée : `1.5 à 2 px`
- focus : `2 à 3 px`
- bordure sombre : `#35506D`
- bordure claire : `#CBD5E1`

## 13.2. Ombres

### Carte sombre

```css
box-shadow:
  0 12px 32px rgba(0, 0, 0, 0.22),
  inset 0 1px 0 rgba(255, 255, 255, 0.03);
```

### Carte claire

```css
box-shadow:
  0 8px 24px rgba(16, 35, 63, 0.08);
```

### Wallet actif

```css
box-shadow:
  0 0 0 3px #0E2542,
  0 6px 18px rgba(199, 81, 0, 0.50);
```

### Gain confirmé

```css
box-shadow:
  0 0 24px rgba(242, 193, 78, 0.38);
```

Les ombres doivent renforcer la hiérarchie, pas créer un effet décoratif permanent.

---

# 14. ICONOGRAPHIE

## 14.1. Style

Utiliser une famille d’icônes cohérente :

- traits réguliers ;
- angles arrondis ;
- lisibilité à 20–24 px ;
- formes simples ;
- pas de mélange d’icônes remplies et contour sans raison.

## 14.2. Tailles

- navigation mobile : `22 à 24 px`
- bouton : `18 à 20 px`
- carte rapide : `24 à 28 px`
- état vide : `40 à 56 px`
- grande action : `28 à 32 px`

## 14.3. Règle d’accompagnement

Une icône seule est acceptable uniquement lorsque son sens est évident et qu’un libellé accessible existe.

Les actions importantes doivent afficher :

- icône ;
- texte ;
- état si nécessaire.

---

# 15. LANGAGE DE MOUVEMENT

Le mouvement est une partie centrale de Wasplex.

Il doit rendre visibles :

- la progression ;
- la confirmation ;
- la circulation de valeur ;
- le passage d’un état à un autre ;
- le crédit Wallet.

Il ne doit pas ralentir l’utilisateur.

## 15.1. Durées

```text
motion.instant   = 80–120 ms
motion.micro     = 140–180 ms
motion.standard  = 200–260 ms
motion.panel     = 280–360 ms
motion.count     = 450–650 ms
motion.reward    = 900–1400 ms
```

## 15.2. Courbes

```css
--ease-standard: cubic-bezier(0.2, 0, 0, 1);
--ease-emphasized: cubic-bezier(0.2, 0.8, 0.2, 1);
--ease-reward: cubic-bezier(0.16, 1, 0.3, 1);
```

## 15.3. Crédit Wallet

Lorsqu’un crédit est réellement confirmé :

1. le moteur reçoit la confirmation ;
2. le montant apparaît ;
3. le compteur Wallet rejoint le vrai solde ;
4. l’icône Wallet pulse ;
5. l’or illumine brièvement la zone ;
6. la mascotte peut transporter la valeur vers le Wallet ;
7. un son court peut être joué ;
8. une vibration légère peut être déclenchée ;
9. l’utilisateur peut continuer à défiler.

L’effet ne doit pas bloquer le Feed.

## 15.4. Animation du martin-pêcheur

L’oiseau doit :

- partir d’un point cohérent ;
- suivre une trajectoire rapide ;
- transporter visuellement la valeur ;
- rejoindre le Wallet ;
- disparaître sans masquer l’écran ;
- être rendu au-dessus de l’interface ;
- ne jamais empêcher le scroll ;
- respecter les performances des appareils modestes.

La durée cible de la nouvelle version est de `900 à 1400 ms`.

L’animation historique plus longue peut être conservée comme référence, mais la version de production du Feed doit être adaptée à un rythme de visionnage fréquent.

## 15.5. Mouvement réduit

Lorsque l’utilisateur active la réduction des animations :

- supprimer le vol complet ;
- conserver un pulse court du Wallet ;
- mettre à jour immédiatement le montant ;
- éviter les particules ;
- conserver le sens fonctionnel.

---

# 16. LANGAGE SONORE ET HAPTIQUE

## 16.1. Principe

Le son n’est pas une musique de fond.

Il confirme une action importante.

## 16.2. Son de crédit

Le son de crédit doit être :

- court ;
- positif ;
- reconnaissable ;
- non agressif ;
- inférieur à `400 ms` ;
- associé uniquement à un crédit confirmé.

Il peut combiner :

- un battement d’aile discret ;
- une note brillante ;
- un tintement très court.

## 16.3. Vibration

Sur appareil compatible :

- vibration légère de `20 à 35 ms` ;
- jamais de vibration répétitive ;
- désactivable ;
- respect des réglages système.

## 16.4. Réglages utilisateur

Prévoir :

- sons Wasplex activés ou désactivés ;
- vibration activée ou désactivée ;
- mouvement réduit ;
- volume dépendant du système.

Aucun son ne doit être déclenché en arrière-plan.

---

# 17. PROGRESSION DU FEED

La progression de visionnage est une signature de Wasplex.

## 17.1. Forme

- ligne fine ;
- hauteur recommandée : `2 px`
- largeur liée à la progression réelle ;
- position constante ;
- aucune grosse jauge qui masque la vidéo ;
- transition fluide ;
- disparition rapide lorsque l’utilisateur passe au contenu suivant.

## 17.2. Couleur

Dégradé officiel :

```css
linear-gradient(90deg, #4FA3FF, #F2C14E)
```

Lecture :

- bleu : attention en cours ;
- or : valeur atteinte.

## 17.3. États

- inactive : invisible ;
- active : visible ;
- interrompue : disparaît sans annoncer de gain ;
- complétée : atteint 100 % ;
- validation : maintien très bref ou pulse ;
- créditée : transfert visuel vers le Wallet ;
- refusée : aucun effet de gain.

---

# 18. IMAGES ET VIDÉOS

## 18.1. Feed

Le média doit occuper la surface utile de l’écran.

Règles :

- vidéo verticale prioritaire ;
- remplissage plein écran ;
- recadrage contrôlé ;
- textes dans les zones sûres ;
- commandes discrètes ;
- contraste des overlays ;
- chargement anticipé du contenu suivant ;
- miniature de repli ;
- aucune page blanche entre deux médias.

## 18.2. Overlays

Les overlays doivent utiliser :

- dégradés sombres ;
- texte blanc ;
- ombre légère ;
- informations limitées ;
- boutons latéraux cohérents ;
- zone inférieure protégée de la navigation.

## 18.3. Portails professionnels

Les visuels de campagne doivent être montrés dans :

- cartes d’aperçu ;
- simulateur mobile ;
- lecteur contrôlé ;
- formats clairement identifiés.

---

# 19. COMPOSANTS FONDATEURS

Le design system initial doit fournir :

```text
BrandLogo
BrandMascot
AppShell
MobileHeader
MobileBottomNavigation
ProfessionalSidebar
ProfessionalTopbar
Card
MetricCard
WalletChip
WalletAmount
RewardBadge
ProgressLine
PrimaryButton
SecondaryButton
DangerButton
IconButton
TextField
SelectField
SearchField
FilterChip
StatusBadge
Avatar
SubscriptionBadge
KycBadge
EmptyState
LoadingSkeleton
OfflineState
ErrorState
Modal
BottomSheet
Drawer
Toast
RewardToast
ConfirmDialog
DataTable
Pagination
Tabs
SegmentedControl
VideoFeedItem
VideoOverlay
FeedActionRail
```

Aucun module ne doit recréer ces composants localement sans nécessité.

---

# 20. HÉRITAGE PAR ESPACE

## 20.1. Application utilisateur

Dominante :

- thème sombre ;
- immersion ;
- cartes profondes ;
- interaction rapide ;
- navigation mobile ;
- valeur visible.

## 20.2. Feed

Dominante :

- média plein écran ;
- overlays ;
- progression fine ;
- Wallet visible ;
- mouvements courts ;
- accent bleu, or et orange.

## 20.3. Wallet

Dominante :

- bleu nuit ;
- orange ;
- or ;
- vert pour confirmation ;
- montants très lisibles ;
- historique rigoureux.

## 20.4. Mon Espace

Dominante :

- carte personnelle riche ;
- badges ;
- progression ;
- actions rapides ;
- sentiment d’intimité ;
- contrôle.

## 20.5. Espace annonceur

Dominante :

- clarté ;
- guidage ;
- aperçu de campagne ;
- données simples ;
- bleu technologique ;
- orange pour budget ;
- or pour performance de valeur.

## 20.6. Administration

Dominante :

- pilotage ;
- files d’action ;
- risques ;
- alertes ;
- configuration ;
- données denses mais hiérarchisées.

## 20.7. Institutions

Dominante :

- sérieux ;
- rapidité ;
- urgence ;
- localisation ;
- dossiers ;
- état d’intervention.

L’espace institutionnel hérite de la marque, mais ne doit pas utiliser les animations de récompense du Feed.

---

# 21. TOKENS À IMPLÉMENTER

```css
:root {
  --font-sans: "Inter", ui-sans-serif, system-ui, -apple-system,
    "Segoe UI", Roboto, Arial, sans-serif;

  --wpx-navy-950: #07182D;
  --wpx-navy-850: #0E2542;
  --wpx-navy-750: #173251;
  --wpx-border-dark: #35506D;

  --wpx-blue: #4FA3FF;
  --wpx-cyan: #2BC4DE;
  --wpx-orange: #FF9A3D;
  --wpx-gold: #F2C14E;

  --wpx-white: #F5F8FC;
  --wpx-muted: #A9B7C8;

  --wpx-success: #42D392;
  --wpx-warning: #F4B942;
  --wpx-danger: #FF6B61;
  --wpx-info: #70B7FF;
  --wpx-pending: #E7CF61;

  --wpx-radius-xs: 8px;
  --wpx-radius-sm: 10px;
  --wpx-radius-md: 14px;
  --wpx-radius-lg: 18px;
  --wpx-radius-xl: 24px;
  --wpx-radius-full: 999px;

  --wpx-space-1: 4px;
  --wpx-space-2: 8px;
  --wpx-space-3: 12px;
  --wpx-space-4: 16px;
  --wpx-space-5: 20px;
  --wpx-space-6: 24px;
  --wpx-space-8: 32px;
  --wpx-space-10: 40px;
  --wpx-space-12: 48px;
  --wpx-space-16: 64px;

  --wpx-ease-standard: cubic-bezier(0.2, 0, 0, 1);
  --wpx-ease-emphasized: cubic-bezier(0.2, 0.8, 0.2, 1);
  --wpx-ease-reward: cubic-bezier(0.16, 1, 0.3, 1);
}
```

Le framework peut générer des alias supplémentaires, mais les valeurs sources doivent rester centralisées.

---

# 22. STRUCTURE DU DESIGN SYSTEM DANS LE NOUVEAU DÉPÔT

```text
docs/
└── 00-fondations/
    ├── 00-identite-visuelle-wasplex.md
    ├── 01-composants-interface.md
    ├── 02-navigation-et-app-shells.md
    ├── 03-mouvement-son-et-retour-utilisateur.md
    └── 04-accessibilite-et-performance.md

apps/
└── platform/
    ├── public/
    │   └── brand/
    ├── resources/
    │   ├── css/
    │   │   ├── tokens.css
    │   │   ├── themes.css
    │   │   └── app.css
    │   └── js/
    │       └── components/
    │           └── design-system/

packages/
└── design-tokens/
    ├── colors.ts
    ├── typography.ts
    ├── spacing.ts
    ├── motion.ts
    └── index.ts
```

Cette structure peut être adaptée au framework retenu, mais les responsabilités doivent rester séparées.

---

# 23. CONTRÔLE VISUEL

Chaque écran doit être vérifié au minimum sur :

```text
320 px
360 px
390 px
480 px
768 px
1024 px
1440 px
```

Thèmes :

- sombre ;
- clair lorsque l’espace le prévoit.

États :

- normal ;
- chargement ;
- vide ;
- erreur ;
- hors connexion ;
- désactivé ;
- succès ;
- en attente.

---

# 24. ACCESSIBILITÉ

Exigences minimales :

- contraste lisible ;
- textes principaux conformes ;
- focus visible ;
- navigation clavier sur desktop ;
- zones tactiles de 44 px minimum ;
- libellés accessibles ;
- pas d’information portée uniquement par la couleur ;
- mouvement réduit ;
- sons désactivables ;
- sous-titres pour les vidéos lorsque disponibles ;
- aucun clignotement agressif.

---

# 25. PERFORMANCE

L’identité ne doit pas dégrader l’application.

Règles :

- SVG optimisés ;
- PNG compressés ;
- images responsives ;
- animations basées sur transform et opacity ;
- particules limitées ;
- pas d’animation lourde permanente ;
- préchargement raisonnable ;
- police optimisée ;
- composants réutilisables ;
- pas de duplication des assets ;
- mode dégradé sur appareils modestes.

---

# 26. INTERDICTIONS GLOBALES

Le nouveau Wasplex ne doit pas contenir :

- couleurs arbitraires dans les composants ;
- hexadécimaux répétés partout ;
- logos différents selon les modules ;
- boutons primaires de couleurs contradictoires ;
- faux soldes ;
- faux crédits ;
- fausses animations de gain ;
- cartes génériques sans identité ;
- textes trop petits ;
- navigation masquant le contenu ;
- effets sonores impossibles à désactiver ;
- animation qui bloque le scroll ;
- interface annonceur conçue uniquement pour un expert ;
- interface administrateur semblable à un panneau technique brut ;
- espace institutionnel ressemblant au Feed publicitaire.

---

# 27. CRITÈRES D’ACCEPTATION

L’identité visuelle fondatrice est prête lorsque :

1. les assets officiels sont présents ;
2. le logo conserve son ratio ;
3. les variantes sombre et claire existent ;
4. les tokens sont centralisés ;
5. aucune couleur majeure n’est codée localement ;
6. Inter est configurée ;
7. les composants fondateurs existent ;
8. les thèmes sont cohérents ;
9. le Feed utilise la progression officielle ;
10. le Wallet utilise orange et or correctement ;
11. l’animation de crédit ne se déclenche qu’après confirmation réelle ;
12. le mouvement réduit fonctionne ;
13. les sons et vibrations sont contrôlables ;
14. les portails professionnels conservent l’identité Wasplex ;
15. les interfaces restent lisibles sur petit Android ;
16. les captures de référence sont produites ;
17. les tests visuels de base passent ;
18. les nouveaux modules peuvent hériter du design sans redéfinir la marque.

---

# 28. LIVRABLES TECHNIQUES ATTENDUS

Lors de l’initialisation du nouveau dépôt, produire :

- dossier `public/brand/` complet ;
- tokens de couleurs ;
- thèmes ;
- tokens typographiques ;
- tokens d’espacement ;
- tokens de mouvement ;
- composants Logo et Mascotte ;
- App Shell mobile ;
- App Shell professionnel ;
- bouton principal ;
- carte ;
- badge ;
- Wallet Chip ;
- Progress Line ;
- état de chargement ;
- état vide ;
- état hors connexion ;
- page de démonstration du design system ;
- captures aux largeurs de référence ;
- tests visuels essentiels.

---

# 29. RELATION AVEC LE FUTUR NOYAU

Le futur noyau Wasplex ne doit pas seulement hériter des couleurs.

Il doit hériter du langage d’expérience défini ici :

- une action réelle produit un retour réel ;
- une valeur confirmée devient visible immédiatement ;
- le Wallet est une destination permanente de la valeur ;
- la mascotte transporte la valeur ;
- la progression est discrète ;
- l’utilisateur peut continuer son mouvement ;
- le système paraît vivant sans mentir ;
- tous les espaces appartiennent au même écosystème.

Le prochain document doit définir le **moteur unifié de valeur temps réel**, qui réunira :

- session de visionnage ;
- progression ;
- preuve ;
- événement ;
- validation ;
- tarification ;
- répartition ;
- écriture comptable ;
- crédit Wallet ;
- confirmation au Feed ;
- animation ;
- son ;
- mise à jour annonceur ;
- supervision administrative.

---

# 30. DÉCISION FONDATRICE

Wasplex doit être reconnaissable avant même que l’utilisateur lise son nom.

Cette reconnaissance repose sur :

- le bleu nuit ;
- le martin-pêcheur ;
- le bleu de progression ;
- l’or de la valeur ;
- l’orange du Wallet ;
- la circulation visuelle du gain ;
- la profondeur des cartes ;
- la fluidité du Feed ;
- la cohérence de tous les espaces.

Aucun futur module ne doit rompre cette identité.
