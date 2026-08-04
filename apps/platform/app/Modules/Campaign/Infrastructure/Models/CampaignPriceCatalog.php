<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $version
 * @property string $name
 * @property string $unit
 * @property string $currency
 * @property int $cpm_minor
 * @property int $minimum_budget_minor
 * @property int $platform_share_basis_points
 * @property int $user_share_basis_points
 * @property string $status
 * @property string|null $created_by_account_id
 * @property CarbonImmutable|null $published_at
 */
final class CampaignPriceCatalog extends Model
{
    use HasUlids;

    protected $fillable = [
        'version',
        'name',
        'unit',
        'currency',
        'cpm_minor',
        'minimum_budget_minor',
        'platform_share_basis_points',
        'user_share_basis_points',
        'status',
        'created_by_account_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'cpm_minor' => 'integer',
            'minimum_budget_minor' => 'integer',
            'platform_share_basis_points' => 'integer',
            'user_share_basis_points' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return HasMany<CampaignQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(CampaignQuote::class, 'price_catalog_id');
    }
}
