<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Shared\Http\AppException;

final class LedgerCorrectionStateException extends AppException
{
    public function __construct(string $transactionId, string $actualStatus)
    {
        parent::__construct(
            'LEDGER_CORRECTION_INVALID_STATE',
            "La correction « {$transactionId} » n'est pas en attente d'approbation (statut actuel : {$actualStatus}).",
            ['transaction_id' => $transactionId, 'status' => $actualStatus],
            409,
        );
    }
}
