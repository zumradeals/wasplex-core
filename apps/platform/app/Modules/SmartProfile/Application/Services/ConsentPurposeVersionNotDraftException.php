<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use RuntimeException;

final class ConsentPurposeVersionNotDraftException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Seule une version en brouillon peut être modifiée.');
    }
}
