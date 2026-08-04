<?php

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $creative_asset_id
 * @property int $version
 * @property string|null $label
 * @property string|null $alt_text
 * @property string|null $usage_context
 * @property string $created_by_account_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CreativeAsset $asset
 * @property-read Account $creator
 */
final class CreativeAssetVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'creative_asset_id',
        'version',
        'label',
        'alt_text',
        'usage_context',
        'created_by_account_id',
    ];

    /** @return BelongsTo<CreativeAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class, 'creative_asset_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }
}
