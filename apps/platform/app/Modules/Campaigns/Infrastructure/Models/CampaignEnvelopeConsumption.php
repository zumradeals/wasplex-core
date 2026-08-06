<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignEnvelopeConsumption extends Model
{
    use HasUlids;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'campaign_envelope_consumptions';

    protected $fillable = [
        'campaign_quote_id', 'economic_class', 'status', 'gain_minor',
        'reserved_at', 'expires_at', 'captured_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'gain_minor' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CampaignQuote::class, 'campaign_quote_id');
    }
}
