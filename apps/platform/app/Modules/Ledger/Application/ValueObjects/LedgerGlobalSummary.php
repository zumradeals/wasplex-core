<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\ValueObjects;

/**
 * docs/18-reporting-statistiques-audit-observabilite-wasplex.md §36 :
 * contrôle d'équilibre du Grand Livre (debits = credits) — la source de
 * vérité pour toute donnée financière (docs/CLAUDE.md §7).
 */
final class LedgerGlobalSummary
{
    /**
     * @param  array<string, int>  $netByAccountType  code de type de compte
     *                                                (ASSET, LIABILITY_USER, LIABILITY_ADVERTISER, ...) => solde net
     *                                                (crédits - débits) en unité mineure.
     */
    public function __construct(
        public readonly int $totalDebitMinor,
        public readonly int $totalCreditMinor,
        public readonly int $transactionCount,
        public readonly array $netByAccountType,
    ) {}

    public function isBalanced(): bool
    {
        return $this->totalDebitMinor === $this->totalCreditMinor;
    }
}
