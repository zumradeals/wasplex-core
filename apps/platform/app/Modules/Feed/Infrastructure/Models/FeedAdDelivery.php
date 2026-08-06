<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FeedAdDelivery extends Model
{
    use HasUlids;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'feed_ad_deliveries';

    protected $fillable = [
        'feed_session_id', 'account_id', 'campaign_id', 'organization_id', 'campaign_envelope_consumption_id',
        'economic_class', 'gain_minor', 'required_duration_ms', 'visible_duration_ms',
        'progress_percent', 'status', 'reserved_at', 'started_at', 'completed_at',
        'ledger_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'gain_minor' => 'integer',
            'required_duration_ms' => 'integer',
            'visible_duration_ms' => 'integer',
            'progress_percent' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedSession::class, 'feed_session_id');
    }
}
