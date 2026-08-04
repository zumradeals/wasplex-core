<?php

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use App\Modules\AdvertiserStudio\Domain\Enums\AssetKind;
use App\Modules\AdvertiserStudio\Domain\Enums\AssetStatus;
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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $advertiser_space_id
 * @property string $organization_id
 * @property string|null $brand_id
 * @property string $uploaded_by_account_id
 * @property AssetKind $kind
 * @property AssetStatus $status
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $disk
 * @property string $path
 * @property string $checksum_sha256
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_seconds
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read UserSpace $space
 * @property-read Organization $organization
 * @property-read Brand|null $brand
 * @property-read Account $uploader
 * @property-read Collection<int, CreativeAssetVersion> $versions
 * @property-read Collection<int, Brand> $brands
 */
final class CreativeAsset extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'advertiser_space_id',
        'organization_id',
        'brand_id',
        'uploaded_by_account_id',
        'kind',
        'status',
        'original_name',
        'mime_type',
        'size_bytes',
        'disk',
        'path',
        'checksum_sha256',
        'width',
        'height',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'status' => AssetStatus::class,
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'uploaded_by_account_id');
    }

    /** @return HasMany<CreativeAssetVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CreativeAssetVersion::class)->latest('version');
    }

    /** @return BelongsToMany<Brand, $this> */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_assets')
            ->withPivot(['id', 'role', 'sort_order'])
            ->withTimestamps();
    }
}
