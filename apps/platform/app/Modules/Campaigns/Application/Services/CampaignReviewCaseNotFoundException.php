<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignReviewCaseNotFoundException extends RuntimeException
{
    public function __construct(string $caseId)
    {
        parent::__construct("Dossier de revue introuvable : {$caseId}.");
    }
}
