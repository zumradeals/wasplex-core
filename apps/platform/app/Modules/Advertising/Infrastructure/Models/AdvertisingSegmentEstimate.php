<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertising_segment_id
 * @property string $estimate_key
 * @property string $status
 * @property int $raw_count
 * @property int|null $protected_min
 * @property int|null $protected_max
 * @property int|null $rounded_count
 * @property int $threshold
 * @property string $bucket
 * @property string|null $reason_code
 * @property string|null $queried_by_account_id
 * @property CarbonImmutable $estimated_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read AdvertisingSegment $segment
 * @property-read Account|null $queriedBy
 */
final class AdvertisingSegmentEstimate extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_segment_id',
        'estimate_key',
        'status',
        'raw_count',
        'protected_min',
        'protected_max',
        'rounded_count',
        'threshold',
        'bucket',
        'reason_code',
        'queried_by_account_id',
        'estimated_at',
        'metadata',
    ];

    protected $hidden = ['raw_count'];

    protected function casts(): array
    {
        return [
            'raw_count' => 'integer',
            'protected_min' => 'integer',
            'protected_max' => 'integer',
            'rounded_count' => 'integer',
            'threshold' => 'integer',
            'estimated_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AdvertisingSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(AdvertisingSegment::class, 'advertising_segment_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function queriedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'queried_by_account_id');
    }
}
