<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Shared\Http\AppException;

final class LedgerTransactionNotFoundException extends AppException
{
    public function __construct(string $transactionId)
    {
        parent::__construct(
            'LEDGER_TRANSACTION_NOT_FOUND',
            "Transaction « {$transactionId} » introuvable.",
            ['transaction_id' => $transactionId],
            404,
        );
    }
}
