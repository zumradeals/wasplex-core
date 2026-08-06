<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Services;

use App\Modules\Matching\Infrastructure\Models\MatchingDecision;

/**
 * docs/chantiers/P008-CHANTIER.md §7 : agrégats uniquement, aucune
 * identité de compte ni profil publicitaire (docs/04 §4.3 dans le plan
 * archivé, cohérent avec docs/CLAUDE.md §16).
 */
final class MatchingAuditService
{
    /**
     * @return array<string, int>
     */
    public function decisionCounts(): array
    {
        return MatchingDecision::query()
            ->selectRaw('decision, count(*) as total')
            ->groupBy('decision')
            ->pluck('total', 'decision')
            ->all();
    }
}
