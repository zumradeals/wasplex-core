<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Contracts;

interface ProfileTargetingContract
{
    /** @return array<string, array<int, array{code: string, label: string}>> */
    public function targetableCatalog(): array;

    /** @param string[] $taxonomyCodes */
    public function validateTargetableCodes(array $taxonomyCodes): array;

    /** @param string[] $taxonomyCodes */
    public function accountMatchesAll(string $accountId, array $taxonomyCodes): bool;
}
