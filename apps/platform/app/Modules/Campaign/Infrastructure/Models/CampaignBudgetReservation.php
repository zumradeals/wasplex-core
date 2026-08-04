<?php

namespace App\Modules\Campaign\Infrastructure\Models;

use App\Modules\Wallet\Infrastructure\Models\WalletReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $campaign_funding_id
 * @property string $wallet_reservation_id
 * @property string $business_reference
 * @property string $status
 * @property CarbonImmutable|null $expires_at
 * @property-read CampaignFunding $funding
 * @property-read WalletReservation $walletReservation
 */
final class CampaignBudgetReservation extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_funding_id',
        'wallet_reservation_id',
        'business_reference',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<CampaignFunding, $this> */
    public function funding(): BelongsTo
    {
        return $this->belongsTo(CampaignFunding::class, 'campaign_funding_id');
    }

    /** @return BelongsTo<WalletReservation, $this> */
    public function walletReservation(): BelongsTo
    {
        return $this->belongsTo(WalletReservation::class);
    }
}
