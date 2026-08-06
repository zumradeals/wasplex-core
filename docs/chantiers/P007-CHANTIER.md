# P007 — Revue administrative et activation de campagne

**Branche :** `claude/wasplex-reconstruction-7ujym7`
**Commit de base :** `d92d1ae` (P006 fusionné)
**Dépendances déclarées (roadmap) :** P006 (campagne versionnée, devis, financement, soumission)
**Spécifications :** `docs/13-studio-annonceur-wasplex.md` §53-60, §93-100 ; `docs/12-administration-centrale-supervision-fondateur-wasplex.md` (dashboard, généralités) ; `docs/16-moderation-securite-antifraude-globale-wasplex.md` (revue humaine, généralités) ; `docs/18-reporting-statistiques-audit-observabilite-wasplex.md` (audit append-only)
**Statut :** proposed

Ce chantier remplace l'ancienne version pré-réinitialisation (branche `codex/p007-campaign-admin-review`,
conservée dans l'historique Git à titre d'audit mais ne décrivant plus l'état réel du dépôt
reconstruit). Sa politique financière par décision (réservation conservée sauf en cas de rejet) et
son option `CAMPAIGN_REVIEW_REQUIRE_DISTINCT_DECIDER` sont reprises car cohérentes et déjà
correctement raisonnées, mais adaptées aux conventions actuelles du dépôt (noms de capacités,
routes `docs/13` §93, pas de table `campaign_review_tasks`/`campaign_status_events` redondante,
pas de commande de bootstrap puisqu'aucune campagne héritée n'existe dans le dépôt reconstruit).

---

## 1. Objectif

Donner à l'administration une file de revue pour les campagnes soumises (P006) : approuver,
demander une correction, rejeter, ou suspendre une campagne déjà approuvée — sans jamais
capturer le budget ni activer le Feed (P008/P009, non construits).

## 2. Périmètre inclus

- File de revue (`docs/13` §93) : campagnes en attente d'une décision.
- Fiche de revue complète : annonceur/marque (via `BrandDirectoryContract`, P006), créatif
  (via `CreativeAssetDirectoryContract`, P006), audience, devis figé, statut de la réservation
  Wallet.
- Décisions administratives (§55) : `approve`, `request_changes`, `reject`. `suspend` s'applique
  séparément à une campagne déjà `approved` (§93 le liste comme une route distincte).
- Motif obligatoire pour `request_changes`, `reject` et `suspend`.
- Correction et resoumission côté annonceur (§56) : `PATCH /campaigns/{id}` reste utilisable
  quand le statut est `changes_requested` (sans réinitialiser le statut, contrairement à une
  édition en `quoted`/`funded`), puis `POST /campaigns/{id}/resubmit` (§90) rouvre un nouveau
  dossier de revue **sans nouveau financement** — le budget déjà réservé reste verrouillé.
- Historique append-only (`campaign_review_events`) de chaque transition, y compris la
  soumission initiale et les resoumissions.
- Politique financière par décision (§6 ci-dessous).
- Séparation optionnelle demandeur/décideur (`config('campaigns.review_require_distinct_decider')`,
  défaut `false`) : si activée, l'administrateur qui a demandé une correction ne peut pas être
  celui qui approuve ou rejette la resoumission correspondante.

## 3. Décisions explicites de réduction de périmètre et d'interprétation

1. **Pas de table `campaign_review_tasks` séparée.** Le dossier de revue
   (`campaign_review_cases`, statut `open`/`decided`) sert déjà de file d'attente — une table de
   tâches parallèle dupliquerait la même notion de file.
2. **Pas de table `campaign_status_events` séparée.** `campaign_review_events` couvre déjà toutes
   les transitions pertinentes à ce chantier (soumission, correction, resoumission, décision,
   suspension) — un second journal des mêmes transitions serait redondant.
3. **Pas de commande de bootstrap.** Le dépôt reconstruit ne contient aucune campagne soumise
   avant ce chantier ; aucune reprise n'est nécessaire.
4. **Politique financière par décision** (§6) : seul le rejet libère la réservation. La suspension
   la conserve — une campagne suspendue reste réactivable sans nouveau financement, contrairement
   à un rejet qui est terminal. Ce choix, absent du texte exact de `docs/13`, est repris de
   l'analyse déjà faite dans la version pré-réinitialisation du chantier (cohérente et
   documentée) plutôt que réinventé.
5. **`campaign_versions.status` n'est pas étendu.** Seul `campaigns.status` porte les nouveaux
   états de revue (`changes_requested`, `approved`, `rejected`, `suspended`) — la version reste
   conceptuellement "soumise" tout au long de la revue ; rien ne lit `campaign_versions.status`
   au-delà de ce que P006 vérifiait déjà.
6. **Widget "dashboard fondateur" simplifié.** Plutôt que le cadre générique
   `admin_dashboards`/`admin_dashboard_widgets` de `docs/12` §69 (infrastructure d'administration
   globale hors périmètre), un simple compteur de dossiers en attente est affiché en tête du
   panneau de revue lui-même.
7. **Aucune automatisation antifraude.** `docs/16` décrit un pipeline de scoring/détection
   automatique — hors périmètre ; les décisions ici sont exclusivement humaines.

## 4. Périmètre exclu

- SmartProfile, consentements, Matching, Feed, diffusion, preuve d'attention (P008/P009).
- Capture du budget, crédit Wallet utilisateur (P009+).
- Reporting de performance de campagne (P012).
- Modification après approbation créant une nouvelle version (§58) — non spécifié avec assez de
  détail pour être codé sans inventer de règle ; une campagne approuvée n'est plus éditable dans
  ce chantier.

## 5. États de campagne (`campaigns.status`, contrainte étendue)

```text
draft → quoted → funded → submitted
                              ├── changes_requested → submitted (resoumission)
                              ├── approved → suspended
                              └── rejected
```

## 6. Politique financière par décision

| Décision            | Réservation Wallet | Statut campagne     |
|---------------------|---------------------|----------------------|
| Soumission           | conservée            | `submitted`          |
| Demande de correction | conservée           | `changes_requested`  |
| Resoumission          | conservée            | `submitted`           |
| Approbation           | conservée            | `approved`            |
| Rejet                 | **libérée**          | `rejected`            |
| Suspension            | conservée            | `suspended`           |

Le rejet libère via `AdvertiserWalletReservationContract::release()` (nouvelle méthode, P007),
sous une clé d'idempotence dérivée du dossier de revue — une répétition de la décision ne libère
jamais deux fois.

## 7. Modèle de données

```text
campaign_review_cases    campaign_id, campaign_version_id, status (open/decided),
                          decision (approved/changes_requested/rejected), reason,
                          requested_changes_by, decided_by, opened_at, decided_at
campaign_review_events   campaign_review_case_id, event_type, actor_account_id, reason,
                          created_at (append-only)
```

## 8. Contrat étendu

`AdvertiserWallet\AdvertiserWalletReservationContract::release(string $organizationId, string $campaignId, int $amountMinor, string $idempotencyKey): void`

## 9. Capacités

`admin.campaign-reviews.view`, `admin.campaign-reviews.decide` (approve/request-changes/reject),
`admin.campaigns.suspend` — aucune autorité dérivée du seul rôle (chaque capacité est accordée
explicitement, comme pour tous les autres domaines admin de ce dépôt).

## 10. Tests obligatoires

Campagne soumise visible dans la file ; refus sans capacité (403) ; demande de correction exige
un motif et conserve la réservation ; l'annonceur peut corriger et resoumettre sans second
financement ; approbation conserve la réservation ; rejet libère la réservation une seule fois
(idempotence) et devient terminal (aucune resoumission possible) ; suspension d'une campagne
approuvée conserve la réservation ; séparation demandeur/décideur appliquée seulement si
configurée ; aucune fuite entre organisations.

## 11. Chantier suivant recommandé

P008 — SmartProfile, consentements et Matching minimal.
