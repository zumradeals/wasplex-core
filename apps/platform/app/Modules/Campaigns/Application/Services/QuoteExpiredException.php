<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class QuoteExpiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Le devis a expiré — générez un nouveau devis avant de financer la campagne.');
    }
}
