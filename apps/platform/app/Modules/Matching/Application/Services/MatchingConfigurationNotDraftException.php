<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Services;

use RuntimeException;

final class MatchingConfigurationNotDraftException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Seule une configuration en brouillon peut être modifiée.');
    }
}
