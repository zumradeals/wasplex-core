<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Contracts;

use App\Modules\Matching\Application\ValueObjects\MatchingDecisionResult;

/**
 * Contrat de sortie minimal exposé à P009 (Feed, non construit) —
 * docs/CLAUDE.md §6, exemple canonique « Feed → MatchingContract →
 * résultat d'éligibilité ». Ne transmet jamais le SmartProfile complet ni
 * l'identité du compte à l'appelant externe (docs/chantiers/
 * P008-CHANTIER.md §4).
 */
interface MatchingContract
{
    public function checkEligibility(string $campaignId, string $accountId): MatchingDecisionResult;

    /**
     * Jetons d'explication en langage clair — vides si la décision n'est
     * pas `eligible` (aucune règle sensible n'est révélée à un compte
     * refusé ou en attente, docs/04 §26).
     *
     * @return string[]
     */
    public function explain(string $campaignId, string $accountId): array;
}
