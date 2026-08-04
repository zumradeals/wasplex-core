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
 * @property string|null $parent_id
 * @property string $code
 * @property string $status
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $allowed_for_targeting
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingSector|null $parent
 * @property-read Collection<int, AdvertisingSector> $children
 * @property-read Collection<int, AdvertisingSectorTranslation> $translations
 * @property-read Collection<int, AdvertisingTaxonomy> $taxonomies
 */
final class AdvertisingSector extends Model
{
    use HasUlids;

    protected $fillable = [
        'parent_id',
        'code',
        'status',
        'icon',
        'sort_order',
        'allowed_for_targeting',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'allowed_for_targeting' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AdvertisingSector, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<AdvertisingSector, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<AdvertisingSectorTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AdvertisingSectorTranslation::class);
    }

    /** @return HasMany<AdvertisingTaxonomy, $this> */
    public function taxonomies(): HasMany
    {
        return $this->hasMany(AdvertisingTaxonomy::class);
    }

    public function translatedName(string $locale): string
    {
        $translation = $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'fr')
            ?? $this->translations->first();

        if (! $translation instanceof AdvertisingSectorTranslation) {
            return $this->code;
        }

        return $translation->name;
    }
}
