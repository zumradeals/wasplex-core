<?php

declare(strict_types=1);

namespace App\Modules\Live\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LiveRewardBudgetReservation extends Model
{
    use HasUlids;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'live_reward_campaign_id',
        'live_reward_quote_id',
        'advertiser_organization_id',
        'amount_minor',
        'released_amount_minor',
        'status',
        'idempotency_key',
        'ledger_transaction_id',
        'reserved_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'released_amount_minor' => 'integer',
            'reserved_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveRewardCampaign::class, 'live_reward_campaign_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(LiveRewardQuote::class, 'live_reward_quote_id');
    }

    public function advertiserOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'advertiser_organization_id');
    }
}
