<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Campaign\Infrastructure\Models\Campaign;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $advertising_match_id
 * @property string $campaign_id
 * @property string $subject_hash
 * @property string $decision
 * @property array<int, string> $reason_codes
 * @property string|null $score_band
 * @property CarbonImmutable $occurred_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read AdvertisingMatch|null $match
 * @property-read Campaign $campaign
 */
final class AdvertisingMatchAudit extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_match_id',
        'campaign_id',
        'subject_hash',
        'decision',
        'reason_codes',
        'score_band',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reason_codes' => 'array',
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AdvertisingMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(AdvertisingMatch::class, 'advertising_match_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
