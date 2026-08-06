<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use RuntimeException;

final class InsufficientAdvertiserBalanceException extends RuntimeException
{
    public function __construct(public readonly int $availableMinor, public readonly int $requestedMinor)
    {
        parent::__construct("Solde annonceur insuffisant : {$availableMinor} disponible, {$requestedMinor} requis.");
    }
}
