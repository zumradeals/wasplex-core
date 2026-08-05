<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Shared\Http\AppException;

final class InvitationInvalidException extends AppException
{
    public static function forInvitation(string $invitationId, string $reason): self
    {
        return new self(
            errorCode: 'INVITATION_INVALID',
            message: "Cette invitation n'est plus valide.",
            details: ['invitation_id' => $invitationId, 'reason' => $reason],
            status: 422,
        );
    }
}
