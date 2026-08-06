<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignBrandNotFoundException extends RuntimeException
{
    public function __construct(string $brandId)
    {
        parent::__construct("Marque introuvable pour cette organisation : {$brandId}.");
    }
}
