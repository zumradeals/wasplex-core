<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $value_event_definition_id
 * @property string $code
 * @property int $version
 * @property string $status
 * @property string|null $country_code
 * @property string $currency
 * @property string|null $economic_class
 * @property int $user_share_basis_points
 * @property int $wasplex_share_basis_points
 * @property int $minimum_event_amount_minor
 * @property int|null $maximum_event_amount_minor
 * @property int $required_attention_ms
 * @property int $heartbeat_interval_ms
 * @property int $attempt_ttl_seconds
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $effective_until
 * @property array<string, mixed>|null $calculation_parameters
 * @property string|null $published_by_account_id
 * @property string|null $approved_by_account_id
 * @property CarbonImmutable|null $published_at
 * @property-read ValueEventDefinition $eventDefinition
 */
final class ValueRuleVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'value_event_definition_id',
        'code',
        'version',
        'status',
        'country_code',
        'currency',
        'economic_class',
        'user_share_basis_points',
        'wasplex_share_basis_points',
        'minimum_event_amount_minor',
        'maximum_event_amount_minor',
        'required_attention_ms',
        'heartbeat_interval_ms',
        'attempt_ttl_seconds',
        'effective_from',
        'effective_until',
        'calculation_parameters',
        'published_by_account_id',
        'approved_by_account_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'user_share_basis_points' => 'integer',
            'wasplex_share_basis_points' => 'integer',
            'minimum_event_amount_minor' => 'integer',
            'maximum_event_amount_minor' => 'integer',
            'required_attention_ms' => 'integer',
            'heartbeat_interval_ms' => 'integer',
            'attempt_ttl_seconds' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_until' => 'immutable_datetime',
            'calculation_parameters' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ValueEventDefinition, $this> */
    public function eventDefinition(): BelongsTo
    {
        return $this->belongsTo(ValueEventDefinition::class, 'value_event_definition_id');
    }
}
