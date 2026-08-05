<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Shared\Http\AppException;

final class SpaceAccessDeniedException extends AppException
{
    public static function forSpace(string $userSpaceId): self
    {
        return new self(
            errorCode: 'SPACE_ACCESS_DENIED',
            message: "Aucun accès actif à l'espace demandé.",
            details: ['user_space_id' => $userSpaceId],
            status: 403,
        );
    }
}
