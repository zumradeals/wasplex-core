<?php

namespace App\Modules\Ledger\Domain\Exceptions;

final class IdempotencyConflict extends LedgerException
{
    public static function forKey(string $sourceModule, string $key): self
    {
        return new self("La clé d’idempotence {$sourceModule}:{$key} désigne déjà une autre opération.");
    }
}
