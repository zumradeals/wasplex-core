<?php

namespace App\Modules\Identity\Domain\Events;

final readonly class OrganizationCreated
{
    public function __construct(public string $organizationId, public string $creatorAccountId) {}
}
