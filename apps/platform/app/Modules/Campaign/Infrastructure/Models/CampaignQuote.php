<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property string $audience_id
 * @property string $price_catalog_id
 * @property int $version
 * @property string $status
 * @property string $unit
 * @property string $currency
 * @property int $gross_amount_minor
 * @property int $platform_share_minor
 * @property int $user_share_minor
 * @property int $cpm_minor
 * @property int $estimated_impressions
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $funded_at
 * @property-read Campaign $campaign
 * @property-read CampaignVersion $campaignVersion
 * @property-read CampaignAudience $audience
 * @property-read CampaignPriceCatalog $priceCatalog
 * @property-read CampaignFunding|null $funding
 */
final class CampaignQuote extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'campaign_version_id',
        'audience_id',
        'price_catalog_id',
        'version',
        'status',
        'unit',
        'currency',
        'gross_amount_minor',
        'platform_share_minor',
        'user_share_minor',
        'cpm_minor',
        'estimated_impressions',
        'issued_at',
        'expires_at',
        'funded_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'gross_amount_minor' => 'integer',
            'platform_share_minor' => 'integer',
            'user_share_minor' => 'integer',
            'cpm_minor' => 'integer',
            'estimated_impressions' => 'integer',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'funded_at' => 'immutable_datetime',
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

    /** @return BelongsTo<CampaignAudience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(CampaignAudience::class);
    }

    /** @return BelongsTo<CampaignPriceCatalog, $this> */
    public function priceCatalog(): BelongsTo
    {
        return $this->belongsTo(CampaignPriceCatalog::class, 'price_catalog_id');
    }

    /** @return HasOne<CampaignFunding, $this> */
    public function funding(): HasOne
    {
        return $this->hasOne(CampaignFunding::class, 'quote_id');
    }
}
