<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertising_segment_id
 * @property string $advertising_taxonomy_id
 * @property string $operator
 * @property array<int, string> $expected_values
 * @property bool $required
 * @property array<int, string> $purpose_codes
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read AdvertisingSegment $segment
 * @property-read AdvertisingTaxonomy $taxonomy
 */
final class AdvertisingSegmentRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_segment_id',
        'advertising_taxonomy_id',
        'operator',
        'expected_values',
        'required',
        'purpose_codes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expected_values' => 'array',
            'required' => 'boolean',
            'purpose_codes' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<AdvertisingSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(AdvertisingSegment::class, 'advertising_segment_id');
    }

    /** @return BelongsTo<AdvertisingTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(AdvertisingTaxonomy::class, 'advertising_taxonomy_id');
    }
}
