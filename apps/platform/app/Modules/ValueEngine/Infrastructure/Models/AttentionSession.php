<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\ValueEngine\Domain\Enums\AttentionSessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $value_attempt_id
 * @property string $account_id
 * @property string $device_session_id
 * @property AttentionSessionStatus $status
 * @property int $required_duration_ms
 * @property int $visible_duration_ms
 * @property int $last_sequence
 * @property string $token_hash
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $last_heartbeat_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $rejected_at
 * @property array<string, mixed>|null $metadata
 * @property-read ValueAttempt $attempt
 * @property-read Account $account
 * @property-read Collection<int, AttentionHeartbeat> $heartbeats
 */
final class AttentionSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'value_attempt_id',
        'account_id',
        'device_session_id',
        'status',
        'required_duration_ms',
        'visible_duration_ms',
        'last_sequence',
        'token_hash',
        'started_at',
        'last_heartbeat_at',
        'completed_at',
        'rejected_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttentionSessionStatus::class,
            'required_duration_ms' => 'integer',
            'visible_duration_ms' => 'integer',
            'last_sequence' => 'integer',
            'started_at' => 'immutable_datetime',
            'last_heartbeat_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ValueAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ValueAttempt::class, 'value_attempt_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<AttentionHeartbeat, $this> */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(AttentionHeartbeat::class, 'value_attention_session_id');
    }
}
