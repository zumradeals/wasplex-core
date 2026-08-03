<?php

namespace App\Modules\Ledger\Domain\Enums;

enum LedgerJournalStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
