<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use RuntimeException;

final class DeliveryNotStartableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cette livraison ne peut plus être démarrée (déjà démarrée, expirée ou terminée).');
    }
}
