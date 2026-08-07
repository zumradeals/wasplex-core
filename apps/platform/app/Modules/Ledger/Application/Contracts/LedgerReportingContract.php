<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Contracts;

use App\Modules\Ledger\Application\ValueObjects\LedgerGlobalSummary;

interface LedgerReportingContract
{
    public function globalSummary(): LedgerGlobalSummary;
}
