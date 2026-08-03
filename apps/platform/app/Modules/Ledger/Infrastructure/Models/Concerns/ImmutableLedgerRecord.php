<?php

namespace App\Modules\Ledger\Infrastructure\Models\Concerns;

use LogicException;

trait ImmutableLedgerRecord
{
    public static function bootImmutableLedgerRecord(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Un enregistrement publié du Grand Livre ne peut pas être modifié.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Un enregistrement publié du Grand Livre ne peut pas être supprimé.');
        });
    }
}
