# Assets de marque Wasplex

Référence normative : `docs/00-identite-visuelle-wasplex.md` §4.3.

## État réel (2026-08-05)

Le fondateur a fourni le premier asset officiel réel : `source/wasplex-logo-official-source.jpg`
(martin-pêcheur avec poisson, logotype "Wasplex" intégré, fond blanc, 1024×1024, raster).//
C'est la seule source de vérité visuelle disponible à ce jour — aucune autre variante
(fichier vectoriel, déclinaison isolée, monochrome) n'a été transmise.

| Fichier attendu (§4.3) | Statut | Origine |
|---|---|---|
| `wasplex-logo-full.png` | ✅ présent | conversion sans perte du JPG officiel fourni |
| `wasplex-app-icon-512.png` | ✅ présent | redimensionnement du même asset officiel |
| `wasplex-app-icon-192.png` | ✅ présent | redimensionnement du même asset officiel |
| `wasplex-logo-full.svg` | ❌ absent | nécessite une vectorisation professionnelle du dessin |
| `wasplex-logo-horizontal.svg` / `.png` | ❌ absent | nécessite une déclinaison graphique dédiée (recomposition du lockup) |
| `wasplex-mascot.svg` / `.png` | ❌ absent | nécessite l'isolement de l'oiseau (détourage professionnel, fond transparent) |
| `wasplex-mascot-monochrome-light.svg` | ❌ absent | nécessite un travail de déclinaison graphique |
| `wasplex-mascot-monochrome-dark.svg` | ❌ absent | nécessite un travail de déclinaison graphique |
| `wasplex-wordmark-light.svg` | ❌ absent | nécessite l'extraction isolée du lettrage |
| `wasplex-wordmark-dark.svg` | ❌ absent | nécessite l'extraction isolée du lettrage |
| `wasplex-favicon.svg` | ❌ absent | nécessite une version simplifiée lisible en très petite taille |

## Pourquoi ces variantes ne sont pas générées automatiquement

Le §4.7 interdit explicitement de reproduire le logo à partir d'une capture d'écran,
d'appliquer une couleur arbitraire ou de découper l'oiseau de façon approximative.
Isoler la mascotte sur fond transparent, vectoriser le dessin ou en dériver une
version monochrome fidèle est un travail de design graphique réel (détourage propre,
tracé vectoriel) — pas une opération de conversion de fichier. Le produire ici avec
des outils de traitement d'image génériques donnerait un résultat dégradé qui violerait
la charte plutôt que de la respecter.

**Décision en attente du fondateur** : fournir les déclinaisons manquantes (idéalement
un fichier vectoriel source, ex. AI/EPS/SVG), ou valider qu'un prestataire graphique
externe les prépare à partir de `source/wasplex-logo-official-source.jpg`.

## Utilisation actuelle dans l'application

En l'absence des déclinaisons horizontale et mascotte-seule, les écrans qui ont besoin
d'un repère de marque compact (barre latérale annonceur/admin, navigation mobile)
utilisent temporairement le logo complet (`wasplex-logo-full.png`) redimensionné dans
son ratio d'origine — jamais recadré ni recoloré à la main. Cette limite est documentée
dans le rapport du chantier design correspondant.
