<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use RuntimeException;

final class AttentionNotQualifiedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("La durée d'attention requise n'est pas encore atteinte.");
    }
}
