<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property int $version
 * @property string $estimate_kind
 * @property string $territory_name
 * @property int $radius_km
 * @property array<int, string> $selected_classes
 * @property int $estimated_min
 * @property int $estimated_max
 * @property array<string, mixed>|null $assumptions
 * @property CarbonImmutable $estimated_at
 */
final class CampaignAudience extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'campaign_version_id',
        'version',
        'estimate_kind',
        'territory_name',
        'radius_km',
        'selected_classes',
        'estimated_min',
        'estimated_max',
        'assumptions',
        'estimated_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'radius_km' => 'integer',
            'selected_classes' => 'array',
            'estimated_min' => 'integer',
            'estimated_max' => 'integer',
            'assumptions' => 'array',
            'estimated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<CampaignVersion, $this> */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class);
    }
}
