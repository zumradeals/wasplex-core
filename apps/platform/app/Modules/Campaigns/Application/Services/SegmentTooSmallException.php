<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use RuntimeException;

final class SegmentTooSmallException extends RuntimeException
{
    public function __construct(public readonly int $estimatedTotal, public readonly int $minimumSegmentSize)
    {
        parent::__construct(
            "Audience estimée trop petite ({$estimatedTotal}, minimum {$minimumSegmentSize}) — élargissez le territoire ou les classes ciblées."
        );
    }
}
