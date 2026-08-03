<?php

namespace App\Modules\Wallet\Domain\Enums;

enum ProviderEventStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
}
