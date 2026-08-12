<?php

declare(strict_types=1);

namespace App\Modules\Health\Application\Contracts;

interface EmergencyCapsuleProvider
{
    /**
     * @return array<string, mixed>|null
     */
    public function forAccount(string $accountId): ?array;
}
