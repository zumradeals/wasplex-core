# WASPLEX — DÉCISIONS OUVERTES APRÈS AUDIT

**Principe :** seules les décisions ayant un impact réel sur l'initialisation sont listées.

## OD-001 — Direction frontend

**Recommandation :** Inertia + Vue 3 + TypeScript + Tailwind + Vite.

**Option A — recommandée :** adaptée au Feed interactif et aux dashboards utilisateur, annonceur et administration ; complexité frontend assumée mais cohérente.

**Option B :** Blade + Livewire + Alpine ; démarrage plus simple, mais plus de compromis pour le Feed vidéo et les interactions riches.

**Impact :** choix bloquant avant le scaffold P000.

## OD-002 — Structure du monorepo

**Recommandation :** application Laravel dans `apps/platform`, documentation dans `docs`, infrastructure dans `infra`, packages partagés uniquement lorsqu'un besoin réel apparaît.

**Alternative :** Laravel à la racine. Plus simple, mais moins cohérent avec la volonté explicite de monorepo et les futures surfaces spécialisées.

**Impact :** choix bloquant avant P000.

## OD-003 — Document maître canonique

**Recommandation :** générer `docs/MASTER-WASPLEX.md` à partir de la note 22 et de l'audit validé, avec liens réels ; conserver la note 22 comme source historique jusqu'à une décision de rangement.

**Impact :** doit être résolu avant que les agents appliquent durablement `CLAUDE.md`.

## OD-004 — Organisation documentaire

**Recommandation :** ne pas déplacer toutes les notes pendant P000. Créer d'abord le master et l'index avec les chemins actuels ; réaliser ensuite un déplacement atomique séparé si le fondateur confirme l'arborescence cible.

**Impact :** non bloquant pour le scaffold, mais important pour éviter des liens cassés.

## OD-005 — Standard de tests

**Recommandation :** Pest au-dessus de PHPUnit pour les tests Laravel, complété par un outil navigateur cohérent avec Vue.

**Impact :** à verrouiller dans P000.

## Validation demandée au fondateur

Une réponse suffit :

```text
Audit validé.
Frontend : option A ou B.
Monorepo : apps/platform ou Laravel à la racine.
Document maître : recommandation validée ou correction.
```

Après cette validation, la mission suivante est la génération de `IMPLEMENTATION-ROADMAP-WASPLEX.md`, pas encore le codage de P000.
