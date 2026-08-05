<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Shared\Http\AppException;

final class LedgerIdempotencyConflictException extends AppException
{
    public function __construct(string $idempotencyKey)
    {
        parent::__construct(
            'LEDGER_IDEMPOTENCY_CONFLICT',
            "La clé d'idempotence « {$idempotencyKey} » a déjà été utilisée avec un contenu économique différent.",
            ['idempotency_key' => $idempotencyKey],
            409,
        );
    }
}
