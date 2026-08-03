<?php

namespace App\Modules\Ledger\Domain\Enums;

enum LedgerLinkType: string
{
    case Reversal = 'reversal';
    case Related = 'related';
}
