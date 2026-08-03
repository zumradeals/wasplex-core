<?php

namespace App\Modules\Ledger\Domain\Exceptions;

final class UnbalancedTransaction extends LedgerException
{
    public function __construct(
        public readonly int $debitTotal,
        public readonly int $creditTotal,
    ) {
        parent::__construct("Transaction déséquilibrée : débits {$debitTotal}, crédits {$creditTotal}.");
    }
}
