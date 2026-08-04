<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertising_sector_id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingSector $sector
 */
final class AdvertisingSectorTranslation extends Model
{
    use HasUlids;

    protected $fillable = [
        'locale',
        'name',
        'description',
    ];

    /** @return BelongsTo<AdvertisingSector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(AdvertisingSector::class, 'advertising_sector_id');
    }
}
