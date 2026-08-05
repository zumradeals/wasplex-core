<?php

namespace App\Modules\ValueEngine\Infrastructure\Models;

use App\Modules\Campaign\Infrastructure\Models\CampaignFunding;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $campaign_funding_id
 * @property int $limit_amount_minor
 * @property int $reserved_amount_minor
 * @property int $settled_amount_minor
 * @property int $released_amount_minor
 * @property int $version
 * @property-read CampaignFunding $funding
 */
final class ValueCampaignBudgetCounter extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_funding_id',
        'limit_amount_minor',
        'reserved_amount_minor',
        'settled_amount_minor',
        'released_amount_minor',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'limit_amount_minor' => 'integer',
            'reserved_amount_minor' => 'integer',
            'settled_amount_minor' => 'integer',
            'released_amount_minor' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<CampaignFunding, $this> */
    public function funding(): BelongsTo
    {
        return $this->belongsTo(CampaignFunding::class, 'campaign_funding_id');
    }

    public function availableAmountMinor(): int
    {
        return max(0, $this->limit_amount_minor - $this->settled_amount_minor - $this->reserved_amount_minor);
    }
}
