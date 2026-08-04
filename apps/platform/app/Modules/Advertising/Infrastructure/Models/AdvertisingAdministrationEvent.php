<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $actor_account_id
 * @property string $event_type
 * @property string $resource_type
 * @property string|null $resource_id
 * @property string|null $resource_code
 * @property string|null $before_state
 * @property string|null $after_state
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Account|null $actor
 */
final class AdvertisingAdministrationEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'actor_account_id',
        'event_type',
        'resource_type',
        'resource_id',
        'resource_code',
        'before_state',
        'after_state',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'actor_account_id');
    }
}
