<?php

namespace App\Modules\Ledger\Domain\Enums;

enum LedgerAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
