<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignEnvelopeExhaustedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("L'enveloppe de cette campagne pour cette classe économique est épuisée.");
    }
}
