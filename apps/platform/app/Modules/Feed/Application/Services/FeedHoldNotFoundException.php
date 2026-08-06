<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use RuntimeException;

final class FeedHoldNotFoundException extends RuntimeException
{
    public function __construct(string $holdId)
    {
        parent::__construct("Retenue introuvable : {$holdId}.");
    }
}
