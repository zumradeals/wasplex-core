<?php

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use App\Modules\AdvertiserStudio\Domain\Enums\BrandStatus;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $advertiser_space_id
 * @property string $organization_id
 * @property string $created_by_account_id
 * @property string $name
 * @property string $slug
 * @property BrandStatus $status
 * @property string|null $active_version_id
 * @property int|null $versions_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read UserSpace $space
 * @property-read Organization $organization
 * @property-read Account $creator
 * @property-read BrandVersion|null $activeVersion
 * @property-read Collection<int, BrandVersion> $versions
 * @property-read Collection<int, CreativeAsset> $assets
 */
final class Brand extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertiser_space_id',
        'organization_id',
        'created_by_account_id',
        'name',
        'slug',
        'status',
        'active_version_id',
    ];

    protected function casts(): array
    {
        return ['status' => BrandStatus::class];
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

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsTo<BrandVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(BrandVersion::class, 'active_version_id');
    }

    /** @return HasMany<BrandVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BrandVersion::class)->latest('version');
    }

    /** @return BelongsToMany<CreativeAsset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(CreativeAsset::class, 'brand_assets')
            ->withPivot(['id', 'role', 'sort_order'])
            ->withTimestamps();
    }
}
