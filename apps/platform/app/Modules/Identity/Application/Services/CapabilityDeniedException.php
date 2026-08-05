<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Shared\Http\AppException;

final class CapabilityDeniedException extends AppException
{
    public static function forCapability(string $capabilityCode): self
    {
        return new self(
            errorCode: 'CAPABILITY_DENIED',
            message: "La capacité « {$capabilityCode} » est requise pour cette action.",
            details: ['capability' => $capabilityCode],
            status: 403,
        );
    }
}
