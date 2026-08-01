<?php

namespace App\Modules\Identity\Domain\Events;

final readonly class OrganizationMemberInvited
{
    public function __construct(public string $organizationId, public string $invitationId) {}
}
