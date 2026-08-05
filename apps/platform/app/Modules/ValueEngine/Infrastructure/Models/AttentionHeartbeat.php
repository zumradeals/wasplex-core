<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $value_attention_session_id
 * @property int $sequence
 * @property string $idempotency_key
 * @property bool $visible
 * @property int $active_duration_ms
 * @property CarbonImmutable $client_occurred_at
 * @property CarbonImmutable $received_at
 * @property string $payload_hash
 * @property array<string, mixed>|null $metadata
 * @property-read AttentionSession $session
 */
final class AttentionHeartbeat extends Model
{
    use HasUlids;

    protected $table = 'value_attention_heartbeats';

    protected $fillable = [
        'value_attention_session_id',
        'sequence',
        'idempotency_key',
        'visible',
        'active_duration_ms',
        'client_occurred_at',
        'received_at',
        'payload_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'visible' => 'boolean',
            'active_duration_ms' => 'integer',
            'client_occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AttentionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttentionSession::class, 'value_attention_session_id');
    }
}
