<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Contracts;

/**
 * The only way Matching (P008) may read a user's consent state — never a
 * direct query against `user_consents` from outside this module
 * (docs/CLAUDE.md §6).
 */
interface AdvertisingConsentContract
{
    public const STATE_ACTIVE = 'active';

    public const STATE_REFUSED = 'refused';

    public const STATE_NOT_DECIDED = 'not_decided';

    /**
     * One of ConsentState::ACTIVE, ::REFUSED or ::NOT_DECIDED. A refusal
     * (denied/withdrawn/expired) is a hard exclusion; the absence of any
     * decision is a privacy doubt, not a refusal (docs/chantiers/
     * P008-CHANTIER.md §8).
     */
    public function stateFor(string $accountId, string $purposeCode): string;
}
