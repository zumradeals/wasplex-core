<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundReserveAllocation extends Model
{
    use HasUlids;

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_PARTIALLY_CONSUMED = 'partially_consumed';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_RELEASED = 'released';

    protected $table = 'fund_reserve_allocations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FundOrder::class, 'fund_order_id');
    }
}
