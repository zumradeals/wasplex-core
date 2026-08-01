<?php

namespace App\Modules\Identity\Domain\Events;

final readonly class UserSpaceCreated
{
    public function __construct(public string $accountId, public string $spaceId, public string $kind) {}
}
