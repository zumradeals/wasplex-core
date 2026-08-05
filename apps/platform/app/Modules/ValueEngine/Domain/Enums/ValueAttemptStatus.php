<?php

namespace App\Modules\ValueEngine\Domain\Enums;

enum ValueAttemptStatus: string
{
    case Reserved = 'reserved';
    case AttentionActive = 'attention_active';
    case ProofPending = 'proof_pending';
    case Completed = 'completed';
    case Released = 'released';
    case Expired = 'expired';
    case Rejected = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Released, self::Expired, self::Rejected], true);
    }
}
