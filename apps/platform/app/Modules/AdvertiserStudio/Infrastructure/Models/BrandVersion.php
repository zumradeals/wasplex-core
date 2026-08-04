<?php

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $brand_id
 * @property int $version
 * @property string $public_name
 * @property string|null $slogan
 * @property string|null $description
 * @property string $primary_color
 * @property string $secondary_color
 * @property string|null $logo_asset_id
 * @property string $state
 * @property string $created_by_account_id
 * @property string|null $published_by_account_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Brand $brand
 * @property-read CreativeAsset|null $logoAsset
 * @property-read Account $creator
 * @property-read Account|null $publisher
 */
final class BrandVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'brand_id',
        'version',
        'public_name',
        'slogan',
        'description',
        'primary_color',
        'secondary_color',
        'logo_asset_id',
        'state',
        'created_by_account_id',
        'published_by_account_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<CreativeAsset, $this> */
    public function logoAsset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class, 'logo_asset_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'published_by_account_id');
    }
}
