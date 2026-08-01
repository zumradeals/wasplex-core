<?php

namespace App\Modules\Identity\Domain\Events;

final readonly class OrganizationInvitationAccepted
{
    public function __construct(
        public string $organizationId,
        public string $invitationId,
        public string $accountId,
    ) {}
}
