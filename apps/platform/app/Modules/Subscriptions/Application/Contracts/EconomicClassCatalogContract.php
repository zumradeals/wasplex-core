<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Contracts;

use App\Modules\Subscriptions\Application\ValueObjects\AudienceEstimate;
use App\Modules\Subscriptions\Application\ValueObjects\EconomicClassSummary;

/**
 * The only way another module reads economic classes (docs/CLAUDE.md §6:
 * contrats internes plutôt qu'une lecture directe des tables). Used by
 * Campaigns (P006) to list targetable classes and to turn a targeting
 * selection into an aggregate audience estimate — never a nominative list
 * (docs/04 §10: "Aucun identifiant individuel n'est transmis.").
 */
interface EconomicClassCatalogContract
{
    /**
     * @return EconomicClassSummary[]
     */
    public function listActive(): array;

    /**
     * @param  string[]  $classCodes
     */
    public function estimateAudience(array $classCodes, ?string $countryCode, int $minimumSegmentSize): AudienceEstimate;
}
