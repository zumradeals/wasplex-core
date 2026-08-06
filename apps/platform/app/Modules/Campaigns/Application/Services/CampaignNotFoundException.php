<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignNotFoundException extends RuntimeException
{
    public function __construct(string $campaignId)
    {
        parent::__construct("Campagne introuvable : {$campaignId}.");
    }
}
