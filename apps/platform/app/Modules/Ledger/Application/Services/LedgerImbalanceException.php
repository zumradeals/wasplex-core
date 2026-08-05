<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Shared\Http\AppException;

final class LedgerImbalanceException extends AppException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(array $details = [])
    {
        parent::__construct('LEDGER_IMBALANCE', "La transaction n'est pas équilibrée (débits ≠ crédits).", $details, 422);
    }
}
