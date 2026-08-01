<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $actor_account_id
 * @property string|null $actor_space_id
 * @property string|null $organization_id
 * @property string|null $session_id
 * @property string|null $device_id
 * @property string|null $capability
 * @property string $action
 * @property string $outcome
 * @property string|null $trace_id
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $occurred_at
 */
class AccessAuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'actor_account_id',
        'actor_space_id',
        'organization_id',
        'session_id',
        'device_id',
        'capability',
        'action',
        'outcome',
        'trace_id',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
