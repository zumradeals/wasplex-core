<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundWishQueueEntry extends Model
{
    use HasUlids;

    public const LANE_ORDINARY = 'ordinary';

    public const LANE_EMERGENCY = 'emergency';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RELEASED = 'released';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'fund_wish_queue_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'queued_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FundProgram::class, 'fund_program_id');
    }
}
