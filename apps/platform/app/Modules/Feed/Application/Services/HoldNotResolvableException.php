<?php

declare(strict_types=1);

namespace App\Modules\Feed\Application\Services;

use RuntimeException;

final class HoldNotResolvableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cette retenue a déjà été résolue.');
    }
}
