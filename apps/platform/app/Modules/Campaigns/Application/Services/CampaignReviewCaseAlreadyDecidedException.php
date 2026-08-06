<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignReviewCaseAlreadyDecidedException extends RuntimeException
{
    public function __construct(string $caseId)
    {
        parent::__construct("Ce dossier de revue a déjà été décidé : {$caseId}.");
    }
}
