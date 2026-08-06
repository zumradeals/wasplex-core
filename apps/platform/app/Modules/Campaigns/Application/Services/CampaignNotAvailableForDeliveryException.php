<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class CampaignNotAvailableForDeliveryException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Cette campagne n'est pas disponible pour la livraison (non approuvée, suspendue ou sans devis actif).");
    }
}
