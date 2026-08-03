<?php

namespace App\Modules\Wallet\Domain\Enums;

enum WalletKind: string
{
    case User = 'user';
    case Advertiser = 'advertiser';
}
