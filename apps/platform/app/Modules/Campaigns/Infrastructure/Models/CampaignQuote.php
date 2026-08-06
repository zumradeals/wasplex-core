<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignQuote extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'campaign_version_id', 'currency', 'gross_amount_minor', 'net_distributable_amount_minor',
        'estimated_events', 'estimated_reach_min', 'estimated_reach_max', 'class_breakdown',
        'expires_at', 'price_version_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_minor' => 'integer',
            'net_distributable_amount_minor' => 'integer',
            'estimated_events' => 'integer',
            'estimated_reach_min' => 'integer',
            'estimated_reach_max' => 'integer',
            'class_breakdown' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class);
    }

    public function priceVersion(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPriceVersion::class, 'price_version_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
