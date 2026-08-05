<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use Illuminate\Support\Carbon;

/**
 * Verifies explicit, contextualised capability grants.
 * Docs/17 #21-#25: no capability is ever implicit from a role name alone.
 */
final class CapabilityChecker
{
    public function accountHas(
        Account $account,
        string $capabilityCode,
        ?string $scopeType = null,
        ?string $scopeId = null,
    ): bool {
        $now = Carbon::now();

        return $account->capabilityGrants()
            ->where('capability_code', $capabilityCode)
            ->get()
            ->contains(fn (CapabilityGrant $grant): bool => $grant->isActiveAt($now) && $grant->matchesScope($scopeType, $scopeId));
    }

    public function requireAccountHas(
        Account $account,
        string $capabilityCode,
        ?string $scopeType = null,
        ?string $scopeId = null,
    ): void {
        if (! $this->accountHas($account, $capabilityCode, $scopeType, $scopeId)) {
            throw CapabilityDeniedException::forCapability($capabilityCode);
        }
    }
}
