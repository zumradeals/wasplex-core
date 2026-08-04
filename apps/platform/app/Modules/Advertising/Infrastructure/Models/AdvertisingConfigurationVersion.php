<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $version
 * @property string $state
 * @property int $minimum_segment_size
 * @property int $estimate_rounding_step
 * @property int $frequency_window_hours
 * @property int $frequency_limit
 * @property int $fatigue_limit
 * @property string|null $created_by_account_id
 * @property string|null $published_by_account_id
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property CarbonImmutable $published_at
 * @property string|null $suspension_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Account|null $creator
 * @property-read Account|null $publisher
 */
final class AdvertisingConfigurationVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'version',
        'state',
        'minimum_segment_size',
        'estimate_rounding_step',
        'frequency_window_hours',
        'frequency_limit',
        'fatigue_limit',
        'created_by_account_id',
        'published_by_account_id',
        'effective_from',
        'effective_to',
        'published_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'minimum_segment_size' => 'integer',
            'estimate_rounding_step' => 'integer',
            'frequency_window_hours' => 'integer',
            'frequency_limit' => 'integer',
            'fatigue_limit' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'published_by_account_id');
    }
}
