<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\AdvertiserStudio\Infrastructure\Models\CreativeAsset;
use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $campaign_id
 * @property int $version
 * @property string $objective
 * @property string|null $headline
 * @property string|null $body
 * @property string|null $call_to_action
 * @property string|null $destination_url
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string|null $territory_name
 * @property int|null $radius_km
 * @property array<int, string> $selected_classes
 * @property int $requested_budget_minor
 * @property string $created_by_account_id
 * @property CarbonImmutable|null $created_at
 * @property-read Campaign $campaign
 * @property-read Account $creator
 * @property-read Collection<int, CreativeAsset> $creatives
 * @property-read Collection<int, CampaignAudience> $audiences
 * @property-read Collection<int, CampaignQuote> $quotes
 */
final class CampaignVersion extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id',
        'version',
        'objective',
        'headline',
        'body',
        'call_to_action',
        'destination_url',
        'starts_on',
        'ends_on',
        'territory_name',
        'radius_km',
        'selected_classes',
        'requested_budget_minor',
        'created_by_account_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'radius_km' => 'integer',
            'selected_classes' => 'array',
            'requested_budget_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsToMany<CreativeAsset, $this> */
    public function creatives(): BelongsToMany
    {
        return $this->belongsToMany(CreativeAsset::class, 'campaign_creatives')
            ->withPivot(['id', 'campaign_id', 'sort_order']);
    }

    /** @return HasMany<CampaignAudience, $this> */
    public function audiences(): HasMany
    {
        return $this->hasMany(CampaignAudience::class);
    }

    /** @return HasMany<CampaignQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(CampaignQuote::class);
    }
}
