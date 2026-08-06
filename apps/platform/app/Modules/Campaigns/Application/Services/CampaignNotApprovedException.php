<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignNotApprovedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Seule une campagne approuvée peut être suspendue.');
    }
}
