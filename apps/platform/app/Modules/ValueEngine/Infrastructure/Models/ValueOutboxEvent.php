<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property string $event_code
 * @property string $idempotency_key
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $published_at
 * @property int $publication_attempts
 * @property string|null $last_error
 */
final class ValueOutboxEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_code',
        'idempotency_key',
        'payload',
        'occurred_at',
        'published_at',
        'publication_attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'publication_attempts' => 'integer',
        ];
    }
}
