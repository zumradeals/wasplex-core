<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Campaign\Infrastructure\Models\CampaignVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property int $version
 * @property string $status
 * @property string $rule_version
 * @property int $minimum_size
 * @property string|null $created_by_account_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Campaign $campaign
 * @property-read CampaignVersion $campaignVersion
 * @property-read Account|null $creator
 * @property-read Collection<int, AdvertisingSegmentRule> $rules
 * @property-read Collection<int, AdvertisingSegmentEstimate> $estimates
 */
final class AdvertisingSegment extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'campaign_version_id',
        'version',
        'status',
        'rule_version',
        'minimum_size',
        'created_by_account_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'minimum_size' => 'integer',
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

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return HasMany<AdvertisingSegmentRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(AdvertisingSegmentRule::class)->orderBy('sort_order');
    }

    /** @return HasMany<AdvertisingSegmentEstimate, $this> */
    public function estimates(): HasMany
    {
        return $this->hasMany(AdvertisingSegmentEstimate::class)->latest('estimated_at');
    }
}
