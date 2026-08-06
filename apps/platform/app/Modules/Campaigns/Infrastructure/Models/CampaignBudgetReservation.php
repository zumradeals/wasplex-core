<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignBudgetReservation extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'campaign_id', 'campaign_quote_id', 'organization_id', 'amount_minor',
        'status', 'idempotency_key', 'ledger_transaction_id',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignQuote(): BelongsTo
    {
        return $this->belongsTo(CampaignQuote::class);
    }
}
