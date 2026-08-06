<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FeedAdDeliveryHold extends Model
{
    use HasUlids;

    public const STATUS_CREATED = 'created';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_RELEASED = 'released';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'feed_ad_delivery_id', 'amount_minor', 'reason_code', 'status',
        'opened_at', 'resolved_at', 'resolved_by', 'resolution_note', 'evidence',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'opened_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'evidence' => 'array',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(FeedAdDelivery::class, 'feed_ad_delivery_id');
    }
}
