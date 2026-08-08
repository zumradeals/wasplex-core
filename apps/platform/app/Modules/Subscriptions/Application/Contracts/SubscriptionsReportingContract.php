<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Contracts;

use App\Modules\Subscriptions\Application\ValueObjects\SubscriptionsSummary;

interface SubscriptionsReportingContract
{
    public function summary(): SubscriptionsSummary;

    /**
     * Docs/chantiers/P017-CHANTIER.md (écran Utilisateurs) : lecture seule,
     * réutilisée par Identity via ce contrat plutôt qu'un accès direct aux
     * tables Subscriptions (CLAUDE.md #6).
     */
    public function activeEconomicClassCodeForAccount(string $accountId): ?string;
}
