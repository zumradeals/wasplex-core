<?php

namespace App\Modules\Identity\Domain\Events;

final readonly class AccountCreated
{
    public function __construct(public string $accountId) {}
}
