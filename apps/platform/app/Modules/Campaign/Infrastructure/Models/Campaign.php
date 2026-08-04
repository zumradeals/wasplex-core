<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\AdvertiserStudio\Infrastructure\Models\Brand;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $advertiser_space_id
 * @property string $organization_id
 * @property string $brand_id
 * @property string $created_by_account_id
 * @property string $name
 * @property string $status
 * @property string|null $active_version_id
 * @property int $current_step
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read UserSpace $space
 * @property-read Organization $organization
 * @property-read Brand $brand
 * @property-read Account $creator
 * @property-read CampaignVersion|null $activeVersion
 * @property-read Collection<int, CampaignVersion> $versions
 * @property-read Collection<int, CampaignAudience> $audiences
 * @property-read Collection<int, CampaignQuote> $quotes
 * @property-read CampaignFunding|null $funding
 * @property-read Collection<int, CampaignReviewCase> $reviewCases
 * @property-read Collection<int, CampaignStatusEvent> $statusEvents
 */
final class Campaign extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertiser_space_id',
        'organization_id',
        'brand_id',
        'created_by_account_id',
        'name',
        'status',
        'active_version_id',
        'current_step',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<UserSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'advertiser_space_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsTo<CampaignVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class, 'active_version_id');
    }

    /** @return HasMany<CampaignVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CampaignVersion::class)->latest('version');
    }

    /** @return HasMany<CampaignAudience, $this> */
    public function audiences(): HasMany
    {
        return $this->hasMany(CampaignAudience::class)->latest('version');
    }

    /** @return HasMany<CampaignQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(CampaignQuote::class)->latest('version');
    }

    /** @return HasOne<CampaignFunding, $this> */
    public function funding(): HasOne
    {
        return $this->hasOne(CampaignFunding::class)->latestOfMany();
    }

    /** @return HasMany<CampaignReviewCase, $this> */
    public function reviewCases(): HasMany
    {
        return $this->hasMany(CampaignReviewCase::class)->latest('submission_number');
    }

    /** @return HasMany<CampaignStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(CampaignStatusEvent::class)->latest('occurred_at');
    }
}
