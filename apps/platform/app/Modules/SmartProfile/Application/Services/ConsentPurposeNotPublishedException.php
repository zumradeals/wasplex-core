<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use RuntimeException;

final class ConsentPurposeNotPublishedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Cette finalité n'a pas encore de texte publié — aucune décision ne peut être enregistrée.");
    }
}
