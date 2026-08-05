<?php

namespace App\Modules\Distribution\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdvertisingDeliveryOutbox
{
    /** @param array<string, mixed> $payload */
    public function record(
        string $aggregateType,
        string $aggregateId,
        string $eventCode,
        string $idempotencyKey,
        array $payload,
    ): void {
        DB::table('advertising_delivery_outbox_events')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_code' => $eventCode,
            'idempotency_key' => $idempotencyKey,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'published_at' => null,
            'publish_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
