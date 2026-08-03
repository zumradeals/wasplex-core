<?php

namespace App\Modules\Wallet\Domain\Enums;

enum DepositStatus: string
{
    case Created = 'created';
    case CreatingPayment = 'creating_payment';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentDetected = 'payment_detected';
    case Confirmed = 'confirmed';
    case Credited = 'credited';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Unknown = 'unknown';
    case Reversed = 'reversed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Credited, self::Failed, self::Cancelled, self::Expired, self::Reversed], true);
    }
}
