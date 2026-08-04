<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $advertising_taxonomy_id
 * @property string $code
 * @property string $status
 * @property int $sort_order
 * @property string|null $current_version_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingTaxonomy $taxonomy
 * @property-read AdvertisingProfileQuestionVersion|null $currentVersion
 * @property-read Collection<int, AdvertisingProfileQuestionVersion> $versions
 * @property-read Collection<int, AdvertisingProfileAnswer> $answers
 */
final class AdvertisingProfileQuestion extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_taxonomy_id',
        'code',
        'status',
        'sort_order',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<AdvertisingTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(AdvertisingTaxonomy::class, 'advertising_taxonomy_id');
    }

    /** @return BelongsTo<AdvertisingProfileQuestionVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AdvertisingProfileQuestionVersion::class, 'current_version_id');
    }

    /** @return HasMany<AdvertisingProfileQuestionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AdvertisingProfileQuestionVersion::class)->latest('version');
    }

    /** @return HasMany<AdvertisingProfileAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AdvertisingProfileAnswer::class);
    }
}
