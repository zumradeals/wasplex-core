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
 * @property string|null $advertising_sector_id
 * @property string|null $parent_id
 * @property string $code
 * @property string $category
 * @property string $signal_kind
 * @property array<int, string>|null $country_codes
 * @property string $label
 * @property string $value_type
 * @property bool $allowed_for_targeting
 * @property bool $sensitive
 * @property bool $user_visible
 * @property string $ai_usage_mode
 * @property int|null $default_freshness_days
 * @property int $sort_order
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingSector|null $sector
 * @property-read AdvertisingTaxonomy|null $parent
 * @property-read Collection<int, AdvertisingTaxonomy> $children
 * @property-read Collection<int, AdvertisingProfileQuestion> $questions
 * @property-read Collection<int, AdvertisingProfileAnswer> $answers
 * @property-read Collection<int, AdvertisingProfileSignal> $signals
 */
final class AdvertisingTaxonomy extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertising_sector_id',
        'parent_id',
        'code',
        'category',
        'signal_kind',
        'country_codes',
        'label',
        'value_type',
        'allowed_for_targeting',
        'sensitive',
        'user_visible',
        'ai_usage_mode',
        'default_freshness_days',
        'sort_order',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'country_codes' => 'array',
            'allowed_for_targeting' => 'boolean',
            'sensitive' => 'boolean',
            'user_visible' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AdvertisingSector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(AdvertisingSector::class, 'advertising_sector_id');
    }

    /** @return BelongsTo<AdvertisingTaxonomy, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<AdvertisingTaxonomy, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<AdvertisingProfileQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(AdvertisingProfileQuestion::class);
    }

    /** @return HasMany<AdvertisingProfileAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AdvertisingProfileAnswer::class);
    }

    /** @return HasMany<AdvertisingProfileSignal, $this> */
    public function signals(): HasMany
    {
        return $this->hasMany(AdvertisingProfileSignal::class);
    }

    public function appliesToMarket(string $marketCode): bool
    {
        return $this->country_codes === null
            || $this->country_codes === []
            || in_array(strtoupper($marketCode), $this->country_codes, true);
    }
}
