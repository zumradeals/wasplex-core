<?php

namespace App\Modules\Ledger\Domain\Enums;

enum LedgerTransactionStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
