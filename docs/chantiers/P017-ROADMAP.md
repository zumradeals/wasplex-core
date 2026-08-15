# P017 — Carte Wasplex : rôle produit et roadmap non prioritaire

**Décision produit :** 2026-08-15  
**Statut :** P017-A et P017-B livrés ; P017-B.1 clôture ; suite reportée en roadmap  
**Principe :** la Carte Wasplex n'est pas le cœur immédiat de Wasplex.

## 1. Rôle principal retenu

La Carte Wasplex doit d'abord permettre à un membre Wasplex :

- de prouver une identité membre minimale et vérifiable ;
- de présenter une Carte virtuelle Wasplex ;
- d'accéder à des réductions, privilèges, offres et avantages négociés avec des partenaires Wasplex ;
- de faire vérifier son éligibilité chez le partenaire sans exposer inutilement ses données personnelles.

Le paiement QR Wallet livré en P017-B est une **capacité secondaire utile**, pas l'objectif produit central de la Carte.

## 2. Ce qui est déjà livré

### P017-A — Fondation

- Carte virtuelle ;
- identifiant public ;
- QR d'identité temporaire ;
- révocation/suspension ;
- exposition minimale des données ;
- aucune valeur stockée dans la Carte elle-même.

### P017-B — Paiement QR Wallet

- QR de réception temporaire ;
- vérification du bénéficiaire ;
- confirmation explicite ;
- débit/crédit dans le Grand Livre ;
- reçu ;
- Historique Carte Wasplex ;
- idempotence et anti-rejeu.

### P017-B.1 — Clôture

- fallback de nom `display_name → prénom + nom → Membre Wasplex` ;
- scanner mobile avec fallback manuel `WPLX:RECEIVE:…` ;
- documentation des preuves production ;
- aucun nouveau sous-système financier Carte.

## 3. Roadmap fonctionnelle Carte — non prioritaire

### Avantages et partenaires — axe produit principal futur

- catalogue d'offres partenaires ;
- pourcentage ou montant de réduction ;
- conditions d'éligibilité ;
- période de validité ;
- usages limités ou illimités ;
- QR/token d'avantage ;
- validation côté partenaire ;
- preuve d'utilisation ;
- historique des avantages consommés ;
- reporting agrégé partenaire ;
- géolocalisation volontaire des offres à proximité ;
- cashback éventuel uniquement si son modèle économique est validé séparément.

### Capacités secondaires possibles

- Carte Pro/Marchand ;
- QR permanent marchand ;
- remboursements et annulations contrôlés ;
- limites et step-up MFA avancés ;
- compatibilité scanner renforcée avec librairie dédiée ;
- carte physique ;
- NFC ;
- intégration à des réseaux externes, uniquement après étude juridique, financière et technique.

## 4. Non-priorités explicites

À ce stade, Wasplex ne poursuit pas :

- la transformation de la Carte en portefeuille autonome ;
- la duplication du Wallet dans la Carte ;
- la construction d'un réseau de paiement externe ;
- un chantier marchand lourd avant les modules cœur ;
- le NFC ou la carte physique comme prérequis produit.

## 5. Invariant économique

La Carte ne possède jamais de solde financier propre. Toute valeur WP reste portée par le Wallet et toute écriture financière par le Grand Livre.

## 6. Décision de passage

P017-B.1 clôt le travail immédiat Carte. La roadmap ci-dessus reste disponible pour approfondissement futur, mais **P018 Live devient le chantier actif suivant**.
