<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $category
 * @property string $label
 * @property string $value_type
 * @property bool $allowed_for_targeting
 * @property bool $sensitive
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, AdvertisingProfileQuestion> $questions
 * @property-read Collection<int, AdvertisingProfileAnswer> $answers
 */
final class AdvertisingTaxonomy extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'category',
        'label',
        'value_type',
        'allowed_for_targeting',
        'sensitive',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'allowed_for_targeting' => 'boolean',
            'sensitive' => 'boolean',
            'metadata' => 'array',
        ];
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
}
