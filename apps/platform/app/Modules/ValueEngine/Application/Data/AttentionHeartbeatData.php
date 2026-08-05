<?php

namespace App\Modules\ValueEngine\Application\Data;

use DateTimeInterface;

final readonly class AttentionHeartbeatData
{
    public function __construct(
        public int $sequence,
        public int $activeDurationMs,
        public bool $visible,
        public DateTimeInterface $clientOccurredAt,
        public string $idempotencyKey,
    ) {}
}
