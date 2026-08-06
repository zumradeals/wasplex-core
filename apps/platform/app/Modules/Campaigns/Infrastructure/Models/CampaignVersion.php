<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CampaignVersion extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_FUNDED = 'funded';

    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'campaign_id', 'version_number', 'creative_configuration', 'audience_configuration',
        'budget_configuration', 'cta_configuration', 'price_version_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'creative_configuration' => 'array',
            'audience_configuration' => 'array',
            'budget_configuration' => 'array',
            'cta_configuration' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function priceVersion(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPriceVersion::class, 'price_version_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(CampaignQuote::class);
    }

    public function latestQuote(): ?CampaignQuote
    {
        return $this->quotes()->orderByDesc('created_at')->first();
    }
}
