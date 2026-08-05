<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\ValueEngine\Infrastructure\Models\ValueOutboxEvent;

final class ValueOutbox
{
    /** @param array<string, mixed> $payload */
    public function record(
        string $aggregateType,
        string $aggregateId,
        string $eventCode,
        string $idempotencyKey,
        array $payload,
    ): void {
        ValueOutboxEvent::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'event_code' => $eventCode,
                'payload' => $payload,
                'occurred_at' => now(),
                'publication_attempts' => 0,
            ],
        );
    }
}
