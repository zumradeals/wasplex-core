<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdvertisingPriceVersion extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'catalog_id', 'status', 'currency', 'base_price_minor_per_event',
        'image_multiplier', 'video_multiplier', 'minimum_budget_minor', 'duration_days',
        'effective_from', 'effective_to', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price_minor_per_event' => 'integer',
            'image_multiplier' => 'decimal:4',
            'video_multiplier' => 'decimal:4',
            'minimum_budget_minor' => 'integer',
            'duration_days' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPriceCatalog::class, 'catalog_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
