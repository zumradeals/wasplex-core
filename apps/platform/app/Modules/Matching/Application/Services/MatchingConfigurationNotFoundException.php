<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Services;

use RuntimeException;

final class MatchingConfigurationNotFoundException extends RuntimeException
{
    public function __construct(string $configurationId)
    {
        parent::__construct("Configuration de Matching introuvable : {$configurationId}.");
    }
}
