<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\Wallet\Infrastructure\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $quote_id
 * @property string $wallet_id
 * @property int $amount_minor
 * @property string $unit
 * @property string $currency
 * @property string $status
 * @property CarbonImmutable $reserved_at
 * @property CarbonImmutable|null $submitted_at
 * @property-read Campaign $campaign
 * @property-read CampaignQuote $quote
 * @property-read Wallet $wallet
 * @property-read CampaignBudgetReservation|null $budgetReservation
 */
final class CampaignFunding extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id',
        'quote_id',
        'wallet_id',
        'amount_minor',
        'unit',
        'currency',
        'status',
        'reserved_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<CampaignQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(CampaignQuote::class, 'quote_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return HasOne<CampaignBudgetReservation, $this> */
    public function budgetReservation(): HasOne
    {
        return $this->hasOne(CampaignBudgetReservation::class);
    }
}
