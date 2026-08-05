<?php

namespace App\Modules\ValueEngine\Domain\Enums;

enum AttentionSessionStatus: string
{
    case Created = 'created';
    case Active = 'active';
    case Paused = 'paused';
    case Backgrounded = 'backgrounded';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case Expired = 'expired';
    case Rejected = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Abandoned, self::Expired, self::Rejected], true);
    }
}
